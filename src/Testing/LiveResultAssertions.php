<?php

declare(strict_types=1);

namespace ApiSutra\Testing;

use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Localization\Message;
use ApiSutra\Result\ResolvedResultInterface;
use ApiSutra\Exceptions\Core\RuntimeException;

/**
 * Assertion-хелперы для live-тестов.
 *
 * Проверка успеха и типа данных в ResolvedResult.
 * Framework-agnostic: бросает RuntimeException при провале.
 */
final readonly class LiveResultAssertions
{
    /**
     * Бросает RuntimeException, если результат не успешен.
     */
    public static function assertSuccess(ResolvedResultInterface $resolved, string $operation, ?LocalizationConfig $localization = null): void
    {
        if (!$resolved->isSuccess()) {
            throw new RuntimeException(
                new Message('testing.live_test_s_failed_status_s_code_s_message', ['operation' => $operation, 'value1' => (string) ($resolved->errorStatus() ?? 'null'), 'value2' => (string) ($resolved->errorCode() ?? 'null'), 'value3' => (string) ($resolved->errorMessage() ?? 'null')]),
                localization: $localization,
            );
        }
    }

    /**
     * Проверяет успех и что data() — экземпляр ожидаемого класса.
     *
     * @template T of object
     * @param class-string<T> $expectedClass
     */
    public static function assertDataInstanceOf(
        ResolvedResultInterface $resolved,
        string $expectedClass,
        string $operation,
        ?LocalizationConfig $localization = null,
    ): void {
        self::assertSuccess($resolved, $operation, $localization);

        $data = $resolved->data();
        if ($data instanceof $expectedClass) {
            return;
        }

        throw new RuntimeException(
            new Message('testing.live_test_s_returned_an_unexpected_data_type_expected', ['operation' => $operation, 'expectedClass' => $expectedClass, 'value2' => get_debug_type($data)]),
            localization: $localization,
        );
    }
}
