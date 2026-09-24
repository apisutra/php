<?php

declare(strict_types=1);

namespace Example\Records\Demo;

use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use Example\Records\DemoClient;
use Example\Records\Resources\Records\Get\GetRecordRequest;
use RuntimeException;

/**
 * Каждая копия нарушает одно правило; остальные данные остаются исходными.
 *
 * @param array<string, mixed> $source
 * @return array<string, array<string, mixed>>
 */
function invalidPayloads(array $source): array
{
    $replace = static fn (array $changes): array => array_replace_recursive($source, $changes);

    // Отсутствие поля нельзя показать заменой на null — это разные сценарии.
    $withoutId = $source;
    unset($withoutId['record_id']);

    return [
        'nested_contact' => $replace(['author' => ['contact' => ['email' => 42]]]),
        'collection_item' => $replace(['tags' => [1 => ['id' => '2']]]),
        'unknown_variant' => $replace(['assets' => [0 => ['value' => ['type' => 'audio']]]]),
        'variant_field' => $replace(['assets' => [0 => ['value' => ['width' => '640']]]]),
        'list_item' => $replace(['related_ids' => [1 => '12']]),
        'custom_cast' => $replace(['reading_time' => '03:99']),
        'missing_id' => $withoutId,
        'null_primary' => $replace(['record_id' => null, 'id' => 8]),
        'null_revision' => $replace(['revision' => null]),
        'constructor_value' => $replace(['kind' => 'other']),
        'enum' => $replace(['state' => 'unknown']),
        'date' => $replace(['created_at' => 'yesterday']),
    ];
}

/**
 * Показать отказ через настоящий клиент, включая unwrap и диагностику исходного поля.
 *
 * @param array<string, mixed> $source
 * @return array<string, array<string, mixed>>
 */
function collectHydrationErrors(DemoClient $client, MockTransport $transport, array $source): array
{
    $payloads = invalidPayloads($source);
    $responses = array_map(
        static fn (array $data): MockResponse => MockResponse::success(['data' => $data]),
        array_values($payloads),
    );
    $transport->fake([GetRecordRequest::class => MockResponse::sequence($responses)]);

    $diagnostics = [];
    foreach (array_keys($payloads) as $case) {
        $execution = $client->records()->get(7)->send()->raw();
        $problem = $execution->errors->first();
        if (!$execution->isFailed() || $problem === null) {
            throw new RuntimeException('Ожидалась ошибка сценария ' . $case);
        }

        // Логический путь DTO и JSON Pointer в исходном ответе — разные полезные координаты.
        $diagnostics[$case] = [
            'code' => $problem->code->value,
            'reason' => $problem->context['reason'] ?? null,
            'path' => $problem->context['path'] ?? null,
            'sourcePath' => $problem->context['sourcePath'] ?? null,
            'sourcePathKind' => $problem->context['sourcePathKind'] ?? null,
            'httpStatus' => $execution->response?->status,
        ];
    }

    return $diagnostics;
}
