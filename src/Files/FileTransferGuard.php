<?php

declare(strict_types=1);

namespace ApiSutra\Files;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Core\FileStreamingInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\VO\Files\FileTransferOptions;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

final class FileTransferGuard
{
    public static function checkCapability(object $sender, ?FileTransferOptions $options): void
    {
        if ($options === null || (!$options->upload && !$options->download)) {
            return;
        }
        if (!$sender instanceof FileStreamingInterface) {
            throw new ConfigurationException(new Message('files.does_not_support_filestreaminginterface', ['value0' => $sender::class]));
        }
        $sender->assertSupportsFileTransfer($options);
    }

    public static function checkContext(PipelineContext $context): void
    {
        if ($context->preparedRequest !== null && $context->fileTransfer !== null) {
            $context->preparedRequest = $context->preparedRequest->with(fileTransfer: $context->fileTransfer);
        }
    }

    public static function options(PreparedRequest $request): ?FileTransferOptions
    {
        if ($request->stream !== null && !$request->fileTransfer?->upload) {
            return new FileTransferOptions(true, $request->fileTransfer->download ?? false, $request->fileTransfer?->target);
        }
        return $request->fileTransfer ?? ($request->stream !== null ? new FileTransferOptions(upload: true) : null);
    }
}
