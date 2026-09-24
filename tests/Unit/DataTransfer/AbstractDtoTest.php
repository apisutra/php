<?php

declare(strict_types=1);

use ApiSutra\Tests\Stubs\Dto\OutputAddressDto;
use ApiSutra\Tests\Stubs\Dto\OutputItemDto;
use ApiSutra\Tests\Stubs\Dto\OutputUserDto;

describe('AbstractDto', function () {
    it('создает DTO через make с именованными аргументами', function () {
        $dto = OutputAddressDto::make(city: 'Moscow', zipCode: '101000');

        expect($dto->city)->toBe('Moscow');
        expect($dto->zipCode)->toBe('101000');
    });

    it('создает клон через with с переопределениями', function () {
        $original = OutputAddressDto::make(city: 'Moscow', zipCode: '101000');
        $updated = $original->with(city: 'Kazan');

        expect($original->city)->toBe('Moscow');
        expect($updated->city)->toBe('Kazan');
        expect($updated->zipCode)->toBe('101000');
    });

    it('сохраняет значения без переопределения в with', function () {
        $original = new OutputUserDto(
            userId: 10,
            address: new OutputAddressDto('Moscow', '101000'),
            items: [new OutputItemDto(1, 'First')],
            title: 'hello',
            tags: ['a'],
            plainValue: null,
        );

        $updated = $original->with(title: 'updated');

        expect($updated->title)->toBe('updated');
        expect($updated->userId)->toBe(10);
        expect($updated->address->city)->toBe('Moscow');
        expect($updated->items)->toHaveCount(1);
    });
});
