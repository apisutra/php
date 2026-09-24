<?php

declare(strict_types=1);

use ApiSutra\Execution\Async\AsyncRuntime;
use ApiSutra\Tests\Support\LocalTimeoutServer;
use ApiSutra\Transport\GuzzleAsyncDriver;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Request;
use Revolt\EventLoop;

it('overlaps real HTTP and resumes nested work after cURL callbacks without leaving a ticker', function (): void {
    $server = new LocalTimeoutServer();
    $runtime = new AsyncRuntime();
    $driver = new GuzzleAsyncDriver(promises: $runtime->promises);
    $before = EventLoop::getIdentifiers();
    $started = hrtime(true);
    $calls = [];
    for ($i = 0; $i < 3; $i++) {
        $calls[] = $runtime->start(function () use ($driver, $runtime, $server): array {
            $response = $runtime->promises->await($driver->send(new Request('GET', $server->httpUrl . '/?ms=150&observe=1'), []), true);
            // This continuation may itself suspend and send another HTTP request.
            $runtime->sleep(1);
            $runtime->promises->await($driver->send(new Request('GET', $server->httpUrl . '/?ms=1'), []), true);
            return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        });
    }
    $results = Utils::all($calls)->wait();
    expect(max(array_column($results, 'peak')))->toBe(3)
        ->and((hrtime(true) - $started) / 1e6)->toBeLessThan(450);
    EventLoop::run();
    expect(EventLoop::getIdentifiers())->toBe($before);
    $server->close();
});
