<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Request;

use ApiSutra\Localization\Message;
use Attribute;
use BackedEnum;
use ApiSutra\Exceptions\Core\InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class AuthScope
{
    /**
     * @param string|BackedEnum $scope Ключ scope (строка или string-backed enum case).
     */
    public string $scope;

    public function __construct(string|BackedEnum $scope)
    {
        $resolved = $scope instanceof BackedEnum ? $scope->value : $scope;

        if (!is_string($resolved)) {
            throw new InvalidArgumentException(new Message('attributes.authscope_supports_only_a_string_backed_enum_or_a'));
        }

        $this->scope = $resolved;
    }
}
