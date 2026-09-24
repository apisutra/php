<?php

declare(strict_types=1);

it('исполняет опубликованный OAuth пример без сети', function (): void {
    $result = require dirname(__DIR__, 4) . '/docs/example/oauth2/run.php';
    expect($result)->toBe([
        'resource' => ['reports' => []],
        'cc_http_calls' => 3,
        'code_http_calls' => 4,
        'rotation_saved' => true,
        'callback_failure' => 'oauth2_invalid_callback',
    ]);
});
