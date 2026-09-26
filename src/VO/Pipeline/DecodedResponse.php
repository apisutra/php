<?php

declare(strict_types=1);

namespace ApiSutra\VO\Pipeline;

use ApiSutra\Serialization\Input\SourceShapeMap;
use ApiSutra\Contracts\Interfaces\Response\ResponseHandlerInterface;

/** Данные для hooks и выбранный обработчик формата ответа. */
final readonly class DecodedResponse
{
    public function __construct(
        public mixed $data,
        public ?ResponseHandlerInterface $handler = null,
        /** @internal */
        public ?SourceShapeMap $shape = null,
        /** @internal */
        public bool $jsonSourceKnown = false,
    ) {
    }
}
