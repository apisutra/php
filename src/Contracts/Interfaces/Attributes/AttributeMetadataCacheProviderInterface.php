<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Attributes;

use ApiSutra\Attributes\AttributeMetadataCache;

interface AttributeMetadataCacheProviderInterface
{
    /**
     * Получить кеш метаданных атрибутов
     */
    public function getAttributeMetadataCache(): AttributeMetadataCache;
}
