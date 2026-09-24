<?php

declare(strict_types=1);

namespace ApiSutra\VO\Validation;

use ApiSutra\Localization\Message;
use ApiSutra\Attributes\DataTransfer\Label;
use ApiSutra\Attributes\DataTransfer\Validate;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\ValidationResult as ValidationResultContract;
use ApiSutra\Contracts\Interfaces\Validation\ValidatorInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Validation\ValidationException;
use ApiSutra\Support\ContainerProviderRegistry;
use ApiSutra\VO\Errors\ValidationError;
use Illuminate\Contracts\Validation\Factory;
use Override;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

final class Validator implements ValidatorInterface
{
    private static ?Factory $factory = null;

    public static function useFactory(Factory $factory): void
    {
        self::$factory = $factory;
    }

    public static function resetFactory(): void
    {
        self::$factory = null;
    }

    #[Override]
    public static function check(object $value, ?ContainerProviderInterface $provider = null): ValidationResultContract
    {
        // Ручная проверка не должна запускать auto-resolve клиента.
        if ($provider === null && $value instanceof AbstractRequest && $value->hasClient()) {
            $provider = $value->getClient()->getConfig()->containerProvider;
        }

        return self::checkWithProvider($value, $provider);
    }

    /** @internal Использует клиента текущего выполнения независимо от привязки объекта запроса. */
    public static function checkForClient(object $value, ClientConfig $config): ValidationResultContract
    {
        return self::checkWithProvider($value, $config->containerProvider);
    }

    private static function checkWithProvider(object $value, ?ContainerProviderInterface $provider): ValidationResultContract
    {
        $reflection = new ReflectionClass($value);
        $rules = [];
        $messages = [];
        $labels = [];
        $inputs = [];

        foreach ($reflection->getProperties() as $property) {
            $validate = self::getAttribute($property, Validate::class);
            if ($validate === null) {
                continue;
            }

            $name = $property->getName();
            $rules[$name] = $validate->rules;
            $inputs[$name] = self::readProperty($value, $property);

            if ($validate->message !== null) {
                foreach (explode('|', $validate->rules) as $rule) {
                    $ruleName = explode(':', $rule, 2)[0];
                    $messages["{$name}.{$ruleName}"] = $validate->message;
                }
            }

            $label = self::getAttribute($property, Label::class);
            if ($label !== null) {
                $labels[$name] = $label->name;
            }
        }

        if ($rules === []) {
            return new ValidationResult(true, []);
        }

        $messages = array_merge(self::resolveValidationMessages($reflection), $messages);

        $factory = self::resolveFactory($provider);

        $validator = $factory->make($inputs, $rules, $messages, $labels);
        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->messages() as $field => $fieldErrors) {
                foreach ($fieldErrors as $message) {
                    $errors[] = new ValidationError(
                        field: $field,
                        rule: self::extractRule($rules[$field] ?? ''),
                        message: $message,
                        input: $inputs[$field] ?? null,
                    );
                }
            }

            return new ValidationResult(false, $errors);
        }

        return new ValidationResult(true, []);
    }

    public static function validateOrThrow(object $value, ?ContainerProviderInterface $provider = null): void
    {
        $result = self::check($value, $provider);
        if ($result->failed()) {
            throw new ValidationException($result->errors());
        }
    }

    private static function resolveFactory(?ContainerProviderInterface $provider): Factory
    {
        if ($provider === null && self::$factory !== null) {
            return self::$factory;
        }

        $instruction = $provider !== null
            ? new Message('vo.configure_validatorfactory_in_the_selected_containerprovider')
            : new Message('vo.connect_illuminate_validation_through_a_container_provider_or_validator');
        try {
            $factory = ContainerProviderRegistry::resolve($provider)->validatorFactory();
        } catch (Throwable $exception) {
            throw new ConfigurationException(
                new Message('vo.failed_to_resolve_the_validator_for_validate', ['instruction' => $instruction]),
                previous: $exception,
            );
        }

        if (!$factory instanceof Factory) {
            throw new ConfigurationException(new Message('vo.a_compatible_validation_factory_is_unavailable_for_validate', ['instruction' => $instruction]));
        }

        return $factory;
    }

    /**
     * @return array<string, string>
     */
    private static function resolveValidationMessages(ReflectionClass $reflection): array
    {
        if (!$reflection->hasMethod('validationMessages')) {
            return [];
        }

        $method = $reflection->getMethod('validationMessages');
        if (!$method->isStatic()) {
            return [];
        }

        $result = $method->invoke(null);
        if (!is_array($result)) {
            return [];
        }

        return $result;
    }

    private static function extractRule(string $rules): string
    {
        $first = explode('|', $rules)[0] ?? '';
        return explode(':', $first, 2)[0] ?: 'unknown';
    }

    private static function readProperty(object $value, ReflectionProperty $property): mixed
    {
        if ($property->isPublic()) {
            return $value->{$property->getName()} ?? null;
        }

        return $property->getValue($value);
    }

    private static function getAttribute(ReflectionProperty $property, string $class): ?object
    {
        $attributes = $property->getAttributes($class);
        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }
}
