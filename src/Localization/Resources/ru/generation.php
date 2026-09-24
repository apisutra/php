<?php

declare(strict_types=1);

return [
    'generation.invalid_name' => 'Некорректное PHP-имя: {name}.',
    'generation.explicit_root' => 'Для явного каталога требуется непустой namespace.',
    'generation.invalid_composer' => 'Не удалось прочитать PSR-4 конфигурацию composer.json.',
    'generation.ambiguous_root' => 'Выберите PSR-4 корень через --namespace либо задайте --namespace и --directory.',
    'generation.outside_root' => 'Создаваемый файл выходит за выбранный каталог.',
    'generation.cannot_create' => 'Невозможно создать {path}: путь уже существует либо недоступен для записи.',
    'generation.request_options' => 'Опции endpoint и DTO доступны только для запросов.',
    'generation.invalid_endpoint' => 'Endpoint должен быть непустой строкой без управляющих символов.',
    'generation.missing_template' => 'Шаблон генерации отсутствует в пакете.',
    'generation.invalid_arguments' => 'Некорректные аргументы генерации. Выполните apisutra --help.',
];
