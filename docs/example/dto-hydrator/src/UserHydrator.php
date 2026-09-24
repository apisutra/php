<?php

declare(strict_types=1);

namespace Example\DtoHydrator;

use ApiSutra\Contracts\Interfaces\Serialization\DtoHydratorInterface;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Context\HydrationContext;
use Override;

final readonly class UserHydrator implements DtoHydratorInterface
{
    public function __construct(private UserFactory $factory) {}

    #[Override]
    public function supports(string $dtoClass): bool
    {
        return $dtoClass === User::class;
    }

    #[Override]
    public function hydrate(array|object $data, string $dtoClass, HydrationContext $context): object
    {
        $data = (array) $data;
        if (!is_int($data['id'] ?? null) || !is_string($data['name'] ?? null)) {
            throw HydrationException::invalidValue('invalid_user', 'integer id and string name', 'invalid user fields');
        }
        $address = $data['address'] ?? null;
        if ($address !== null && !is_array($address) && !is_object($address)) {
            throw HydrationException::invalidValue('invalid_address', 'object', get_debug_type($address), 'address');
        }
        // Address не поддерживается этим обработчиком и проходит штатную гидратацию.
        return $this->factory->create(
            $data['id'],
            $data['name'],
            $address === null ? null : $context->hydrate($address, Address::class),
        );
    }
}
