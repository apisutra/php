<?php

declare(strict_types=1);

namespace Example\DtoHydrator;

final class UserFactory
{
    public function create(int $id, string $name, ?Address $address): User
    {
        return User::create($id, trim($name), $address);
    }
}
