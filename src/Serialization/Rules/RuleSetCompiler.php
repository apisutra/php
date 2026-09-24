<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

use ApiSutra\Localization\Message;
use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\DateTimeFrom;
use ApiSutra\Attributes\DataTransfer\DateTimeTo;
use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Attributes\DataTransfer\DtoHydrate;
use ApiSutra\Attributes\DataTransfer\DtoHydrationProfile;
use ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Attributes\DataTransfer\Map;
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\BodyRoot;
use ApiSutra\Attributes\Request\File;
use ApiSutra\Attributes\Request\Header;
use ApiSutra\Attributes\Request\Path;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Collections\AbstractTypedCollection;
use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\DtoInterface;
use ApiSutra\Enums\DataTransfer\NestedDiscriminatorMode;
use ApiSutra\Enums\DataTransfer\NestedUnknownVariant;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Metadata\MetadataCatalog;
use ApiSutra\Metadata\ClassMetadata;
use ApiSutra\Attributes\DataTransfer\ConstructorValue;
use ApiSutra\Attributes\DataTransfer\Extras;
use ApiSutra\Attributes\DataTransfer\ForbidExplicitNull;
use ApiSutra\Attributes\DataTransfer\RequiredInput;
use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Serialization\Shapes\ShapeCompiler;
use Throwable;
use DateTimeInterface;
use JsonSerializable;
use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Stringable;

/** @internal Разрешает описание DTO без создания DTO, пользовательских профилей и обработчиков. */
final class RuleSetCompiler
{
    private const array INPUT_ATTRIBUTES = [
        From::class, Map::class, Nested::class, Cast::class,
        DateTimeFrom::class, EmptyStringAsNull::class, DefaultValue::class,
        RequiredInput::class, ForbidExplicitNull::class, ConstructorValue::class, Shape::class,
    ];
    private const array OUTPUT_ATTRIBUTES = [
        To::class, DateTimeTo::class, Query::class, Body::class, BodyRoot::class,
        Header::class, Path::class, File::class,
    ];

    /** @var array<class-string, CompiledDtoRules> */
    private array $compiled = [];

    /** @var array<class-string, string|null> */
    private array $receivers = [];

    /** @var array<class-string, CompiledDtoRules> Доступны только внутреннему обходу текущего корня. */
    private array $drafts = [];
    private bool $compiling = false;
    private bool $resetOnFailure = false;
    private ?self $hydrationNodes = null;

    public function __construct(
        private readonly ?HydrationConfig $config = null,
        private readonly MetadataCatalog $metadata = new MetadataCatalog(),
        private readonly bool $deferNested = false,
    ) {
        $rules = $config?->rules;
        $this->validatePolicy($config->policy ?? new RulePolicy());
        if ($rules !== null) {
            $this->validatePolicy($rules->defaults());
        }
        foreach ($deferNested || $config?->hydrator !== null ? [] : ($rules?->definitions() ?? []) as $class => $declaration) {
            $this->forClass($class);
        }
    }

    /** @internal Общая нейтральная структура для направленных планов текущей сборки. */
    public function catalog(): MetadataCatalog
    {
        return $this->metadata;
    }

    public function forClass(string $class): CompiledDtoRules
    {
        if (isset($this->compiled[$class])) {
            return $this->compiled[$class];
        }
        return $this->compileRoot($class);
    }

    /** @internal Вложенный узел выбирает custom/native до компиляции собственных правил. */
    public function forHydrationNode(string $class): CompiledDtoRules
    {
        $this->hydrationNodes ??= new self($this->config, $this->metadata, deferNested: true);
        return $this->hydrationNodes->forClass($class);
    }

    private function compileRoot(string $class, ?ClassMetadata $description = null): CompiledDtoRules
    {
        $this->assertAvailable($class);
        $this->compiling = true;
        try {
            $compiled = $this->compile($class, $description);
            // Все потенциально бросающие проверки закончены; между записями нет пользовательского кода.
            $this->compiled += $this->drafts;
            return $compiled;
        } catch (Throwable $exception) {
            if ($this->resetOnFailure) {
                $this->compiled = [];
                $this->receivers = [];
            }
            throw $exception;
        } finally {
            $this->drafts = [];
            $this->resetOnFailure = false;
            $this->compiling = false;
        }
    }

    private function assertAvailable(string $class): void
    {
        if ($this->compiling) {
            throw new ConfigurationException(new Message('serialization.reentrant_access_to_unfinished_dto_compilation', ['class' => $class]));
        }
    }

    private function compile(string $class, ?ClassMetadata $description = null): CompiledDtoRules
    {
        if (isset($this->compiled[$class]) || isset($this->drafts[$class])) {
            return $this->compiled[$class] ?? $this->drafts[$class];
        }
        if (!class_exists($class)) {
            throw new ConfigurationException(new Message('serialization.rule_set_dto_class_not_found', ['class' => $class]));
        }
        $description ??= $this->metadata->forClass($class);
        $reflection = $description->reflection;
        if ($reflection->isAbstract() || $reflection->isEnum()) {
            throw new ConfigurationException(new Message('serialization.rule_set_dto_class_is_not_instantiable', ['class' => $class]));
        }
        $external = $this->config?->rules?->rulesFor($class);
        $declaration = $external;
        $profile = $this->hasProfile($description);
        if ($profile && $declaration !== null) {
            throw new ConfigurationException(new Message('serialization.dtorules_conflicts_with_dtohydrate_dtohydrationprofile', ['class' => $class]));
        }
        $base = $this->config->policy ?? new RulePolicy();
        $policy = $profile ? $base : ($declaration->policy ?? new RulePolicy())
            ->over(($this->config?->rules?->defaults() ?? new RulePolicy())->over($base));
        $override = $this->classOverride($description);
        if ($override?->scalars !== null) {
            $policy = (new RulePolicy(scalars: $override->scalars))->over($policy);
        }
        [$declaration, $origins, $attributes] = $this->attributeDeclarations($description, $declaration);
        $compiled = new CompiledDtoRules(
            $reflection,
            $declaration,
            $policy,
            $profile,
            $this->config !== null || $attributes || $override?->scalars !== null,
            $origins,
        );
        // Прежняя граница полного сброса сохраняется отдельно от публикации ready.
        $this->resetOnFailure = true;
        $this->drafts[$class] = $compiled;
        $this->validatePolicy($policy);
        foreach ($declaration->fields ?? [] as $name => $field) {
            $property = $this->property($reflection, $name);
            if (isset($external?->fields[$name])) {
                $this->rejectAttributes($property, self::INPUT_ATTRIBUTES);
            }
            if ($field->policy !== null) {
                $this->validatePolicy($field->policy);
            }
            if ($field->from === '' || in_array('', $field->fallback, true)) {
                throw new ConfigurationException(new Message('serialization.fieldrule_from_fallback_path_must_not_be_empty'));
            }
            if ($field->cast !== null) {
                $this->validateHandler($field->cast, HydrationCastInterface::class, $class . '::$' . $name);
            }
            if ($field->default?->provider !== null) {
                $this->validateHandler($field->default->provider, DefaultValueProviderInterface::class);
            }
            if ($field->constructorValue) {
                $this->validateConstructorValue($reflection, $property, $field);
            }
            if ($field->shape !== null) {
                $this->validateShape($field->shape, $property);
            }
        }
        if ($declaration?->receiver !== null) {
            $this->validateReceiver($description, $declaration);
        }
        return $compiled;
    }

    private function validateConstructorValue(ReflectionClass $class, ReflectionProperty $property, FieldRule $field): void
    {
        $constructor = $class->getConstructor();
        if (
            !$property->isPublic() || $property->hasHooks() || $property->hasDefaultValue()
            || $constructor === null || !$constructor->isPublic()
            || array_any($constructor->getParameters(), static fn (ReflectionParameter $parameter): bool => $parameter->getName() === $property->getName())
            || !$this->isConstructorValueType($property->getType())
        ) {
            throw new ConfigurationException(new Message('serialization.constructorvalue_requires_a_separate_public_field_of_a_supported', ['value0' => $class->getName(), 'value1' => $property->getName()]));
        }
        for ($shape = $field->shape; $shape !== null; $shape = $shape->item) {
            if (!in_array($shape->kind, ['scalar', 'mixed', 'nullable', 'list'], true)) {
                throw new ConfigurationException(new Message('serialization.constructorvalue_does_not_support_dto_variants_in_the_value'));
            }
        }
    }

    private function isConstructorValueType(?ReflectionType $type): bool
    {
        if ($type instanceof ReflectionUnionType) {
            return array_all($type->getTypes(), fn (ReflectionType $part): bool => $this->isConstructorValueType($part));
        }
        return $type instanceof ReflectionNamedType && (
            in_array($type->getName(), ['int', 'float', 'string', 'bool', 'true', 'false', 'null', 'array'], true)
            || enum_exists($type->getName())
        );
    }

    public function receiverFor(string $class): ?string
    {
        if (array_key_exists($class, $this->receivers)) {
            return $this->receivers[$class];
        }
        if (isset($this->compiled[$class])) {
            return $this->receivers[$class] = $this->compiled[$class]->declaration?->receiver;
        }
        // Runtime-объект запроса может быть opaque или не иметь деклараций DTO.
        $this->assertAvailable($class);
        $description = $this->metadata->forClass($class);
        foreach ($description->properties as $property) {
            if ($property->getAttributes(Extras::class) !== []) {
                return $this->receivers[$class] = $this->compileRoot($class, $description)->declaration?->receiver;
            }
        }
        return $this->receivers[$class] = $this->config?->rules?->rulesFor($class)?->receiver;
    }

    public function hasCapabilities(string $class): bool
    {
        return $this->config !== null || $this->forClass($class)->enhanced;
    }

    private function classOverride(ClassMetadata $description): ?DtoHydrate
    {
        return ($description->attributes(DtoHydrate::class, inherited: true)[0] ?? null)?->newInstance();
    }

    /** @return array{?DtoRules, array<string, string>, bool} */
    private function attributeDeclarations(ClassMetadata $description, ?DtoRules $external): array
    {
        $class = $description->reflection;
        $result = $external;
        $origins = array_fill_keys(array_keys($external->fields ?? []), 'external');
        $found = false;
        foreach ($description->properties as $property) {
            $name = $property->getName();
            $declarations = [];
            foreach ([Extras::class, RequiredInput::class, ForbidExplicitNull::class, ConstructorValue::class, Shape::class] as $attribute) {
                $matches = $property->getAttributes($attribute);
                if (count($matches) > 1) {
                    throw new ConfigurationException(new Message('serialization.attribute_cannot_be_repeated', ['attribute' => $attribute]));
                }
                if ($matches !== []) {
                    $this->property($class, $name);
                    if (isset($external?->fields[$name])) {
                        $this->rejectAttributes($property, self::INPUT_ATTRIBUTES);
                    }
                    try {
                        $declarations[$attribute] = $matches[0]->newInstance();
                    } catch (Throwable $exception) {
                        throw new ConfigurationException(new Message('serialization.invalid_declaration_of', ['attribute' => $attribute]), previous: $exception);
                    }
                }
            }
            if ($declarations === []) {
                continue;
            }
            $found = true;
            $result ??= DtoRules::create();
            if (isset($declarations[Extras::class])) {
                $result = $result->extras($name);
                continue;
            }
            $field = FieldRule::create();
            if (isset($declarations[RequiredInput::class])) {
                $field = $field->required();
            }
            if (isset($declarations[ForbidExplicitNull::class])) {
                $field = $field->forbidExplicitNull();
            }
            if (isset($declarations[ConstructorValue::class])) {
                $field = $field->constructorValue($declarations[ConstructorValue::class]->allowMissing);
            }
            if (isset($declarations[Shape::class])) {
                $this->rejectAttributes($property, [Cast::class, Nested::class]);
                $field = $field->shape((new ShapeCompiler())->compile($declarations[Shape::class]->value));
            }
            $result = $result->field($name, $field);
            $origins[$name] = 'attribute';
        }
        return [$result, $origins, $found];
    }

    private function hasProfile(ClassMetadata $description): bool
    {
        return $description->attributes(DtoHydrate::class, inherited: true) !== []
            || $description->attributes(DtoHydrationProfile::class, inherited: true) !== [];
    }

    private function property(ReflectionClass $class, string $name): ReflectionProperty
    {
        if (!$class->hasProperty($name)) {
            throw new ConfigurationException(new Message('serialization.rule_property_not_found', ['value0' => $class->getName(), 'name' => $name]));
        }
        $property = $class->getProperty($name);
        if ($property->isStatic() || $property->isVirtual()) {
            throw new ConfigurationException(new Message('serialization.rule_requires_a_stored_dto_property', ['value0' => $class->getName(), 'name' => $name]));
        }
        return $property;
    }

    /** @param list<class-string> $attributes */
    private function rejectAttributes(ReflectionProperty $property, array $attributes): void
    {
        foreach ($attributes as $attribute) {
            if ($property->getAttributes($attribute) !== []) {
                throw new ConfigurationException(
                    new Message('serialization.rule_conflicts_with_attribute', ['attribute' => $attribute, 'value1' => $property->getDeclaringClass()->getName(), 'value2' => $property->getName()]),
                );
            }
        }
    }

    private function validateReceiver(ClassMetadata $description, DtoRules $rules): void
    {
        $class = $description->reflection;
        $name = $rules->receiver;
        $property = $this->property($class, $name);
        $type = $property->getType();
        if (!$property->isPublic() || !$type instanceof ReflectionNamedType || $type->getName() !== 'array') {
            throw new ConfigurationException(new Message('serialization.receiver_requires_public_array_or_array', ['value0' => $class->getName(), 'name' => $name]));
        }
        if (isset($rules->fields[$name])) {
            throw new ConfigurationException(new Message('serialization.receiver_cannot_have_a_fieldrule', ['value0' => $class->getName(), 'name' => $name]));
        }
        $this->rejectAttributes($property, [...self::INPUT_ATTRIBUTES, ...self::OUTPUT_ATTRIBUTES]);
        $constructor = $class->getConstructor();
        if (
            $constructor !== null && !array_any(
                $constructor->getParameters(),
                static fn (ReflectionParameter $parameter): bool => $parameter->getName() === $name,
            )
        ) {
            throw new ConfigurationException(new Message('serialization.receiver_must_be_a_constructor_parameter', ['value0' => $class->getName(), 'name' => $name]));
        }
        if (
            $class->implementsInterface(JsonSerializable::class)
            || $class->implementsInterface(DateTimeInterface::class)
            || $class->implementsInterface(Stringable::class)
            || ($class->hasMethod('toArray') && !$class->implementsInterface(DtoInterface::class))
        ) {
            throw new ConfigurationException(new Message('serialization.receiver_is_incompatible_with_a_custom_json_representation', ['value0' => $class->getName()]));
        }
    }

    private function validatePolicy(RulePolicy $policy): void
    {
        foreach ($policy->casts as $type => $spec) {
            $this->validateHandler($spec, HydrationCastInterface::class, $type);
        }
    }

    private function validateHandler(HandlerSpec $spec, string $interface, string $target = 'default'): void
    {
        if (!is_subclass_of($spec->class, $interface)) {
            throw new ConfigurationException(new Message('serialization.invalid_rule_handler_for_is_required', ['value0' => $spec->class, 'target' => $target, 'interface' => $interface]));
        }
        $reflection = new ReflectionClass($spec->class);
        if (!$reflection->isInstantiable()) {
            throw new ConfigurationException(new Message('serialization.rule_handler_is_not_instantiable', ['value0' => $spec->class]));
        }
    }

    private function validateShape(ValueShape $shape, ReflectionProperty $property, bool $listItem = false): void
    {
        if ($shape->kind === 'dto') {
            $this->compileReference($shape->class);
        }
        if ($shape->kind === 'variants') {
            if (!$listItem || $shape->map === []) {
                throw new ConfigurationException(new Message('serialization.variants_requires_a_non_empty_map_and_a_list'));
            }
            if ($shape->mode === NestedDiscriminatorMode::Value && $shape->discriminator === '') {
                throw new ConfigurationException(new Message('serialization.value_discriminator_requires_a_non_empty_path'));
            }
            $type = $property->getType();
            if (
                $shape->unknown === NestedUnknownVariant::KeepRaw
                && $type instanceof ReflectionNamedType
                && is_subclass_of($type->getName(), AbstractTypedCollection::class)
            ) {
                throw new ConfigurationException(new Message('serialization.keepraw_is_incompatible_with_a_typed_dto_collection'));
            }
            foreach ($shape->map as $class) {
                $this->compileReference($class);
            }
        }
        if ($shape->itemCast !== null) {
            $this->validateHandler($shape->itemCast, HydrationCastInterface::class, $property->getDeclaringClass()->getName() . '::$' . $property->getName());
        }
        if ($shape->item !== null) {
            $this->validateShape($shape->item, $property, $shape->kind === 'list' || $listItem);
        }
    }

    private function compileReference(mixed $class): void
    {
        if (!is_string($class)) {
            throw new ConfigurationException(new Message('serialization.variants_map_requires_dto_classes'));
        }
        if ($this->deferNested) {
            if (!class_exists($class) || (new ReflectionClass($class))->isAbstract() || enum_exists($class)) {
                throw new ConfigurationException(new Message('serialization.rule_set_dto_class_not_found', ['class' => $class]));
            }
            return;
        }
        $this->compile($class);
    }
}
