<?php

declare(strict_types=1);

namespace ApiSutra\Testing;

use ApiSutra\Transport\MockTransport;

final class MockClient
{
    private static ?MockTransport $globalTransport = null;

    public static function global(array $responses = []): MockTransport
    {
        if (self::$globalTransport === null) {
            self::$globalTransport = new MockTransport();
        }

        if ($responses !== []) {
            self::$globalTransport->fake($responses);
        }

        return self::$globalTransport;
    }

    public static function destroyGlobal(): void
    {
        self::$globalTransport = null;
    }

    public static function hasGlobal(): bool
    {
        return self::$globalTransport !== null;
    }
}
