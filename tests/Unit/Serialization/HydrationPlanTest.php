<?php

declare(strict_types=1);

use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Tests\Stubs\MappingHydration\PlainDto;
use ApiSutra\Tests\Support\HydrationPlanCorpus;

it('сохраняет стадии, живую policy, provenance и изоляцию на общем корпусе', function (): void {
    $corpus = new HydrationPlanCorpus();
    $corpus->run();
    foreach ($corpus->observations as $id => $row) {
        expect($row['actual'], $id)->toBe($row['expected']);
    }
});

it('освобождает план вместе с описанием правил при сохранённом общем кеше', function (): void {
    $cache = new AttributeMetadataCache();
    $hydrator = new Hydrator(new CastRegistry(), $cache);
    $dto = $hydrator->hydrate(['id' => 7], PlainDto::class);
    $description = $hydrator->descriptions()->forClass(PlainDto::class);
    $plans = $cache->get(PlainDto::class . ':hydration-plan')['plans'];
    $weak = WeakReference::create($description);
    $weakDto = WeakReference::create($dto);
    expect(count($plans))->toBe(1);
    unset($dto, $description, $hydrator);
    gc_collect_cycles();
    expect($weak->get())->toBeNull()->and($weakDto->get())->toBeNull()->and(count($plans))->toBe(0);
});
