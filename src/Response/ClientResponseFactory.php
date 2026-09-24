<?php

declare(strict_types=1);

namespace ApiSutra\Response;

use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Result\ResolvedResultInterface;
use ApiSutra\VO\Errors\ClientError;
use ApiSutra\VO\Errors\ClientErrorFactory;
use ApiSutra\VO\Errors\ClientErrorMapperAwareInterface;
use ApiSutra\VO\Errors\ClientErrorMapperInterface;
use ApiSutra\VO\Errors\DefaultClientErrorMapper;

/**
 * Дефолтная фабрика клиентского ответа.
 */
final readonly class ClientResponseFactory implements ClientResponseFactoryInterface, ClientErrorMapperAwareInterface
{
    public const string KEY_DATA = 'data';
    public const string KEY_ERRORS = 'errors';

    private ClientErrorMapperInterface $mapper;
    private ClientErrorFactory $errorFactory;

    public function __construct(
        ?ClientErrorMapperInterface $mapper = null,
    ) {
        $this->mapper = $mapper ?? new DefaultClientErrorMapper();
        $this->errorFactory = new ClientErrorFactory($this->mapper);
    }

    public function withErrorMapper(ClientErrorMapperInterface $mapper): static
    {
        return new self($mapper);
    }

    public function make(ResolvedResultInterface $result): ClientResponse
    {
        $execution = $result->result();
        $errors = $this->mapErrors($execution->errors);

        return match ($execution->status) {
            ResultStatus::SUCCESS => new ClientResponse(
                status: 200,
                body: $execution->data,
            ),
            ResultStatus::PARTIAL => new ClientResponse(
                status: 207,
                body: [
                    self::KEY_DATA => $execution->data,
                    self::KEY_ERRORS => $errors,
                ],
            ),
            ResultStatus::FAILED => new ClientResponse(
                status: $this->mapper->status($execution->errors),
                body: [
                    self::KEY_ERRORS => $errors,
                ],
            ),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapErrors(ErrorCollection $errors): array
    {
        $mapped = $this->errorFactory->makeMany($errors);

        return array_map(
            static fn (ClientError $error): array => $error->toArray(),
            $mapped,
        );
    }
}
