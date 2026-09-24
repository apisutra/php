<?php

declare(strict_types=1);

use Example\Records\Config\ClientConfigFactory;

// Объекты создаёт provider; конфигурацию можно сохранить через config:cache.
return [
    'base_url' => env('RECORDS_BASE_URL', ClientConfigFactory::DEFAULT_BASE_URL),
    'timeout' => env('RECORDS_TIMEOUT', ClientConfigFactory::DEFAULT_TIMEOUT),
    'token' => env('RECORDS_TOKEN'),
];
