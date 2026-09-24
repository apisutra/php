<?php

declare(strict_types=1);

namespace ApiSutra\Retry;

use ApiSutra\VO\Http\PreparedRequest;
use Throwable;

/** Состояние тела одного выполнения; SDK не закрывает и не буферизует поток. */
final class RequestBodyReplay
{
    private ?int $position = null;

    public function __construct(private readonly PreparedRequest $initial)
    {
        try {
            if ($initial->stream?->isSeekable()) {
                $this->position = $initial->stream->tell();
            }
        } catch (Throwable) {
            // Первая отправка допустима даже без возможности восстановить позицию.
        }
    }

    /** Возвращает причину отказа либо null при успешном восстановлении. */
    public function restore(PreparedRequest $request): ?string
    {
        if ($request->stream !== $this->initial->stream || $request->body !== $this->initial->body) {
            return 'body_changed';
        }
        if ($request->stream === null) {
            return null;
        }
        if ($this->position === null) {
            return 'body_not_replayable';
        }
        try {
            $request->stream->seek($this->position);
            if ($request->stream->tell() !== $this->position) {
                return 'body_rewind_failed';
            }
        } catch (Throwable) {
            return 'body_rewind_failed';
        }
        return null;
    }
}
