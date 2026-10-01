<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support\TransferProgress;

use ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Transport\GuzzleHttpClient;
use ApiSutra\Transport\HttpTransport;
use Closure;
use GuzzleHttp\Psr7\HttpFactory;
use RuntimeException;

function check(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('Шлюз: ' . $label);
    }
}

/** @param array<string, mixed> $fields */
function report(string $label, array $fields = []): void
{
    echo json_encode(['case' => $label] + $fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n";
}

/** @param array<string, mixed> $defaults */
function transport(array $defaults = []): HttpTransport
{
    $factory = new HttpFactory();
    return new HttpTransport(new GuzzleHttpClient($defaults), $factory, $factory);
}

function observe(AbstractRequest $request, Closure $callback): RequestExecutionInterface
{
    return $request->withOptions(RequestOptions::empty()->withTransferProgress($callback));
}
