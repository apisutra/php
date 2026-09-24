<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use ApiSutra\Tests\Stubs\ConstructorOwned\ArrayDto;

final readonly class ConstructorOwnedFixture
{
    public static function rules(string $class = ArrayDto::class, ?FieldRule $field = null): HydrationRules
    {
        return HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
            ->withDto($class, DtoRules::create()->field('value', ($field ?? FieldRule::create())->constructorValue()));
    }

    public static function deep(int $depth): array
    {
        $value = [];
        for ($i = 1; $i < $depth; $i++) {
            $value = [$value];
        }
        return $value;
    }
}
