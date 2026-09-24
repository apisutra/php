<?php

declare(strict_types=1);

namespace ApiSutra\VO\Files;

use ApiSutra\Localization\Message;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Extensions\Archive\Response\ArchiveResponse;
use Psr\Http\Message\StreamInterface;
use ApiSutra\Files\DownloadManager;

/**
 * Value Object для работы с файловым ответом от провайдера.
 *
 * Представляет файл, полученный в ответе API (например, при скачивании документа).
 * Предоставляет методы для работы с содержимым файла: получение потока, содержимого,
 * определение типа файла и сохранение на диск.
 *
 * Используется в:
 * - ResponseHydrator::hydrateToFile() - создаёт из HTTP-ответа с файлом
 * - Контроллерах для возврата файловых ответов API
 * - Тестах для проверки скачивания файлов
 */
readonly class FileResponse
{
    /**
     * @param StreamInterface $stream PSR-7 поток с содержимым файла
     * @param string|null $filename Имя файла (может быть извлечено из Content-Disposition)
     * @param string|null $mimeType MIME-тип файла (из Content-Type)
     * @param int|null $size Размер файла в байтах (из Content-Length)
     * @param ClientConfig|null $config Конфигурация клиента (для работы с архивами)
     */
    public function __construct(
        private StreamInterface $stream,
        private ?string $filename = null,
        private ?string $mimeType = null,
        private ?int $size = null,
        private ?ClientConfig $config = null,
    ) {
    }

    /**
     * Возвращает PSR-7 поток с содержимым файла.
     *
     * @return StreamInterface Поток файла
     */
    public function stream(): StreamInterface
    {
        return $this->stream;
    }

    /**
     * Возвращает содержимое файла как строку.
     * Перематывает поток в начало перед чтением (если поддерживается).
     *
     * @return string Содержимое файла
     */
    public function content(): string
    {
        if ($this->stream->isSeekable()) {
            $this->stream->rewind();
        }

        return (string) $this->stream;
    }

    /**
     * Возвращает имя файла (если доступно).
     *
     * @return string|null Имя файла или null
     */
    public function filename(): ?string
    {
        return $this->filename;
    }

    /**
     * Возвращает MIME-тип файла (если доступен).
     *
     * @return string|null MIME-тип или null
     */
    public function mimeType(): ?string
    {
        return $this->mimeType;
    }

    /**
     * Возвращает размер файла в байтах (если доступен).
     *
     * @return int|null Размер файла или null
     */
    public function size(): ?int
    {
        return $this->size;
    }

    /**
     * Проверяет, является ли файл архивом (zip, tar, gzip).
     * Проверка выполняется по MIME-типу и magic bytes.
     *
     * @return bool true если файл — архив
     */
    public function isArchive(): bool
    {
        $mime = $this->mimeType ?? '';
        if (str_contains($mime, 'zip') || str_contains($mime, 'tar') || str_contains($mime, 'gzip')) {
            return true;
        }

        $magic = $this->readMagicBytes();
        return str_starts_with($magic, 'PK') || $magic === "\x1F\x8B";
    }

    /**
     * Преобразует файл в объект архива для работы с содержимым.
     *
     * @return ArchiveResponse Объект архива для извлечения файлов
     * @throws ConfigurationException Если файл не является архивом
     */
    public function asArchive(): ArchiveResponse
    {
        if (!$this->isArchive()) {
            throw new ConfigurationException(new Message('vo.response_is_not_an_archive'));
        }

        $content = $this->content();
        $format = $this->detectArchiveFormat($this->mimeType, $content);

        return new ArchiveResponse($content, $format, $this->config);
    }

    /**
     * Сохраняет содержимое файла на диск.
     *
     * @param string $path Путь для сохранения файла
     * @throws ConfigurationException Если не удалось сохранить файл
     */
    public function saveTo(string $path): void
    {
        DownloadManager::save($this->stream, new DownloadTarget($path, overwrite: true));
    }

    /** Явно закрывает общий поток FileResponse и raw response. */
    public function close(): void
    {
        $this->stream->close();
    }

    private function detectArchiveFormat(?string $mimeType, string $content): string
    {
        $mime = $mimeType ?? '';
        if (str_contains($mime, 'zip')) {
            return 'zip';
        }
        if (str_contains($mime, 'gzip')) {
            return 'tar.gz';
        }
        if (str_contains($mime, 'tar')) {
            return 'tar';
        }

        if (str_starts_with($content, 'PK')) {
            return 'zip';
        }

        return 'tar.gz';
    }

    private function readMagicBytes(): string
    {
        if ($this->stream->isSeekable()) {
            $position = $this->stream->tell();
            try {
                $this->stream->rewind();
                return $this->stream->read(4);
            } finally {
                $this->stream->seek($position);
            }
        }

        return '';
    }
}
