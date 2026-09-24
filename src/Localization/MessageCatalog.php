<?php

declare(strict_types=1);

namespace ApiSutra\Localization;

/** Кешируются только неизменяемые переводы, без текущей локали клиента. */
final class MessageCatalog
{
    /** @var array<string, array<string, string>> */
    private static array $catalogs = [];

    /** @return array<string, string> */
    public static function all(string $locale): array
    {
        if (!in_array($locale, ['en', 'ru'], true)) {
            return [];
        }
        if (!isset(self::$catalogs[$locale])) {
            $catalog = [];
            foreach (glob(__DIR__ . '/Resources/' . $locale . '/*.php') ?: [] as $file) {
                /** @var array<string, string> $messages */
                $messages = require $file;
                $catalog += $messages;
            }
            self::$catalogs[$locale] = $catalog;
        }
        return self::$catalogs[$locale];
    }
}
