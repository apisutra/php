<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Execution;

use ApiSutra\Contracts\Interfaces\Execution\ClientExecutorInterface;
use ApiSutra\Core\AbstractClient;

final class ExecutorClient extends AbstractClient
{
    public ?ClientExecutorInterface $executorOverride = null;

    public function execution(): ClientExecutorInterface
    {
        return $this->executorOverride ?? parent::execution();
    }
}
