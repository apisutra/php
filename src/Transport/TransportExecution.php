<?php

declare(strict_types=1);

namespace ApiSutra\Transport;

use ApiSutra\Contracts\Interfaces\Core\ConcurrentTransportInterface;
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Execution\Async\AsyncTask;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Localization\Message;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;

/** @internal Общая транспортная граница обычной отправки и повторов. */
final class TransportExecution
{
    public static function assertConcurrent(TransportInterface $transport): void
    {
        if (!$transport instanceof ConcurrentTransportInterface) {
            throw new ConfigurationException(new Message('transport.concurrent_execution_unsupported', ['adapter' => $transport::class]));
        }
        $transport->assertSupportsConcurrency();
    }

    public static function send(TransportInterface $transport, PreparedRequest $request): ProviderResponse
    {
        $task = AsyncTask::current();
        if ($task === null) {
            return $transport->send($request);
        }
        $task->check();
        self::assertConcurrent($transport);
        return $task->runtime->promises->await($transport->sendAsync($request), true);
    }
}
