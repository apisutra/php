<?php

declare(strict_types=1);

namespace ApiSutra\VO\Http;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\ResponseDecodingException;
use ApiSutra\Support\ArrayPath;
use JsonException;
use Psr\Http\Message\StreamInterface;

/**
 * Value Object для HTTP-ответа от провайдера.
 *
 * Содержит полную информацию об ответе API: статус код, заголовки, тело ответа,
 * оригинальный запрос и время выполнения. Предоставляет методы для работы с ответом:
 * декодирование JSON, проверка статуса, получение заголовков.
 *
 * Используется в:
 * - Transport (HttpTransport, MockTransport) - создаётся после выполнения HTTP-запроса
 * - Pipeline handlers (AuthHandler, CacheManager, ErrorPolicy) - обрабатывают ответ
 * - ResponseHydrator - гидратирует ответ в DTO
 * - RetryDecisionMaker - принимает решение о повторе запроса
 * - ExtensionRegistry - определяет обработчик для Content-Type
 */
readonly class ProviderResponse
{
    /**
     * @param int $status HTTP статус код ответа (200, 404, 500 и т.д.)
     * @param array<string, array<int, string>> $headers HTTP заголовки (key => [values])
     * @param string $body Тело ответа (raw content)
     * @param PreparedRequest $request Оригинальный подготовленный запрос
     * @param float $duration Время выполнения запроса в миллисекундах
     */
    public function __construct(
        public int $status,
        public array $headers,
        public ?string $body,
        public PreparedRequest $request,
        public float $duration,
        public ?StreamInterface $stream = null,
    ) {
        if (($body === null) === ($stream === null)) {
            throw new ConfigurationException(new Message('vo.http_response_must_contain_a_string_or_a_stream'));
        }
    }

    /**
     * Декодирует JSON-тело ответа и возвращает данные.
     * Поддерживает извлечение вложенных полей через dot-нотацию.
     *
     * @param string|null $key Путь к полю через точку (например, 'data.user.name')
     * @return mixed Декодированные данные или null
     */
    public function json(?string $key = null): mixed
    {
        $data = json_decode($this->stringBody(), true, 512, JSON_BIGINT_AS_STRING);
        if ($key === null) {
            return $data;
        }

        return ArrayPath::getByPath($data, $key);
    }

    /** Строгий разбор JSON; вызывающая сторона выбирает политику пустого/raw ответа. */
    public function jsonStrict(?string $key = null): mixed
    {
        try {
            $data = json_decode($this->stringBody(), true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $exception) {
            throw new ResponseDecodingException(new Message('vo.failed_to_decode_response_json', ['value0' => $exception->getMessage()]), 0, $exception);
        }

        return $key === null ? $data : ArrayPath::getByPath($data, $key);
    }

    /** HTTP-ошибка сохраняет приоритет над невалидным или не-JSON телом ответа. */
    private function stringBody(): string
    {
        return $this->body ?? throw new ConfigurationException(new Message('vo.streaming_response_must_be_read_explicitly_through_stream'));
    }

    public function errorMessage(): string
    {
        $body = $this->body;
        if ($body === null && $this->stream?->isSeekable()) {
            $position = $this->stream->tell();
            try {
                $this->stream->rewind();
                $body = $this->stream->read(65537);
                if (strlen($body) > 65536 || !$this->stream->eof()) {
                    $body = null;
                }
            } finally {
                $this->stream->seek($position);
            }
        }
        $data = $body === null ? null : json_decode($body, true, 512, JSON_BIGINT_AS_STRING);
        $message = is_array($data) ? ($data['message'] ?? null) : null;
        return is_string($message) && $message !== '' ? $message : "HTTP {$this->status}";
    }

    /**
     * Проверяет, является ли ответ успешным (2xx).
     *
     * @return bool true если статус 200-299
     */
    public function isSuccess(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * Проверяет, является ли ответ клиентской ошибкой (4xx).
     *
     * @return bool true если статус 400-499
     */
    public function isClientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    /**
     * Проверяет, является ли ответ серверной ошибкой (5xx).
     *
     * @return bool true если статус 500+
     */
    public function isServerError(): bool
    {
        return $this->status >= 500;
    }

    /**
     * Возвращает значение конкретного заголовка (case-insensitive).
     *
     * @param string $name Имя заголовка
     * @return string|null Значение заголовка или null
     */
    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $values) {
            if (strcasecmp($key, $name) === 0) {
                return $values[0] ?? null;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function headers(): array
    {
        return $this->headers;
    }
}
