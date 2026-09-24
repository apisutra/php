<?php

declare(strict_types=1);

namespace Example\ConstructorValues;

final readonly class RecordDto
{
    public string $type;
    /** @var list<string> */
    public array $permissions;
    /** @var array<string, bool> */
    public array $flags;

    public function __construct(public int $id)
    {
        $this->type = 'record';
        $this->permissions = ['read', 'write'];
        $this->flags = ['visible' => true, 'archived' => false];
    }
}
