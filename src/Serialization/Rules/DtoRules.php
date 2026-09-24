<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;

final readonly class DtoRules
{
    /** @param array<string, FieldRule> $fields */
    private function __construct(
        public RulePolicy $policy,
        public array $fields = [],
        public ?string $receiver = null,
    ) {
    }

    public static function create(?RulePolicy $policy = null): self
    {
        return new self($policy ?? new RulePolicy());
    }

    public function field(string $property, FieldRule $rule): self
    {
        if (array_key_exists($property, $this->fields)) {
            throw new ConfigurationException(new Message('serialization.field_rule_is_already_defined', ['property' => $property]));
        }
        return new self($this->policy, $this->fields + [$property => $rule], $this->receiver);
    }

    public function extras(string $property): self
    {
        if ($this->receiver !== null) {
            throw new ConfigurationException(new Message('serialization.dto_receiver_is_already_defined'));
        }
        return new self($this->policy, $this->fields, $property);
    }
}
