<?php

declare(strict_types=1);

namespace ApiSutra\Serialization;

use ApiSutra\Contracts\Interfaces\Localization\LocalizableExceptionInterface;
use ApiSutra\Localization\Message;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Metadata\MetadataCatalog;
use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\DtoSerializationPolicy;
use ApiSutra\Serialization\VO\ResolvedDtoSerialization;
use ApiSutra\Serialization\Rules\ReceiverOutput;
use ApiSutra\Serialization\Rules\RuleSetCompiler;
use ApiSutra\Support\ArrayPath;
use ApiSutra\VO\Pipeline\PipelineContext;
use ApiSutra\Exceptions\Serialization\SerializationException;
use ApiSutra\Serialization\Traversal\TraversalState;
use ApiSutra\Serialization\Traversal\TraversalFrames;
use ApiSutra\Serialization\Plan\SerializationPlanCompiler;

final class DtoSerializer
{
    private static ?self $default = null;

    /** Мост повторного входа; между операциями содержит только пустой набор id. */
    private readonly TraversalFrames $traversal;
    private ?ReceiverOutput $receiverOutput;
    private ?SerializationPlanCompiler $plans = null;
    private MetadataCatalog $catalog;
    private readonly DtoSerializationProfileResolver $profiles;
    private readonly PropertyTypeInspector $types;

    public function __construct(
        private readonly CastRegistry $casts,
        private readonly ?AttributeMetadataCache $cache = null,
        ?DtoSerializationProfileResolver $profileResolver = null,
        ?HydrationConfig $config = null,
        private readonly LocalizationConfig $localization = new LocalizationConfig(),
    ) {
        try {
            $this->traversal = new TraversalFrames();
            $this->catalog = $cache?->catalog() ?? new MetadataCatalog();
            $this->profiles = $profileResolver ?? new DtoSerializationProfileResolver();
            $this->types = new PropertyTypeInspector();
            $this->receiverOutput = $config === null ? null : new ReceiverOutput(
                new RuleSetCompiler($config, $this->catalog),
            );
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($this->localization);
        }
    }

    /** @internal Исходящее представление использует описание клиента без повторной компиляции. */
    public static function withDescriptions(
        CastRegistry $casts,
        ?AttributeMetadataCache $cache,
        RuleSetCompiler $descriptions,
        LocalizationConfig $localization = new LocalizationConfig(),
    ): self {
        $serializer = new self($casts, $cache, localization: $localization);
        $serializer->catalog = $descriptions->catalog();
        $serializer->receiverOutput = new ReceiverOutput($descriptions);
        return $serializer;
    }

    public static function default(): self
    {
        return self::$default ??= new self(
            CastRegistry::global(),
        );
    }

    /**
     * Сериализовать DTO в массив для body.
     *
     * @return array<string, mixed>
     */
    public function serialize(object $dto, ?PipelineContext $context = null): array
    {
        try {
            $this->receiverOutput?->receiverFor($dto::class);
            $resolved = $this->resolveSerialization($dto::class, $context?->config);

            return $this->serializeResolved(
                dto: $dto,
                resolved: $resolved,
                context: $context,
                nestedDtoSerializer: fn (object $nestedDto, ?PipelineContext $nestedContext): array => $this->serialize(
                    $nestedDto,
                    $nestedContext,
                ),
            );
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($context?->config->localization ?? $this->localization);
        }
    }

    /**
     * Сериализовать DTO по явно переданной policy.
     *
     * @return array<string, mixed>
     */
    public function serializeWithPolicy(
        object $dto,
        DtoSerializationPolicy $policy,
        ?PipelineContext $context = null,
        ?CastRegistry $casts = null,
    ): array {
        try {
            return $this->serializeResolved(
                dto: $dto,
                resolved: new ResolvedDtoSerialization(
                    policy: $policy,
                    casts: $casts ?? new CastRegistry(),
                ),
                context: $context,
                nestedDtoSerializer: fn (object $nestedDto, ?PipelineContext $nestedContext): array => $this->serializeWithPolicy(
                    dto: $nestedDto,
                    policy: $policy,
                    context: $nestedContext,
                    casts: $casts,
                ),
            );
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($context?->config->localization ?? $this->localization);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeResolved(
        object $dto,
        ResolvedDtoSerialization $resolved,
        ?PipelineContext $context,
        callable $nestedDtoSerializer,
    ): array {
        $traversal = $this->traversal->current();
        $id = spl_object_id($dto);
        if (isset($traversal->ancestors[$id]) || count($traversal->ancestors) >= TraversalState::MAX_DEPTH) {
            throw new SerializationException(new Message('serialization.circular_reference_or_dto_serialization_depth_exceeded'));
        }
        $traversal->ancestors[$id] = true;
        try {
            return $this->serializeFields($dto, $resolved, $context, $nestedDtoSerializer);
        } finally {
            unset($traversal->ancestors[$id]);
        }
    }

    /** @return array<string, mixed> */
    private function serializeFields(
        object $dto,
        ResolvedDtoSerialization $resolved,
        ?PipelineContext $context,
        callable $nestedDtoSerializer,
    ): array {
        $this->plans ??= new SerializationPlanCompiler($this->cache, $this->catalog);
        $fields = $this->plans->bind($dto::class);
        $valueResolver = new SerializationValueResolver(
            $resolved->casts,
            propertyTypeInspector: $this->types,
            receiverOutput: $this->receiverOutput,
        );
        $data = [];
        foreach ($fields as $field) {
            if ($field->name === $this->receiverOutput?->receiverFor($dto::class)) {
                continue;
            }

            $property = $field->value->property;
            $value = $property->isInitialized($dto) ? $property->getValue($dto) : null;
            $name = $field->outputName($resolved->policy);
            $value = $valueResolver->resolve(
                value: $value,
                cast: null,
                dateTimeTo: $field->dateTimeTo,
                property: $property,
                context: $context,
                policy: $resolved->policy,
                dtoSerializer: $nestedDtoSerializer,
                plan: $field->value,
            );

            if ($value === null && !$resolved->policy->serializeNulls) {
                continue;
            }
            if (str_contains($name, '.')) {
                ArrayPath::setByPath($data, $name, $value);
            } else {
                $data[$name] = $value;
            }
        }
        return $data;
    }

    private function resolveSerialization(string $dtoClass, ?ClientConfig $config): ResolvedDtoSerialization
    {
        return $this->profiles->resolveForDto($dtoClass, $config);
    }
}
