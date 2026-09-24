<?php

declare(strict_types=1);

it('исполняет опубликованный пример фабрики, вложенного DTO и ошибки потока', function (): void {
    $result = require dirname(__DIR__, 3) . '/docs/example/dto-hydrator/run.php';
    expect($result['user'])->toMatchArray(['user_id' => 7, 'name' => 'Ada', 'address' => ['city' => 'London']])
        ->and($result['seen'])->toBe(['Grace'])->and($result['failed_page'])->toBeTrue()->and($result['http_calls'])->toBe(3);
});
