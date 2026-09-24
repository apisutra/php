<?php

declare(strict_types=1);

namespace ApiSutra\Casts;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\VO\Files\Base64File;
use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Context\SerializationContext;

final readonly class DataUriBase64FileCast implements CastInterface
{
    #[\Override]
    public function hydrate(mixed $value, HydrationContext $context): ?Base64File
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new ConfigurationException(new Message('casts.datauribase64filecast_hydrate_expects_string_null'));
        }

        $normalized = $this->normalizeBase64String($value);
        if ($normalized === null) {
            return null;
        }

        $this->assertValidBase64($normalized);

        return new Base64File($normalized);
    }

    #[\Override]
    public function serialize(mixed $value, SerializationContext $context): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Base64File) {
            return base64_encode($value->content());
        }

        if (!is_string($value)) {
            throw new ConfigurationException(new Message('casts.datauribase64filecast_serialize_expects_base64file_string_null'));
        }

        $normalized = $this->normalizeBase64String($value);
        if ($normalized === null) {
            return null;
        }

        $this->assertValidBase64($normalized);

        return $normalized;
    }

    private function normalizeBase64String(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^data:[^,]*;base64,\s*(.+)$/is', $trimmed, $matches) === 1) {
            $trimmed = trim($matches[1]);
        }

        return $trimmed === '' ? null : $trimmed;
    }

    private function assertValidBase64(string $value): void
    {
        if (base64_decode($value, true) === false) {
            throw new ConfigurationException(new Message('casts.datauribase64filecast_received_an_invalid_base64_payload'));
        }
    }
}
