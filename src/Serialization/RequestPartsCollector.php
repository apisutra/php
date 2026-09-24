<?php

declare(strict_types=1);

namespace ApiSutra\Serialization;

use ApiSutra\Localization\Message;
use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\DateTimeSerializationPolicy;
use ApiSutra\Config\DtoSerializationPolicy;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Enums\Serialization\EnumOutput;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Serialization\EnumSerializationHelper;
use ApiSutra\Serialization\VO\ResolvedDtoSerialization;
use ApiSutra\Serialization\VO\RequestPartsBag;
use ApiSutra\Serialization\Rules\ReceiverOutput;
use ApiSutra\Support\ArrayPath;
use ApiSutra\VO\Files\FileInput;
use ApiSutra\VO\Pipeline\PipelineContext;
use Closure;
use ApiSutra\Metadata\MetadataCatalog;
use ApiSutra\Serialization\Plan\SerializationValuePlan;
use UnitEnum;
use ApiSutra\Serialization\Plan\RequestPartsPlanCompiler;
use ApiSutra\Serialization\Plan\RequestFieldTarget;

final readonly class RequestPartsCollector
{
    private NamingStrategyResolver $namingStrategyResolver;
    private EnumSerializationHelper $enumSerializer;
    private RequestPartsPlanCompiler $plans;
    private SerializationValueResolver $values;

    /**
     * @param Closure(object, ?PipelineContext): array $dtoSerializer
     */
    public function __construct(
        CastRegistry $casts,
        ?AttributeMetadataCache $cache,
        private Closure $dtoSerializer,
        private ?ReceiverOutput $receiverOutput = null,
        ?MetadataCatalog $catalog = null,
    ) {
        $this->namingStrategyResolver = new NamingStrategyResolver();
        $this->enumSerializer = new EnumSerializationHelper();
        $this->plans = new RequestPartsPlanCompiler($cache, $catalog);
        $this->values = new SerializationValueResolver($casts, receiverOutput: $receiverOutput);
    }

    /**
     * @param array<string, mixed> $paginationOverrides
     * @param array<string, mixed> $placeholders
     */
    public function collect(
        RequestInterface $request,
        ?PipelineContext $context,
        array $paginationOverrides,
        array $placeholders,
        HttpMethod $method,
    ): RequestPartsBag {
        $plan = $this->plans->bind($request::class);
        $query = [];
        $body = [];
        $rootBody = null;
        $headers = [];
        $files = [];
        $fileFormat = null;
        $bodySerialization = new ResolvedDtoSerialization(
            policy: $this->resolveWireBodyPolicy($context),
            casts: new CastRegistry(),
        );
        $requestPartsOutput = $this->resolveRequestPartsEnumOutput($context);
        $strictMode = $this->resolveRequestPartsStrictEnums($context);
        $unmappedTarget = $plan->unmappedTarget($method);
        $bodyIsRoot = $plan->bodyRoot !== null;
        $rootBodyName = $plan->bodyRoot;

        foreach ($plan->fields as $field) {
            if (
                $field->target === RequestFieldTarget::Skip
                || $field->name === $this->receiverOutput?->receiverFor($request::class)
            ) {
                continue;
            }

            $name = $field->name;
            $value = $paginationOverrides[$name] ?? $request->{$name};
            $target = $field->route(array_key_exists($name, $placeholders), $unmappedTarget);
            $valuePlan = $field->value;

            if ($target === RequestFieldTarget::BodyRoot) {
                if ($rootBodyName !== $name) {
                    continue;
                }

                if (array_key_exists($name, $placeholders)) {
                    throw new ConfigurationException(
                        new Message('serialization.bodyroot_cannot_be_combined_with_path_placeholder', ['name' => $name]),
                    );
                }

                $rootBody = $this->serializeValue(
                    $value,
                    $valuePlan,
                    $context,
                    $bodySerialization,
                );

                continue;
            }

            if ($target === RequestFieldTarget::File) {
                if ($bodyIsRoot) {
                    throw new ConfigurationException(
                        new Message('serialization.bodyroot_cannot_be_combined_with_file', ['name' => $name]),
                    );
                }

                $fileFormat = $field->fileFormat;
                $fileName = $field->partName ?? $name;
                $this->collectFiles($files, $fileName, $value);
                continue;
            }

            if ($target === RequestFieldTarget::Header) {
                if ($value !== null) {
                    $value = $this->serializeEnumOnly($value, $requestPartsOutput, $strictMode);
                    $headers[$field->partName ?? $name] = (string) $value;
                }
                continue;
            }

            if ($target === RequestFieldTarget::Path) {
                $paramName = $field->partName ?? $name;
                if ($value !== null) {
                    $value = $this->serializeEnumOnly($value, $requestPartsOutput, $strictMode);
                    $placeholders[$paramName] = $value;
                } else {
                    $placeholders[$paramName] = null;
                }
                continue;
            }

            $targetName = $field->queryName ?? $this->namingStrategyResolver->resolve($name, $context);

            if ($target === RequestFieldTarget::Body) {
                if ($bodyIsRoot) {
                    throw new ConfigurationException(
                        new Message('serialization.bodyroot_cannot_be_combined_with_body', ['name' => $name]),
                    );
                }

                $value = $this->serializeValue(
                    $value,
                    $valuePlan,
                    $context,
                    $bodySerialization,
                );
                if ($value === null && !$bodySerialization->policy->serializeNulls) {
                    continue;
                }

                if ($field->bodyNested !== null) {
                    $path = $field->bodyNested !== '' ? $field->bodyNested : $targetName;
                    ArrayPath::setByPath($body, $path, $value);
                    continue;
                }

                $body[$targetName] = $value;
                continue;
            }

            if ($target === RequestFieldTarget::Query) {
                $value = $this->serializeRequestPartValue(
                    $value,
                    $valuePlan,
                    $context,
                    $requestPartsOutput,
                    $strictMode,
                );
                if ($value === null && !($field->queryNullable ?? $context?->config->serializeNulls ?? false)) {
                    continue;
                }

                $query[$targetName] = [
                    'value' => $value,
                    'format' => $field->queryFormat,
                ];
                continue;
            }

            if ($bodyIsRoot) {
                throw new ConfigurationException(
                    new Message('serialization.bodyroot_conflicts_with_a_field_included_in_the_body', ['name' => $name]),
                );
            }

            $value = $this->serializeValue(
                $value,
                $valuePlan,
                $context,
                $bodySerialization,
            );
            if ($value === null && !$bodySerialization->policy->serializeNulls) {
                continue;
            }
            $body[$targetName] = $value;
        }

        return new RequestPartsBag(
            query: $query,
            body: $bodyIsRoot ? $rootBody : $body,
            bodyIsRoot: $bodyIsRoot,
            headers: $headers,
            files: $files,
            fileFormat: $fileFormat,
            placeholders: $placeholders,
        );
    }

    /**
     * @param array<int, array{name: string, file: FileInput}> $files
     */
    private function collectFiles(array &$files, string $name, mixed $value): void
    {
        if ($value instanceof FileInput) {
            $files[] = ['name' => $name, 'file' => $value];
            return;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if ($item instanceof FileInput) {
                    $files[] = ['name' => $name, 'file' => $item];
                }
            }
        }
    }

    private function serializeValue(
        mixed $value,
        SerializationValuePlan $plan,
        ?PipelineContext $context,
        ResolvedDtoSerialization $resolved,
    ): mixed {
        return $this->values->resolve(
            value: $value,
            cast: null,
            dateTimeTo: null,
            property: $plan->property,
            context: $context,
            policy: $resolved->policy,
            dtoSerializer: $this->dtoSerializer,
            plan: $plan,
        );
    }

    private function serializeRequestPartValue(
        mixed $value,
        SerializationValuePlan $plan,
        ?PipelineContext $context,
        EnumOutput $enumOutput,
        bool $strictMode,
    ): mixed {
        $policy = $this->buildRequestPartsPolicy($context, $enumOutput, $strictMode);

        return $this->values->resolve(
            value: $value,
            cast: null,
            dateTimeTo: null,
            property: $plan->property,
            context: $context,
            policy: $policy,
            dtoSerializer: $this->dtoSerializer,
            plan: $plan,
        );
    }

    private function buildRequestPartsPolicy(
        ?PipelineContext $context,
        EnumOutput $enumOutput,
        bool $strictMode,
    ): DtoSerializationPolicy {
        return new DtoSerializationPolicy(
            enumOutput: $enumOutput,
            strictEnums: $strictMode,
            dateTime: new DateTimeSerializationPolicy(
                format: $context?->config->requestDateTime->format ?? DATE_ATOM,
                timezone: $context?->config->requestDateTime->timezone,
            ),
        );
    }

    private function resolveWireBodyPolicy(?PipelineContext $context): DtoSerializationPolicy
    {
        $explicit = $context?->config->wireBodySerializationPolicy;
        if ($explicit instanceof DtoSerializationPolicy) {
            return $explicit;
        }

        return new DtoSerializationPolicy(
            enumOutput: EnumOutput::Value,
            strictEnums: false,
            serializeNulls: false,
            dateTime: $context?->config->requestDateTime ?? new DateTimeSerializationPolicy(),
        );
    }

    private function resolveRequestPartsEnumOutput(?PipelineContext $context): EnumOutput
    {
        return $context?->config->requestPartsEnumOutput ?? EnumOutput::Value;
    }

    private function resolveRequestPartsStrictEnums(?PipelineContext $context): bool
    {
        return $context?->config->requestPartsStrictEnums ?? false;
    }

    private function serializeEnumOnly(mixed $value, EnumOutput $output, bool $strictMode): mixed
    {
        if ($value instanceof UnitEnum) {
            return $this->enumSerializer->serializeEnum($value, $output, $strictMode);
        }

        return $value;
    }
}
