<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

final class InvalidReceiversDto
{
    public static array $static = [];
    public array $virtual { get => []; }
    protected array $protected = [];
    public string $wrong = '';
    public array $extra = [];
    public function __construct()
    {
    }
}
