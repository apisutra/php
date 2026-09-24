<?php

declare(strict_types=1);

namespace ApiSutra\Serialization;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Serialization\SerializationException;
use JsonException;

final class JsonEncoder
{
    public static function encode(mixed $value): string
    {
        try {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            // Сообщение не содержит исходных значений payload.
            throw new SerializationException(new Message('serialization.failed_to_serialize_json', ['value0' => $exception->getMessage()]), 0, $exception);
        }
    }
}
