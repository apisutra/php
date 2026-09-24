<?php

declare(strict_types=1);

namespace ApiSutra\Http;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\VO\Http\PreparedRequest;
use Throwable;

/** Проверяет явно заданный framing без чтения или перемотки тела. */
final class RequestBodyGuard
{
    public static function check(PreparedRequest $request): void
    {
        if ($request->body !== null && $request->stream !== null) {
            throw new ConfigurationException(new Message('http.http_body_cannot_contain_both_a_string_and_a'));
        }
        $lengths = [];
        $hasTransferEncoding = false;
        foreach ($request->headers as $name => $value) {
            if (strcasecmp($name, 'Content-Length') === 0) {
                $lengths[] = trim($value);
            } elseif (strcasecmp($name, 'Transfer-Encoding') === 0) {
                $hasTransferEncoding = true;
            }
        }
        if ($lengths === []) {
            return;
        }
        if ($hasTransferEncoding || count($lengths) !== 1 || !preg_match('/^[0-9]+$/D', $lengths[0])) {
            throw new ConfigurationException(new Message('http.invalid_request_content_length_transfer_encoding'));
        }

        $size = strlen($request->body ?? '');
        if ($request->stream !== null) {
            try {
                $size = $request->stream->getSize();
                if ($size !== null) {
                    $size = max(0, $size - $request->stream->tell());
                }
            } catch (Throwable) {
                // Неизвестную длину нельзя выяснять потреблением пользовательского потока.
                $size = null;
            }
        }
        if ($size !== null && (ltrim($lengths[0], '0') ?: '0') !== (string) $size) {
            throw new ConfigurationException(new Message('http.content_length_does_not_match_the_http_body_size'));
        }
    }
}
