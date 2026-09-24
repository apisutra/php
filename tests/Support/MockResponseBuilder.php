<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use ApiSutra\Testing\MockResponse;
use ApiSutra\Testing\MockSequence;

final class MockResponseBuilder
{
    public static function serverErrorThenSuccess(array $success = []): MockSequence
    {
        return MockResponse::sequence([
            MockResponse::serverError(),
            MockResponse::success($success),
        ]);
    }

    public static function unauthorizedThenSuccess(array $success = []): MockSequence
    {
        return MockResponse::sequence([
            new MockResponse(['message' => 'Unauthorized'], 401, ['Content-Type' => 'application/json']),
            MockResponse::success($success),
        ]);
    }

    public static function rateLimitThenSuccess(int $retryAfter = 60, array $success = []): MockSequence
    {
        return MockResponse::sequence([
            MockResponse::rateLimited($retryAfter),
            MockResponse::success($success),
        ]);
    }

    public static function sequence(MockResponse ...$responses): MockSequence
    {
        return MockResponse::sequence($responses);
    }
}
