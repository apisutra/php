<?php

declare(strict_types=1);

namespace ApiSutra\OperationInventory\Catalog;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Core\MultiServiceClientInterface;
use ApiSutra\Contracts\Interfaces\Inventory\OperationInventoryInterface;
use ApiSutra\Contracts\Interfaces\Inventory\ResponseDtoCatalogProviderInterface;
use ApiSutra\Core\AbstractClient;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\OperationInventory\CompositeOperationInventory;

/**
 * Фабрика multi-service каталога DTO.
 *
 * Что делает:
 * - собирает operationInventory() со всех сервис-клиентов
 * - складывает их в CompositeOperationInventory
 * - размечает каждый usage в ResponseDtoCatalog meaningful serviceClass
 *
 * Сами сервис-клиенты ничего не сканируют дополнительно — composite агрегирует
 * уже готовые inventory.
 */
final readonly class MultiServiceResponseDtoCatalogFactory
{
    public function fromMultiService(MultiServiceClientInterface $mega): ResponseDtoCatalog
    {
        return $this->fromServices($mega->services());
    }

    /**
     * @param array<int, ClientInterface> $services
     */
    public function fromServices(array $services): ResponseDtoCatalog
    {
        $inventories = [];
        $serviceByRequest = [];

        foreach ($services as $service) {
            $inventory = $this->resolveInventory($service);
            $inventories[] = $inventory;

            $serviceClass = $service::class;
            foreach ($inventory->all() as $operation) {
                $serviceByRequest[$operation->requestClass] ??= $serviceClass;
            }
        }

        return new ResponseDtoCatalog(
            inventory: new CompositeOperationInventory($inventories),
            serviceClassResolver: new MapServiceClassResolver($serviceByRequest),
        );
    }

    /**
     * Сливает каталоги произвольного набора провайдеров в один.
     *
     * Принимает на вход:
     * - одиночные клиенты (`AbstractClient` / `ClientInterface` + `ResponseDtoCatalogProviderInterface`)
     * - мегаклиенты (`MultiServiceClientInterface` + `ResponseDtoCatalogProviderInterface`)
     *   — раскрываются в свои services()
     *
     * Каждому usage проставляется serviceClass:
     * - для services() мегаклиента — FQCN сервиса
     * - для одиночного клиента — FQCN самого клиента
     *
     * Если один и тот же requestClass встречается у нескольких провайдеров,
     * выигрывает первый по порядку (детерминированно).
     */
    public function merge(ResponseDtoCatalogProviderInterface ...$providers): ResponseDtoCatalog
    {
        $inventories = [];
        $serviceByRequest = [];

        foreach ($providers as $provider) {
            foreach ($this->expandProvider($provider) as $serviceClass => $inventory) {
                $inventories[] = $inventory;
                foreach ($inventory->all() as $operation) {
                    $serviceByRequest[$operation->requestClass] ??= $serviceClass;
                }
            }
        }

        return new ResponseDtoCatalog(
            inventory: new CompositeOperationInventory($inventories),
            serviceClassResolver: new MapServiceClassResolver($serviceByRequest),
        );
    }

    /**
     * @return iterable<class-string, OperationInventoryInterface>
     */
    private function expandProvider(ResponseDtoCatalogProviderInterface $provider): iterable
    {
        if ($provider instanceof MultiServiceClientInterface) {
            foreach ($provider->services() as $service) {
                yield $service::class => $this->resolveInventory($service);
            }

            return;
        }

        if ($provider instanceof ClientInterface) {
            yield $provider::class => $this->resolveInventory($provider);

            return;
        }

        throw new ConfigurationException(new Message('operationinventory.provider_s_is_not_supported_by_merge_clientinterface_or', ['value0' => $provider::class]));
    }

    private function resolveInventory(ClientInterface $service): OperationInventoryInterface
    {
        if ($service instanceof AbstractClient) {
            return $service->operationInventory();
        }

        if (method_exists($service, 'operationInventory')) {
            /** @var mixed $inventory */
            $inventory = $service->{'operationInventory'}();
            if ($inventory instanceof OperationInventoryInterface) {
                return $inventory;
            }
        }

        throw new ConfigurationException(new Message('operationinventory.service_client_s_does_not_expose_operationinventoryinterface_through_operationinventory', ['value0' => $service::class]));
    }
}
