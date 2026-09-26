<?php

declare(strict_types=1);

namespace ApiSutra\Config;

use ApiSutra\Contracts\Interfaces\Serialization\DtoHydratorInterface;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\RulePolicy;

final readonly class HydrationConfig
{
    public function __construct(
        public ?RulePolicy $policy = null,
        public ?HydrationRules $rules = null,
        public ?DtoHydratorInterface $hydrator = null,
        public bool $jsonShapeValidation = true,
    ) {
    }
}
