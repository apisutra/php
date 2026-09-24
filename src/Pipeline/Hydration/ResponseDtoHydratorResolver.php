<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Hydration;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Serialization\DtoHydratorInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\ControlFlow\ControlFlowException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Localization\Message;
use ApiSutra\Support\ContainerProviderRegistry;
use Throwable;

/** @internal Разрешает прикладной обработчик один раз на ответ, вне вычислений DTO. */
final readonly class ResponseDtoHydratorResolver
{
    public function resolve(RequestInterface $request, ClientConfig $config): DtoHydratorInterface|false|null
    {
        $returns = $request instanceof AbstractRequest ? $request->getReturnsAttribute() : null;
        ResponseContractGuard::validateDeclaration($returns, $request instanceof AbstractRequest ? $request->getResponseType() : null);
        $selection = $returns?->hydrator;
        if ($selection === null || $selection === false) {
            return $selection;
        }
        try {
            $instance = ContainerProviderRegistry::resolve($config->containerProvider)->make($selection) ?? new $selection();
            if (!$instance instanceof DtoHydratorInterface) {
                throw new ConfigurationException(new Message('serialization.invalid_dto_hydrator', ['class' => $selection]));
            }
            return $instance;
        } catch (ControlFlowException | ExecutionDeadlineException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ConfigurationException(new Message('serialization.invalid_dto_hydrator', ['class' => $selection]), previous: $exception);
        }
    }
}
