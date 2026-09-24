<?php

declare(strict_types=1);

namespace ApiSutra\Files;

use ApiSutra\Exceptions\Files\FileTransferException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Timing\ExecutionBudget;
use Psr\Http\Message\StreamInterface;
use Throwable;

final class StreamCopy
{
    public static function copy(StreamInterface $source, StreamInterface $sink, ?ExecutionBudget $budget = null): int
    {
        $written = 0;
        try {
            while (!$source->eof()) {
                $budget?->check('file_copy');
                $chunk = $source->read(65536);
                if ($chunk === '' && !$source->eof()) {
                    throw new FileTransferException('read_no_progress', $written, $written > 0);
                }
                for ($offset = 0, $length = strlen($chunk); $offset < $length;) {
                    $budget?->check('file_copy');
                    $count = $sink->write(substr($chunk, $offset));
                    if ($count <= 0 || $count > $length - $offset) {
                        throw new FileTransferException('write_no_progress', $written, $written > 0);
                    }
                    $written += $count;
                    $offset += $count;
                }
            }
            $budget?->check('file_copy');
            return $written;
        } catch (ExecutionDeadlineException $exception) {
            throw new ExecutionDeadlineException($exception->stage, $exception, $exception->response, $written, $written > 0);
        } catch (Throwable $exception) {
            throw new FileTransferException('copy', $written, $written > 0, $exception);
        }
    }
}
