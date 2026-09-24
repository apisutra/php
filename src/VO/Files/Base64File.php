<?php

declare(strict_types=1);

namespace ApiSutra\VO\Files;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

/**
 * Value Object для работы с файлами в формате Base64.
 *
 * Позволяет декодировать Base64-строку и получать содержимое файла
 * в виде строки, потока или сохранять на диск.
 *
 * Используется в:
 * - Hydrator::hydrateProperties() - декодирует Base64-поля в DTO
 * - Тестах для проверки работы с Base64-файлами
 */
readonly class Base64File
{
    /**
     * @param string $base64 Base64-закодированная строка файла
     */
    public function __construct(
        private string $base64,
    ) {
    }

    /**
     * Декодирует Base64 и возвращает содержимое файла.
     *
     * @return string Декодированное содержимое файла
     */
    public function content(): string
    {
        return base64_decode($this->base64, true) ?: '';
    }

    /**
     * Возвращает содержимое файла как PSR-7 поток.
     *
     * @return StreamInterface PSR-7 поток с содержимым файла
     */
    public function stream(): StreamInterface
    {
        return Utils::streamFor($this->content());
    }

    /**
     * Возвращает размер декодированного файла в байтах.
     *
     * @return int Размер файла в байтах
     */
    public function size(): int
    {
        return strlen($this->content());
    }

    /**
     * Сохраняет декодированный файл на диск.
     *
     * @param string $path Путь для сохранения файла
     * @throws ConfigurationException Если не удалось сохранить файл
     */
    public function saveTo(string $path): void
    {
        $resource = fopen($path, 'wb');
        if ($resource === false) {
            throw new ConfigurationException(new Message('vo.failed_to_save_file', ['path' => $path]));
        }

        fwrite($resource, $this->content());
        fclose($resource);
    }
}
