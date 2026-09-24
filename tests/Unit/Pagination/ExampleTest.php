<?php

declare(strict_types=1);

it('выполняет опубликованный пример пагинации без сети', function (): void {
    $result = require dirname(__DIR__, 3) . '/docs/example/pagination/run.php';
    expect($result)->toBe(['range' => [2, 3, 4], 'all' => [1, 2, 3, 4, 5, 6], 'pages' => 6]);
});
