<?php

declare(strict_types=1);

namespace Example\HydrationRules;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use ApiSutra\Serialization\Rules\ValueShape;

require __DIR__ . '/../sdk/bootstrap.php';

$rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
    ->withDto(EntryDto::class, DtoRules::create()
        ->field('id', FieldRule::create()->from('record_id', 'id'))
        ->extras('_extra'))
    ->withDto(ReportDto::class, DtoRules::create()
        ->field('owner', FieldRule::create()->shape(ValueShape::dto(EntryDto::class)))
        ->field('items', FieldRule::create()->from('rows')->shape(
            ValueShape::list(ValueShape::dto(EntryDto::class), each: 'value'),
        ))
        ->field('ids', FieldRule::create()->shape(ValueShape::list(ValueShape::int())))
        ->field('count', FieldRule::create()->forbidExplicitNull())
        ->extras('_extra'));

$config = new ClientConfig(baseUrl: 'https://api.example', hydration: new HydrationConfig(rules: $rules));
$source = [
    'owner' => ['record_id' => 7, 'future' => false],
    'rows' => [['value' => ['record_id' => 8], 'meta' => ['revision' => 2]]],
    'ids' => [1, 2],
    'next_feature' => null,
];
$dto = Hydrator::forRules($rules)->hydrate($source, ReportDto::class);
// owner.id = 7, owner._extra = ['future' => false], items[0].id = 8, count = null.
// _extra содержит next_feature и остаток rows с meta (форма описана ниже).

echo json_encode($dto, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
