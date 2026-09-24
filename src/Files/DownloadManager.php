<?php

declare(strict_types=1);

namespace ApiSutra\Files;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Files\FileTransferException;
use ApiSutra\Timing\ExecutionBudget;
use ApiSutra\VO\Files\DownloadTarget;
use ApiSutra\VO\Http\ProviderResponse;
use Psr\Http\Message\StreamInterface;

final class DownloadManager
{
    public static function validate(?DownloadTarget $destination): void
    {
        if ($destination === null) {
            return;
        }
        if (is_string($destination->target)) {
            self::validatePath($destination->target, $destination->overwrite);
        } elseif ($destination->overwrite || !$destination->target->isWritable()) {
            throw new ConfigurationException(new Message('files.download_requires_a_writable_stream_without_overwrite'));
        }
    }

    public static function validatePath(string $path, bool $overwrite): void
    {
        if ($path === '' || str_contains($path, "\0") || preg_match('~^[a-z][a-z0-9+.-]*:~i', $path)) {
            throw new ConfigurationException(new Message('files.download_requires_a_local_file_path'));
        }
        clearstatcache(true, $path);
        if (is_link($path) || (file_exists($path) && (!$overwrite || !is_file($path)))) {
            throw new ConfigurationException(new Message('files.download_destination_already_exists_or_is_not_a_regular'));
        }
        if (!is_dir(dirname($path)) || !is_writable(dirname($path))) {
            throw new ConfigurationException(new Message('files.download_destination_directory_is_unavailable'));
        }
    }

    public static function temporary(?DownloadTarget $destination): TemporaryFileStream
    {
        self::validate($destination);
        return new TemporaryFileStream(is_string($destination?->target) ? dirname($destination->target) : null);
    }

    public static function deliver(ProviderResponse $response, ?DownloadTarget $target, ?ExecutionBudget $budget): void
    {
        if ($target === null) {
            return;
        }
        if (!$response->isSuccess() || $response->stream === null) {
            throw new FileTransferException('download_not_successful');
        }
        if (is_string($target->target) && $response->stream instanceof TemporaryFileStream) {
            $response->stream->rewind();
            $budget?->check('file_publish');
            $response->stream->publish($target->target, $target->overwrite);
            return;
        }
        self::save($response->stream, $target, $budget);
    }

    public static function save(StreamInterface $source, DownloadTarget $target, ?ExecutionBudget $budget = null): void
    {
        self::validate($target);
        if ($source->isSeekable()) {
            $source->rewind();
        }
        if (is_string($target->target)) {
            $temporary = self::temporary($target);
            try {
                StreamCopy::copy($source, $temporary, $budget);
                if ($source->isSeekable()) {
                    $source->rewind();
                }
                $budget?->check('file_publish');
                $temporary->publish($target->target, $target->overwrite);
            } finally {
                $temporary->close();
            }
        } else {
            if ($source === $target->target) {
                throw new ConfigurationException(new Message('files.download_source_and_destination_cannot_be_the_same_stream'));
            }
            try {
                StreamCopy::copy($source, $target->target, $budget);
            } finally {
                if ($source->isSeekable()) {
                    $source->rewind();
                }
            }
        }
    }
}
