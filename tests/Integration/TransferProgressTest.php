<?php

declare(strict_types=1);

it('сохраняет контракты прогресса при реальном sync/async HTTP, retry, auth, pagination и отмене', function (): void {
    ob_start();
    try {
        require __DIR__ . '/../Support/TransferProgress/contracts.php';
        $output = ob_get_contents();
    } finally {
        ob_end_clean();
    }
    expect($output)->toContain('"status":"passed"');
});

it('выполняет опубликованный пример без тестовых DTO и подмены транспорта', function (): void {
    ob_start();
    try {
        $summary = require __DIR__ . '/../../docs/example/transfer-progress/run.php';
    } finally {
        ob_end_clean();
    }
    expect($summary)->toBe(['uploadBytes' => 262144, 'retryAttempt' => 2, 'downloadBytes' => 262144, 'unknownTotal' => null]);
});
