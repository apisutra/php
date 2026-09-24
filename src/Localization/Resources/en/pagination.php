<?php

declare(strict_types=1);

return [
    'pagination.item_keys_must_be_integers_or_strings' => 'Pagination item keys must be integers or strings',
    'pagination.concurrency_must_be_positive' => 'Pagination concurrency must be greater than 0',
    'pagination.page_size_must_be_positive' => 'Page size must be greater than 0',
    'pagination.concurrent_iterator_not_supported' => 'Lazy pagination requires concurrency 1; use all, pages or range for concurrent loading',
    'pagination.cannot_create_the_items_collection' => 'Cannot create the items collection',
    'pagination.collection_class_not_found' => 'Collection class \'{class}\' not found',
    'pagination.collection_factory_must_implement_paginationitemscollectionfactoryinterface' => 'Collection factory must implement PaginationItemsCollectionFactoryInterface',
    'pagination.cursor_did_not_change' => 'cursor did not change',
    'pagination.factory_class_not_found' => 'Factory class \'{factory}\' not found',
    'pagination.invalid_page_range' => 'Invalid page range',
    'pagination.items_must_be_an_array_or_a_collection' => 'Items must be an array or an iterable collection',
    'pagination.offset_based_pagination_requires_limit' => 'Offset-based pagination requires limit',
    'pagination.page_count_must_be_greater_than_0' => 'Page count must be greater than 0',
    'pagination.page_did_not_change' => 'page did not change',
    'pagination.page_limit_reached' => 'page limit reached',
    'pagination.page_loading_failed' => 'page loading failed',
    'pagination.pagination_stopped' => 'Pagination stopped: {reason}',
    'pagination.concurrent_cursor_not_supported' => 'Concurrent pagination requires page numbers or offsets; cursors must be traversed sequentially.',
    'pagination.concurrent_all_requires_total' => 'Concurrent all() requires total and a positive perPage; use pages() or range() for an explicit bound.',
    'pagination.page_metadata_changed' => 'Page metadata changed during concurrent pagination.',
];
