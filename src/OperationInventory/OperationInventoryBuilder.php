<?php

declare(strict_types=1);

namespace ApiSutra\OperationInventory;

use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Inventory\OperationInventoryInterface;
use ApiSutra\Contracts\Interfaces\Inventory\ResourceNameResolverInterface;
use ApiSutra\Request\RequestSpec;
use ApiSutra\Request\RequestSpecResolver;
use ApiSutra\Resolver\RequestScanner;

final readonly class OperationInventoryBuilder
{
    private const string RESOURCE_LABEL_SEPARATOR = ' / ';

    private SdkCallPathResolver $sdkCallPathResolver;
    private ResourceNameResolverInterface $resourceNameResolver;

    public function __construct(
        private RequestScanner $requestScanner,
        private RequestSpecResolver $requestSpecResolver,
        ?SdkCallPathResolver $sdkCallPathResolver = null,
        ?ResourceNameResolverInterface $resourceNameResolver = null,
    ) {
        $this->sdkCallPathResolver = $sdkCallPathResolver
            ?? new SdkCallPathResolver(new SdkCallPathMethodAnalyzer());
        $this->resourceNameResolver = $resourceNameResolver ?? new ResourceNameResolver();
    }

    public function buildForClient(ClientInterface|string $client): OperationInventoryInterface
    {
        $clientClass = is_string($client) ? $client : $client::class;
        $rootNamespace = $this->inferRootNamespace($clientClass);
        $requestClasses = $this->requestScanner->scanRoot($rootNamespace);
        $sdkCallPaths = $this->sdkCallPathResolver->resolveForClient($clientClass);

        return $this->buildInventory($requestClasses, $sdkCallPaths);
    }

    public function buildForRootNamespace(string $rootNamespace): OperationInventoryInterface
    {
        $requestClasses = $this->requestScanner->scanRoot($rootNamespace);

        return $this->buildInventory($requestClasses, []);
    }

    /**
     * @param array<int, string> $requestClasses
     * @param array<class-string, array<int, string>> $sdkCallPaths
     */
    private function buildInventory(array $requestClasses, array $sdkCallPaths): OperationInventoryInterface
    {
        $requestClasses = array_values(array_unique($requestClasses));

        $items = [];
        foreach ($requestClasses as $requestClass) {
            $spec = $this->requestSpecResolver->resolveClass($requestClass);
            $resourcePath = $this->resourceNameResolver->resolveResourcePath($requestClass);

            $items[] = new OperationDescriptorView(
                requestClass: $requestClass,
                httpMethod: $spec->method,
                endpoint: $spec->endpoint,
                responseType: $this->resolveResponseType($spec),
                operationDescriptor: $spec->operationDescriptor,
                hasDownload: $spec->hasDownload,
                hasNoAuth: $spec->hasNoAuth,
                skipCredentialsEnrichment: $spec->skipCredentialsEnrichment,
                sdkCallPaths: $sdkCallPaths[$requestClass] ?? [],
                continuationFinalType: $spec->continuationResult?->finalType,
                continuationUnwrap: $spec->continuationResult?->unwrap,
                pollRequestClass: $spec->continuationResult?->pollRequest,
                returnsUnwrap: $spec->returns?->unwrap,
                resourcePath: $resourcePath,
                resourceLabel: $this->buildResourceLabel($resourcePath),
            );
        }

        usort(
            $items,
            static fn (OperationDescriptorView $left, OperationDescriptorView $right): int => $left->requestClass <=> $right->requestClass,
        );

        return new OperationInventory($items);
    }

    /**
     * Если задан Returns::type, он имеет приоритет над response для каталога DTO.
     */
    private function resolveResponseType(RequestSpec $spec): ?string
    {
        return $spec->returns->type ?? $spec->responseType;
    }

    /**
     * @param array<int, string>|null $resourcePath
     */
    private function buildResourceLabel(?array $resourcePath): ?string
    {
        if ($resourcePath === null || $resourcePath === []) {
            return null;
        }

        return implode(self::RESOURCE_LABEL_SEPARATOR, $resourcePath);
    }

    private function inferRootNamespace(string $clientClass): string
    {
        $parts = explode('\\', trim($clientClass, '\\'));
        if (count($parts) < 2) {
            return $clientClass;
        }

        return implode('\\', array_slice($parts, 0, 2));
    }
}
