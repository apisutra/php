<?php

declare(strict_types=1);

return [
    'pagination.item_keys_must_be_integers_or_strings' => 'Ключи элементов пагинации должны быть целыми числами или строками',
    'pagination.concurrency_must_be_positive' => 'Конкурентность пагинации должна быть больше 0',
    'pagination.page_size_must_be_positive' => 'Размер страницы должен быть больше 0',
    'pagination.concurrent_iterator_not_supported' => 'Ленивая пагинация требует concurrency 1; для конкурентной загрузки используйте all, pages или range',
    'pagination.cannot_create_the_items_collection' => 'Невозможно создать коллекцию items',
    'pagination.collection_class_not_found' => 'Класс коллекции \'{class}\' не найден',
    'pagination.collection_factory_must_implement_paginationitemscollectionfactoryinterface' => 'Фабрика коллекции должна реализовывать PaginationItemsCollectionFactoryInterface',
    'pagination.cursor_did_not_change' => 'cursor не изменился',
    'pagination.factory_class_not_found' => 'Класс фабрики \'{factory}\' не найден',
    'pagination.invalid_page_range' => 'Некорректный диапазон страниц',
    'pagination.items_must_be_an_array_or_a_collection' => 'Items должны быть массивом или итерируемой коллекцией',
    'pagination.offset_based_pagination_requires_limit' => 'Для offset-based пагинации требуется limit',
    'pagination.page_count_must_be_greater_than_0' => 'Количество страниц должно быть больше 0',
    'pagination.page_did_not_change' => 'page не изменился',
    'pagination.page_limit_reached' => 'достигнут лимит страниц',
    'pagination.page_loading_failed' => 'ошибка загрузки страницы',
    'pagination.pagination_stopped' => 'Пагинация остановлена: {reason}',
    'pagination.concurrent_cursor_not_supported' => 'Конкурентная пагинация требует номеров страниц или offset; cursor обходится последовательно.',
    'pagination.concurrent_all_requires_total' => 'Конкурентный all() требует total и положительный perPage; задайте явный предел через pages() или range().',
    'pagination.page_metadata_changed' => 'Метаданные страницы изменились во время конкурентной пагинации.',
];
