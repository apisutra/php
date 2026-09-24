<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ConstructorOwned;

use DateTimeInterface;
use JsonSerializable;
use Stringable;

final class InvalidFieldsDto
{
    public static string $static;
    private string $hidden;
    public mixed $mixed;
    public object $object;
    public $untyped;
    public string $defaulted = 'known';
    public string $virtual { get => 'known'; }
    // phpcs:ignore PSR2.Classes.PropertyDeclaration.Multiple,PSR2.Classes.PropertyDeclaration.ScopeMissing -- PHP 8.4 set-hook в отрицательной фикстуре.
    public string $hooked { set => $value; }
    public DateTimeInterface $date;
    public Stringable&JsonSerializable $intersection;
    public string $parameter;

    public function __construct(public string $promoted = 'known', string $parameter = 'known')
    {
        State::$calls++;
    }
}
