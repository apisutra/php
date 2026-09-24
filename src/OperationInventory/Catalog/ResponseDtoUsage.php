<?php

declare(strict_types=1);

namespace ApiSutra\OperationInventory\Catalog;

use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Enums\Inventory\ResponseDtoKind;

/**
 * Запись каталога: один usage конкретного response-класса в рамках конкретного request-класса.
 *
 * Все необходимые exporter-у поля хранятся прямо здесь, чтобы каталог оставался единым
 * источником истины и не требовал дополнительных join-ов с OperationInventory.
 */
final readonly class ResponseDtoUsage
{
    /**
     * @param class-string                $requestClass  request-класс, который реально возвращает этот response
     * @param array<int, string>|null     $resourcePath  иерархия ресурсов (ResourceNameResolverInterface)
     * @param class-string                $responseClass конкретный response-класс (sync DTO / async final DTO / FileResponse)
     * @param class-string|null           $pollRequest   класс poll-запроса (только для AsyncFinal)
     * @param class-string|null           $serviceClass  FQCN сервис-клиента в multi-service режиме (null для single-client)
     */
    public function __construct(
        public string $requestClass,
        public ?array $resourcePath,
        public ?string $resourceLabel,
        public ?HttpMethod $httpMethod,
        public ?string $endpoint,
        public ?string $title,
        public ?string $description,
        public string $responseClass,
        public ResponseDtoKind $kind,
        public ?string $pollRequest = null,
        public ?string $unwrap = null,
        public ?string $serviceClass = null,
    ) {
    }
}
