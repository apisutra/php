<?php

declare(strict_types=1);

namespace ApiSutra\Execution\Pool;

use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Execution\ExecutionErrorFactory;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Localization\Message;
use ApiSutra\Request\RequestExecution;
use Countable;
use Generator;
use TypeError;

/** @internal Проверяет и подготавливает только запрошенный элемент источника. */
final readonly class PoolRequestSource
{
    public function __construct(
        private ClientInterface $client,
        private iterable $requests,
        private RequestRole $role,
    ) {
    }

    public function hasKnownCount(): bool
    {
        return is_array($this->requests) || $this->requests instanceof Countable;
    }

    public function count(): int
    {
        if (is_array($this->requests) || $this->requests instanceof Countable) {
            return count($this->requests);
        }
        throw new ConfigurationException(
            new Message('execution.pool_consumption_resolver_requires_count'),
            localization: $this->client->getConfig()->localization,
        );
    }

    /** @return Generator<int, RequestInterface> */
    public function iterate(): Generator
    {
        $errors = new ExecutionErrorFactory($this->client->getConfig()->localization);
        $index = 0;
        foreach ($this->requests as $request) {
            if (!$request instanceof RequestInterface) {
                throw $errors->unsupportedItemException('pool', $index, $request);
            }
            yield $index++ => $this->prepare($request);
        }
    }

    private function prepare(RequestInterface $request): RequestInterface
    {
        if ($request instanceof RequestExecutionInterface) {
            $inner = $request->getRequest();
            if (!$inner instanceof AbstractRequest) {
                // Сохраняем требование конструктора RequestExecution при сторонней обёртке.
                throw new TypeError(
                    RequestExecution::class . ' requires ' . AbstractRequest::class . ', ' . $inner::class . ' given',
                );
            }
            $inner->setClient($this->client);
            return new RequestExecution(
                request: $inner,
                options: $request->getOptions()->withRole($this->role),
                paginationOptions: $request->getPaginationOptions(),
            );
        }
        if ($request instanceof AbstractRequest) {
            $request->setClient($this->client);
            return $request->withRole($this->role);
        }
        return $request;
    }
}
