<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;

final readonly class HydrationRules
{
    /** @param array<class-string, DtoRules> $definitions */
    private function __construct(private RulePolicy $policy, private array $definitions = [])
    {
    }

    public static function create(?RulePolicy $defaults = null): self
    {
        return new self($defaults ?? new RulePolicy());
    }

    public function withDto(string $class, DtoRules $rules): self
    {
        if (array_key_exists($class, $this->definitions)) {
            throw new ConfigurationException(new Message('serialization.class_rules_are_already_defined', ['class' => $class]));
        }
        return new self($this->policy, $this->definitions + [$class => $rules]);
    }

    public function defaults(): RulePolicy
    {
        return $this->policy;
    }

    public function rulesFor(string $class): ?DtoRules
    {
        return $this->definitions[$class] ?? null;
    }

    /**
     * @internal
     * @return array<class-string, DtoRules>
     */
    public function definitions(): array
    {
        return $this->definitions;
    }
}
