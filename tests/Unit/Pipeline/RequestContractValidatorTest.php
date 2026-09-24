<?php

declare(strict_types=1);

use ApiSutra\Pipeline\Flow\RequestContractValidator;
use ApiSutra\Tests\Stubs\Requests\OneOfAtLeastOneRequest;
use ApiSutra\Tests\Stubs\Requests\OneOfAtLeastOneWithDiscriminatorRequest;
use ApiSutra\Tests\Stubs\Requests\OneOfDuplicateContractsRequest;
use ApiSutra\Tests\Stubs\Requests\OneOfInvalidDotRootRequest;
use ApiSutra\Tests\Stubs\Requests\OneOfNestedSignatureRequest;
use ApiSutra\Tests\Stubs\Requests\OneOfSignatureRequest;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;

describe('RequestContractValidator', function () {
    it('пропускает запросы без oneOf атрибутов', function () {
        $validator = new RequestContractValidator();
        $result = $validator->validate(new SimpleGetRequest('query'));

        expect($result->failed())->toBeFalse()
            ->and($result->violation)->toBeNull()
            ->and($result->oneOfDebug)->toBeNull();
    });

    it('валидирует корректный вариант cloudcrypt', function () {
        $validator = new RequestContractValidator();
        $request = new OneOfSignatureRequest(
            type: 'KONTUR_UC',
            contents: 'payload',
            certificateId: 'cert-1',
            goskeyData: null,
        );

        $result = $validator->validate($request);

        expect($result->failed())->toBeFalse()
            ->and($result->violation)->toBeNull()
            ->and($result->oneOfDebug['contract'] ?? null)->toBe('signature_payload')
            ->and($result->oneOfDebug['matchedVariant'] ?? null)->toBe('cloudcrypt')
            ->and($result->oneOfDebug['discriminator']['field'] ?? null)->toBe('type')
            ->and($result->oneOfDebug['discriminator']['variant'] ?? null)->toBe('cloudcrypt');
    });

    it('считает пустой массив заполненным полем для oneOf', function () {
        $validator = new RequestContractValidator();
        $request = new OneOfSignatureRequest(
            type: 'GOSKEY',
            contents: 'payload',
            certificateId: null,
            goskeyData: [],
        );

        $result = $validator->validate($request);

        expect($result->failed())->toBeFalse()
            ->and($result->oneOfDebug['matchedVariant'] ?? null)->toBe('goskey');
    });

    it('возвращает ошибку если не выбран ни один вариант', function () {
        $validator = new RequestContractValidator();
        $request = new OneOfSignatureRequest(
            type: 'KONTUR_UC',
            contents: 'payload',
            certificateId: null,
            goskeyData: null,
        );

        $result = $validator->validate($request);

        expect($result->failed())->toBeTrue()
            ->and(violationCodes($result))->toContain('none_selected')
            ->and(violationCodes($result))->toContain('discriminator_variant_missing_fields');
    });

    it('возвращает ошибку если заполнено несколько вариантов', function () {
        $validator = new RequestContractValidator();
        $request = new OneOfSignatureRequest(
            type: 'KONTUR_UC',
            contents: 'payload',
            certificateId: 'cert-1',
            goskeyData: ['key' => 'value'],
        );

        $result = $validator->validate($request);

        expect($result->failed())->toBeTrue()
            ->and(violationCodes($result))->toContain('multiple_selected')
            ->and(violationCodes($result))->toContain('prohibited_variant_fields');
    });

    it('возвращает ошибку для неизвестного discriminator значения', function () {
        $validator = new RequestContractValidator();
        $request = new OneOfSignatureRequest(
            type: 'UNKNOWN',
            contents: 'payload',
            certificateId: null,
            goskeyData: ['key' => 'value'],
        );

        $result = $validator->validate($request);

        expect($result->failed())->toBeTrue()
            ->and(violationCodes($result))->toContain('unknown_discriminator_value');
    });

    it('возвращает ошибку при конфликте discriminator и выбранного варианта', function () {
        $validator = new RequestContractValidator();
        $request = new OneOfSignatureRequest(
            type: 'GOSKEY',
            contents: 'payload',
            certificateId: 'cert-1',
            goskeyData: null,
        );

        $result = $validator->validate($request);

        expect($result->failed())->toBeTrue()
            ->and(violationCodes($result))->toContain('discriminator_mismatch');
    });

    it('возвращает ошибку для дублированных имён oneOf-контрактов', function () {
        $validator = new RequestContractValidator();
        $request = new OneOfDuplicateContractsRequest(alphaData: 'x');

        $result = $validator->validate($request);

        expect($result->failed())->toBeTrue()
            ->and(violationCodes($result))->toContain('duplicate_contract_name');
    });

    it('возвращает ошибку если отсутствуют requiredCommon поля', function () {
        $validator = new RequestContractValidator();
        $request = new OneOfSignatureRequest(
            type: 'KONTUR_UC',
            contents: null,
            certificateId: 'cert-1',
            goskeyData: null,
        );

        $result = $validator->validate($request);

        expect($result->failed())->toBeTrue()
            ->and(violationCodes($result))->toContain('required_common_missing');
    });

    it('поддерживает oneOf по dot-path во вложенном payload', function () {
        $validator = new RequestContractValidator();
        $request = new OneOfNestedSignatureRequest(
            type: 'KONTUR_UC',
            signature: [
                'contents' => 'payload',
                'certificateId' => 'cert-1',
            ],
        );

        $result = $validator->validate($request);

        expect($result->failed())->toBeFalse()
            ->and($result->oneOfDebug['matchedVariant'] ?? null)->toBe('cloudcrypt');
    });

    it('возвращает ошибку invalid_dot_path_root для некорректного корня dot-path', function () {
        $validator = new RequestContractValidator();
        $request = new OneOfInvalidDotRootRequest(type: 'EMAIL');

        $result = $validator->validate($request);

        expect($result->failed())->toBeTrue()
            ->and(violationCodes($result))->toContain('invalid_dot_path_root');
    });

    it('разрешает несколько заполненных вариантов в режиме AtLeastOne', function () {
        $validator = new RequestContractValidator();
        $request = new OneOfAtLeastOneRequest(
            email: 'a@test.local',
            phone: '+79001234567',
        );

        $result = $validator->validate($request);

        expect($result->failed())->toBeFalse()
            ->and(violationCodes($result))->not->toContain('multiple_selected');
    });

    it('в режиме AtLeastOne требует хотя бы один вариант', function () {
        $validator = new RequestContractValidator();
        $request = new OneOfAtLeastOneRequest();

        $result = $validator->validate($request);

        expect($result->failed())->toBeTrue()
            ->and(violationCodes($result))->toContain('none_selected');
    });

    it('в режиме AtLeastOne с discriminator запрещает заполнение лишних вариантов', function () {
        $validator = new RequestContractValidator();
        $request = new OneOfAtLeastOneWithDiscriminatorRequest(
            type: 'EMAIL',
            email: 'a@test.local',
            phone: '+79001234567',
        );

        $result = $validator->validate($request);

        expect($result->failed())->toBeTrue()
            ->and(violationCodes($result))->toContain('prohibited_variant_fields');
    });
});

/**
 * @return array<int, string>
 */
function violationCodes(object $result): array
{
    $violations = $result->violation?->violations ?? [];
    $codes = [];

    foreach ($violations as $violation) {
        if (is_array($violation) && is_string($violation['code'] ?? null)) {
            $codes[] = $violation['code'];
        }
    }

    return $codes;
}
