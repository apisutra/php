<?php

declare(strict_types=1);

namespace ApiSutra\Transport;

use ApiSutra\VO\Http\TransportOptions;
use ApiSutra\Execution\Async\GuzzlePromiseBridge;
use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Throwable;

/** @internal Опрос существует только пока есть HTTP; не владеет loop приложения. */
final class GuzzleAsyncDriver
{
    private readonly CurlMultiHandler $multi;
    private readonly Client $client;
    /** @var array<int, array{source: ?PromiseInterface, result: Promise, request: RequestInterface, options: array<string, mixed>, limits: ?TransportOptions, body: ?CallbackStream, sink: ?CallbackStream}> */
    private array $active = [];
    private int $nextId = 0;
    /** @var Closure(): void|null */
    private ?Closure $cancelTick = null;

    /** @param array<string, mixed> $config */
    public function __construct(
        array $config = [],
        bool $exactTarget = false,
        private readonly GuzzlePromiseBridge $promises = new GuzzlePromiseBridge(),
    ) {
        $this->multi = new CurlMultiHandler(['select_timeout' => 0] + ($exactTarget ? ['handle_factory' => new ExactTargetCurlFactory()] : []));
        $this->client = new Client(['handler' => HandlerStack::create($this->multi)] + $config);
    }

    /** @param array<string, mixed> $options */
    public function send(RequestInterface $request, array $options, ?TransportOptions $limits = null): PromiseInterface
    {
        $id = $this->nextId++;
        $cancel = function () use ($id): void {
            try {
                ($this->active[$id]['source'] ?? null)?->cancel();
            } finally {
                $this->remove($id);
            }
        };
        $result = new Promise(null, $cancel);
        $this->active[$id] = ['source' => null, 'result' => $result, 'request' => $request, 'options' => $options, 'limits' => $limits, 'body' => null, 'sink' => null];
        $this->schedule();
        return $this->promises->wrap($result, abandon: $cancel);
    }

    private function startPending(): void
    {
        foreach ($this->active as $id => $entry) {
            try {
                $entry['limits']?->budget?->check('http');
                if ($entry['source'] !== null) {
                    continue;
                }
                $options = $entry['options'];
                if ($entry['limits'] !== null) {
                    $effective = $entry['limits']->effective();
                    $options['timeout'] = $effective->timeoutMs / 1000;
                    $options['connect_timeout'] = $effective->connectTimeoutMs / 1000;
                }
                $body = new CallbackStream($entry['request']->getBody());
                $this->active[$id]['body'] = $body;
                if (($options['sink'] ?? null) instanceof StreamInterface) {
                    $options['sink'] = $this->active[$id]['sink'] = new CallbackStream($options['sink']);
                }
                $source = $this->client->sendAsync($entry['request']->withBody($body), $options);
                $this->active[$id]['source'] = $source;
                $source->then(
                    fn (mixed $value) => $this->settle($id, $value, true),
                    fn (mixed $reason) => $this->settle($id, $reason, false),
                );
            } catch (Throwable $error) {
                $entry['source']?->cancel();
                $this->settle($id, $error, false);
            }
        }
    }

    private function settle(int $id, mixed $value, bool $success): void
    {
        $entry = $this->active[$id] ?? null;
        $this->remove($id);
        if ($entry !== null && $entry['result']->getState() === PromiseInterface::PENDING) {
            $failure = $entry['body']->failure ?? $entry['sink']->failure ?? null;
            if ($failure !== null) {
                $value = $failure;
                $success = false;
            } elseif ($success && $value instanceof ResponseInterface && $entry['sink'] !== null) {
                $value = $value->withBody($entry['options']['sink']);
            }
            $success ? $entry['result']->resolve($value) : $entry['result']->reject($value);
        }
        $this->promises->pump();
    }

    private function schedule(): void
    {
        if ($this->cancelTick !== null || $this->active === []) {
            return;
        }
        $this->cancelTick = $this->promises->scheduler->delay(1, function (): void {
            $this->cancelTick = null;
            try {
                $this->startPending();
                if ($this->active !== []) {
                    $this->multi->tick();
                    foreach ($this->active as $id => $entry) {
                        $failure = $entry['body']->failure ?? $entry['sink']->failure ?? null;
                        if ($failure !== null) {
                            $entry['source']?->cancel();
                            $this->settle($id, $failure, false);
                        }
                    }
                }
            } catch (Throwable $error) {
                foreach ($this->active as $id => $entry) {
                    $entry['source']?->cancel();
                    if ($entry['result']->getState() === PromiseInterface::PENDING) {
                        $entry['result']->reject($error);
                    }
                    $this->remove($id);
                }
            }
            $this->promises->pump();
            $this->schedule();
        });
    }

    private function remove(int $id): void
    {
        unset($this->active[$id]);
        if ($this->active === [] && $this->cancelTick !== null) {
            ($this->cancelTick)();
            $this->cancelTick = null;
        }
    }
}
