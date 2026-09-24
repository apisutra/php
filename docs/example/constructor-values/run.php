<?php

declare(strict_types=1);

use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use Example\ConstructorValues\RecordDto;

require __DIR__ . '/../sdk/bootstrap.php';

$rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
    ->withDto(RecordDto::class, DtoRules::create()
        ->field('type', FieldRule::create()->constructorValue())
        ->field('permissions', FieldRule::create()->constructorValue(allowMissing: true))
        ->field('flags', FieldRule::create()->constructorValue(allowMissing: true)));
$hydrator = Hydrator::forRules($rules);
$source = ['id' => 7, 'type' => 'record', 'permissions' => ['read', 'write'],
    'flags' => ['archived' => false, 'visible' => true]];
/** @var RecordDto $dto */
$dto = $hydrator->hydrate($source, RecordDto::class);
$observations = ['type' => $dto->type, 'permissions' => $dto->permissions, 'flags' => $dto->flags];
foreach (['conflict' => ['id' => 7, 'type' => 'other'], 'missing' => ['id' => 7]] as $case => $input) {
    try {
        $hydrator->hydrate($input, RecordDto::class);
        throw new LogicException('Ожидалась ошибка проверки поля');
    } catch (HydrationException $error) {
        $observations[$case] = $error->reason;
    }
}
$observations['allowMissing'] = $hydrator->hydrate(['id' => 7, 'type' => 'record'], RecordDto::class)->permissions;
echo json_encode($observations, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
