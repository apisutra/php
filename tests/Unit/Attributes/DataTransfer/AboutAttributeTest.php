<?php

declare(strict_types=1);

use ApiSutra\Attributes\DataTransfer\About;

describe('About attribute', function () {
    it('хранит минимальное описание поля', function () {
        $about = new About(title: 'ИНН физического лица');

        expect($about->title)->toBe('ИНН физического лица')
            ->and($about->description)->toBeNull()
            ->and($about->example)->toBeNull()
            ->and($about->examples)->toBeNull()
            ->and($about->format)->toBeNull()
            ->and($about->nullableReason)->toBeNull()
            ->and($about->note)->toBeNull();
    });

    it('поддерживает scalar, json-string и array examples', function () {
        $about = new About(
            title: 'Сырые данные провайдера',
            example: [
                'status' => 'found',
                'score' => 0.92,
            ],
            examples: [
                '{"status":"found","score":0.92}',
                [
                    'status' => 'not_found',
                    'score' => 0,
                ],
            ],
        );

        expect($about->example)->toBe([
            'status' => 'found',
            'score' => 0.92,
        ])
            ->and($about->examples)->toBe([
                '{"status":"found","score":0.92}',
                [
                    'status' => 'not_found',
                    'score' => 0,
                ],
            ]);
    });
});
