<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\JsonContainerShapes;

use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\DataTransfer\AbstractResponseDto;
use ApiSutra\Serialization\Shapes\ListShape;
use ApiSutra\Serialization\Rules\ScalarType;

readonly class ResponseDto extends AbstractResponseDto
{
    public function __construct(#[Shape(new ListShape(ScalarType::String))] public array $items = [])
    {
    }
}
