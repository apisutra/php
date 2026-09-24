<?php

declare(strict_types=1);

namespace ApiSutra\Config;

use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Localization\Message;

final readonly class LocalizationConfig
{
    public string $locale;
    /** @var array<string, array<string, string>> */
    public array $messages;

    /** @param array<string, array<string, string>> $messages Дополнительные каталоги и переопределения SDK. */
    public function __construct(string $locale = 'en', array $messages = [])
    {
        $this->locale = self::normalize($locale);
        $this->messages = self::validateCatalogs($messages);
    }

    /** @return array<string, array<string, string>> */
    private static function validateCatalogs(mixed $messages): array
    {
        $normalized = [];
        foreach ($messages as $language => $catalog) {
            if (!is_string($language) || !is_array($catalog)) {
                throw new ConfigurationException(new Message('localization.invalid_catalog'));
            }
            $language = self::normalize($language);
            foreach ($catalog as $key => $template) {
                if (!is_string($key) || $key === '' || !is_string($template)) {
                    throw new ConfigurationException(new Message('localization.invalid_catalog'));
                }
                $normalized[$language][$key] = $template;
            }
        }
        return $normalized;
    }

    private static function normalize(string $locale): string
    {
        $locale = strtolower(str_replace('_', '-', $locale));
        if (!preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/D', $locale)) {
            throw new ConfigurationException(new Message('localization.invalid_locale'));
        }
        return $locale;
    }
}
