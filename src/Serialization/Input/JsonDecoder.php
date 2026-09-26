<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Input;

use JsonException;

/** @internal Штатный decoder владеет значениями, синтаксисом, Unicode и пределом глубины. */
final readonly class JsonDecoder
{
    public function decode(string $json, bool $shapes = true): HydrationInput
    {
        if (!$shapes) {
            return new HydrationInput(json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING));
        }
        try {
            $data = json_decode($json, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $exception) {
            if ($exception->getCode() !== JSON_ERROR_INVALID_PROPERTY_NAME) {
                throw $exception;
            }
            // Допустимое имя с ведущим NUL непредставимо в stdClass. Проверка соседей сохраняется.
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            return new HydrationInput($data, (new JsonShapeReader())->read($json), true);
        }
        $shape = (new JsonValueNormalizer())->normalize($data);
        return new HydrationInput($data, $shape, true);
    }
}
