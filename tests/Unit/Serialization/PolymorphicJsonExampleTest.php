<?php

declare(strict_types=1);

it('исполняет опубликованный HTTP polling webhook пример с fallback и четырьмя ошибками формы', function (): void {
    $result = require dirname(__DIR__, 3) . '/docs/example/polymorphic-json/run.php';
    expect($result['same_model'])->toBeTrue()
        ->and($result['raw_attachment']['type'])->toBe('future_attachment')
        ->and($result['unknown_update']['feature'])->toBeTrue()
        ->and($result['errors']['participants'])->toBe(['reason' => 'invalid_object_shape', 'sourcePath' => '/message/participants'])
        ->and($result['errors']['payload'])->toBe(['reason' => 'unexpected_response_shape', 'sourcePath' => '/message/attachments/0/payload'])
        ->and($result['errors']['permissions']['reason'])->toBe('invalid_list_shape')
        ->and($result['errors']['polling_permissions'])->toBe('invalid_list_shape');
});
