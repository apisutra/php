<?php

declare(strict_types=1);

namespace ApiSutra\Testing;

use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Result\ExecutionResult;

final class RequestContractTestHelper
{
    public static function isRequestContractViolation(ExecutionResult $result): bool
    {
        return $result->errors->first()?->code === ErrorCode::RequestContractViolation;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function context(ExecutionResult $result): ?array
    {
        if (!self::isRequestContractViolation($result)) {
            return null;
        }

        $context = $result->errors->first()?->context;

        return is_array($context) ? $context : null;
    }

    /**
     * @return array<int, string>
     */
    public static function violationCodes(ExecutionResult $result): array
    {
        $context = self::context($result);
        $violations = is_array($context['violations'] ?? null)
            ? $context['violations']
            : [];

        $codes = [];
        foreach ($violations as $violation) {
            if (is_array($violation) && is_string($violation['code'] ?? null)) {
                $codes[] = $violation['code'];
            }
        }

        return $codes;
    }

    public static function hasViolationCode(ExecutionResult $result, string $code): bool
    {
        return in_array($code, self::violationCodes($result), true);
    }
}
