<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Context;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;

/** @internal Общий протокол жизни одного вызова обработчика. */
trait InvocationLifetime
{
    private bool $active = true;

    /** @var array<class-string, object> */
    private array $extensions;

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return T|null
     */
    public function extension(string $type): ?object
    {
        $this->assertActive();
        /** @var T|null */
        return $this->extensions[$type] ?? null;
    }

    private function assertActive(): void
    {
        if (!$this->active) {
            throw new ConfigurationException(new Message('serialization.conversion_context_is_closed_after_the_handler_call'));
        }
    }

    private function closeLifetime(): void
    {
        $this->active = false;
        $this->extensions = [];
    }
}
