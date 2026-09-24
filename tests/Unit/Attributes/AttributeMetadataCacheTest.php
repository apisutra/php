<?php

declare(strict_types=1);

use ApiSutra\Attributes\AttributeMetadataCache;

describe('AttributeMetadataCache', function () {
    it('не сохраняет данные, когда кеш отключён', function () {
        $cache = new AttributeMetadataCache(false);
        $cache->set('Class', ['value' => 1]);

        expect($cache->isEnabled())->toBeFalse()
            ->and($cache->get('Class'))->toBeNull();
    });

    it('сохраняет и возвращает данные, когда кеш включён', function () {
        $cache = new AttributeMetadataCache(true);
        $cache->set('Class', ['value' => 1]);

        expect($cache->isEnabled())->toBeTrue()
            ->and($cache->get('Class'))->toBe(['value' => 1]);
    });
});
