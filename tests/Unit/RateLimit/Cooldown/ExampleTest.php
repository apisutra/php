<?php

declare(strict_types=1);

it('выполняет опубликованный пример cooldown без сети', function (): void {
    $result = require dirname(__DIR__, 4) . '/docs/example/cooldown/run.php';
    expect($result)->toBe(['denial' => 'server_cooldown_active', 'successful' => 3, 'waits_ms' => [30_000]]);
});

it('выполняет пример общего локального backend без сети', function (): void {
    $result = require dirname(__DIR__, 4) . '/docs/example/cooldown/shared.php';
    expect($result)->toBe(['denial' => 'server_cooldown_active', 'response' => null, 'independent' => true, 'ready' => true, 'waits_ms' => [30_000]]);
});
