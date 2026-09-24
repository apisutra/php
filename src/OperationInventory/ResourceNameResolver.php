<?php

declare(strict_types=1);

namespace ApiSutra\OperationInventory;

use ApiSutra\Contracts\Interfaces\Inventory\ResourceNameResolverInterface;

/**
 * Дефолтный резолвер resource-иерархии по неймспейсу.
 *
 * Эвристика: между сегментами `\Resources\` и `\Requests\` располагается путь ресурса.
 *
 * Примеры:
 * - \Foo\Resources\Reports\Tasks\Requests\CreateRequest → ['Reports', 'Tasks']
 * - \Foo\Resources\Files\Requests\DownloadRequest      → ['Files']
 * - \Foo\Bar\StandaloneRequest                          → null
 */
final readonly class ResourceNameResolver implements ResourceNameResolverInterface
{
    private const string RESOURCES_SEGMENT = 'Resources';
    private const string REQUESTS_SEGMENT = 'Requests';

    /**
     * @return array<int, string>|null
     */
    #[\Override]
    public function resolveResourcePath(string $requestClass): ?array
    {
        $segments = explode('\\', trim($requestClass, '\\'));

        $resourcesIndex = $this->indexOf($segments, self::RESOURCES_SEGMENT);
        if ($resourcesIndex === null) {
            return null;
        }

        $requestsIndex = $this->indexOf($segments, self::REQUESTS_SEGMENT, $resourcesIndex + 1);
        if ($requestsIndex === null || $requestsIndex <= $resourcesIndex + 1) {
            return null;
        }

        $path = array_slice($segments, $resourcesIndex + 1, $requestsIndex - $resourcesIndex - 1);

        return $path === [] ? null : array_values($path);
    }

    /**
     * @param  array<int, string> $segments
     */
    private function indexOf(array $segments, string $needle, int $startFrom = 0): ?int
    {
        $count = count($segments);
        for ($i = $startFrom; $i < $count; $i++) {
            if ($segments[$i] === $needle) {
                return $i;
            }
        }

        return null;
    }
}
