<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use ApiSutra\Serialization\Rules\ValueShape;
use ApiSutra\Tests\Stubs\HydrationRules\OwnerDto;
use ApiSutra\Tests\Stubs\HydrationRules\RecordDto;
use ApiSutra\Tests\Stubs\HydrationRules\ReportDto;
use LogicException;

final readonly class HydrationRulesFixture
{
    public static function rules(): HydrationRules
    {
        return HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
            ->withDto(ReportDto::class, DtoRules::create()
                ->field('id', FieldRule::create()->from('record_id', 'legacy_id'))
                ->field('owner', FieldRule::create()->from('profile')->shape(ValueShape::dto(OwnerDto::class)))
                ->field('items', FieldRule::create()->from('rows')->required()
                    ->shape(ValueShape::list(ValueShape::dto(RecordDto::class), each: 'value')))
                ->field('ids', FieldRule::create()->shape(ValueShape::list(ValueShape::int())))
                ->field('count', FieldRule::create()->forbidExplicitNull())
                ->extras('extra'))
            ->withDto(OwnerDto::class, DtoRules::create()->field('id', FieldRule::create()->from('user_id'))->extras('extra'))
            ->withDto(RecordDto::class, DtoRules::create()->field('id', FieldRule::create()->from('record_id')));
    }

    /** @return array<string, mixed> */
    public static function payload(): array
    {
        return [
            'record_id' => 7, 'legacy_id' => 99,
            'profile' => ['user_id' => 11, 'future' => false],
            'rows' => [['value' => ['record_id' => 12], 'meta' => ['future' => 0]]],
            'ids' => [1, 2], 'future' => null,
        ];
    }

    public static function error(callable $operation): HydrationException
    {
        try {
            $operation();
        } catch (HydrationException $exception) {
            return $exception;
        }
        throw new LogicException('Ожидалась ошибка гидратации');
    }
}
