<?php

declare(strict_types=1);

it('исполняет опубликованный пример типизированных async-результатов без сети', function (): void {
    $result = require dirname(__DIR__, 3) . '/docs/example/async-results/run.php';
    expect($result)->toBe([
        'handle' => true,
        'same_handle' => true,
        'wait_false' => null,
        'pool_total' => 2,
        'consume_total' => 2,
        'batch_total' => 2,
        'chain_total' => 2,
        'fulfilled_failed' => true,
        'otherwise_on_failed' => false,
        'recovered' => 'fallback',
        'throwing_recovery' => 'fallback',
    ]);
});
