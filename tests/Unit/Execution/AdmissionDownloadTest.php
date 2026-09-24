<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RateLimitConfig;
use ApiSutra\Exceptions\ControlFlow\AdmissionRefused;
use ApiSutra\Execution\Admission\AdmissionScope;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Files\ControlledStream;
use ApiSutra\Tests\Stubs\ProviderB\Requests\ProviderBDownloadRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Stubs\Tracing\ObserverProvider;
use ApiSutra\Tests\Stubs\Tracing\RecordingObserver;
use ApiSutra\Timing\CooperativeSleeper;
use ApiSutra\Transport\MockTransport;
use GuzzleHttp\Psr7\Utils;
use Revolt\EventLoop;

it('после отказа дренирует download, сохраняет пользовательский sink и закрывает lifecycle', function (): void {
    $folder = sys_get_temp_dir() . '/apisutra-admission-' . bin2hex(random_bytes(5));
    mkdir($folder);
    $sink = new ControlledStream(Utils::streamFor(''));
    $observer = new RecordingObserver();
    $transport = new MockTransport();
    $transport->fake(['*' => static function (): MockResponse {
        (new CooperativeSleeper())->sleepMs(5);
        return MockResponse::make('payload');
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://example.test', rateLimit: new RateLimitConfig(limit: 1),
        containerProvider: new ObserverProvider($observer)), $transport);
    $requests = [
        (new ProviderBDownloadRequest('first'))->withDownloadTo($sink),
        (new ProviderBDownloadRequest('second'))->withDownloadTo($folder . '/refused'),
    ];
    $delivered = 0;
    $pool = $client->pool($requests, 2)->withResponseHandler(static function ($result) use (&$delivered): void {
        $delivered++;
        expect($result->data->content())->toBe('payload');
        $result->data->close();
    });
    $before = EventLoop::getIdentifiers();
    try {
        expect(fn () => new AdmissionScope()->run(static fn () => $pool->consume()))->toThrow(AdmissionRefused::class);
        expect($delivered)->toBe(1)->and($sink->closed)->toBeFalse()->and((string) $sink)->toBe('payload')
            ->and(glob($folder . '/{*,.*}', GLOB_BRACE))->toBe([$folder . '/.', $folder . '/..'])
            ->and($observer->active)->toBe(0)->and($observer->snapshots)->toHaveCount(2)
            ->and(EventLoop::getIdentifiers())->toBe($before);
        expect(array_unique(array_column(array_column($observer->snapshots, 'data'), 'executionId')))->toHaveCount(2);
    } finally {
        $sink->close();
        foreach (glob($folder . '/{*,.*}', GLOB_BRACE) ?: [] as $path) { if (is_file($path)) { unlink($path); } }
        rmdir($folder);
    }
});
