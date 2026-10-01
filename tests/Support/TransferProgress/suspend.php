<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support\TransferProgress;

use ApiSutra\Tests\Support\LocalFileServer;
use ApiSutra\Transport\GuzzleAsyncDriver;
use Fiber;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use Revolt\EventLoop;
use Throwable;
use RuntimeException;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once __DIR__ . '/common.php';

foreach ([false, true] as $async) {
    $server = new LocalFileServer();
    $calls = 0;
    $suspended = null;
    $resumed = false;
    $callback = static function () use (&$calls, &$suspended): void {
        if (++$calls === 1) {
            $suspended = Fiber::getCurrent();
            Fiber::suspend('fixture unsupported suspension');
        }
    };
    try {
        if ($async) {
            $driver = new GuzzleAsyncDriver();
            $promise = $driver->send(new Request('GET', $server->url . '/download?size=3000000'), ['progress' => $callback]);
            $resumeTimer = EventLoop::repeat(0.002, static function (string $id) use (&$suspended, &$resumed): void {
                if ($suspended?->isSuspended()) {
                    EventLoop::cancel($id);
                    $suspended->resume();
                    $resumed = true;
                }
            });
            $watchdog = EventLoop::delay(2, static fn () => throw new RuntimeException('suspend probe timeout'));
            try {
                $response = $promise->wait();
            } finally {
                EventLoop::cancel($resumeTimer);
                EventLoop::cancel($watchdog);
            }
        } else {
            $client = new Client(['handler' => HandlerStack::create(new CurlHandler())]);
            $fiber = new Fiber(static fn () => $client->send(new Request('GET', $server->url . '/download?size=3000000'), ['progress' => $callback]));
            $fiber->start();
            if ($fiber->isSuspended()) {
                $fiber->resume();
                $resumed = true;
            }
            $response = $fiber->getReturn();
        }
        check($resumed && $response->getStatusCode() === 200, 'явное suspend/resume в этой среде');
        report('unsupported-suspend', ['mode' => $async ? 'async' : 'sync', 'resumed' => true, 'status' => 200, 'callbacks' => $calls]);
    } catch (Throwable $error) {
        report('unsupported-suspend', ['mode' => $async ? 'async' : 'sync', 'error' => $error::class, 'message' => $error->getMessage()]);
        throw $error;
    } finally {
        $server->close();
    }
}
check(EventLoop::getIdentifiers() === [], 'suspend probe не оставил watchers');
