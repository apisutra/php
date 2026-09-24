<?php

declare(strict_types=1);

namespace ApiSutra\Localization;

use ApiSutra\Config\LocalizationConfig;

final readonly class MessageFormatter
{
    public function __construct(private LocalizationConfig $config = new LocalizationConfig())
    {
    }

    public function format(Message $message): string
    {
        $locales = array_unique([$this->config->locale, explode('-', $this->config->locale)[0], 'en']);
        $default = MessageCatalog::all('en')[$message->key] ?? $this->config->messages['en'][$message->key] ?? null;
        $expected = $default === null ? array_keys($message->parameters) : self::parameters($default);
        sort($expected);
        foreach ($locales as $locale) {
            $candidates = [$this->config->messages[$locale][$message->key] ?? null, MessageCatalog::all($locale)[$message->key] ?? null];
            foreach ($candidates as $template) {
                if ($template === null || self::parameters($template) !== $expected) {
                    continue;
                }
                $values = [];
                foreach ($expected as $name) {
                    if (!array_key_exists($name, $message->parameters)) {
                        return $message->key;
                    }
                    $value = $message->parameters[$name];
                    $values['{' . $name . '}'] = $value instanceof Message ? $this->format($value) : (string) $value;
                }
                // strtr не обрабатывает плейсхолдеры, попавшие в подставляемое значение.
                return strtr($template, $values);
            }
        }
        return $message->key;
    }

    /** @return list<string> */
    public static function parameters(string $template): array
    {
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $template, $matches);
        $parameters = array_values(array_unique($matches[1]));
        sort($parameters);
        return $parameters;
    }
}
