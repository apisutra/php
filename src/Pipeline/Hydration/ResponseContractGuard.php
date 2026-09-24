<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Hydration;

use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\CompositeRequestInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Serialization\DtoHydratorInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\ResponseTypeMismatchException;

/** @internal Проверяет декларацию и конечное значение без создания DTO или его зависимостей. */
final class ResponseContractGuard
{
    public static function validate(RequestInterface $request, ClientConfig $config): void
    {
        $returns = $request instanceof AbstractRequest ? $request->getReturnsAttribute() : null;
        foreach ([$returns?->mismatchMessage, $config->resultExceptions?->mismatchMessage] as $message) {
            if ($message !== null && trim($message) === '') {
                throw new ConfigurationException(new Message('result.mismatch_message_must_not_be_empty'));
            }
        }
        self::validateDeclaration($returns, $request instanceof AbstractRequest ? $request->getResponseType() : null);
    }

    public static function validateDeclaration(?Returns $returns, ?string $responseType): void
    {
        // Динамические accessor-ы могут различаться у экземпляров одного класса запроса.
        $classes = array_filter(
            [$returns?->response, $returns?->type, $responseType],
            static fn (?string $class): bool => $class !== null,
        );
        foreach (array_unique($classes) as $class) {
            self::validateDtoClass($class);
        }
        $hydrator = $returns?->hydrator;
        if (is_string($hydrator) && !is_subclass_of($hydrator, DtoHydratorInterface::class)) {
            throw new ConfigurationException(new Message('serialization.invalid_dto_hydrator', ['class' => $hydrator]));
        }
    }

    private static function validateDtoClass(string $class): void
    {
        if (!class_exists($class)) {
            throw new ConfigurationException(new Message('result.dto_class_is_unavailable', ['expected' => $class]));
        }
    }

    public static function check(RequestInterface $request, ClientConfig $config, mixed $data): void
    {
        if (!$request instanceof AbstractRequest || $request->hasDownload()) {
            return;
        }
        $returns = $request->getReturnsAttribute();
        $expected = $request->getResponseType();
        if (!$request instanceof CompositeRequestInterface && $returns?->unwrap !== null) {
            $expected = $returns->type ?? $expected;
        }
        if ($expected === null) {
            return;
        }
        self::validateDtoClass($expected);
        if ($data instanceof $expected) {
            return;
        }

        throw self::mismatch($request, $config, $expected, get_debug_type($data));
    }

    public static function mismatch(RequestInterface $request, ClientConfig $config, string $expected, string $actual): ResponseTypeMismatchException
    {
        $returns = $request instanceof AbstractRequest ? $request->getReturnsAttribute() : null;
        $message = $returns->mismatchMessage
            ?? $config->resultExceptions->mismatchMessage
            ?? new Message('result.response_type_mismatch', ['request' => $request::class, 'expected' => $expected, 'actual' => $actual]);
        return new ResponseTypeMismatchException($message, $expected, $actual);
    }
}
