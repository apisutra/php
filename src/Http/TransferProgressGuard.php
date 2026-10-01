<?php

declare(strict_types=1);

namespace ApiSutra\Http;

use ApiSutra\Contracts\Interfaces\Core\TransferProgressInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Localization\Message;

/** @internal Единая проверка opt-in для транспорта и его адаптера. */
final class TransferProgressGuard
{
    public static function checkCapability(object $sender): void
    {
        if (!$sender instanceof TransferProgressInterface) {
            throw new ConfigurationException(new Message('transport.transfer_progress_unsupported', [
                'adapter' => $sender::class,
            ]));
        }
        $sender->assertSupportsTransferProgress();
    }
}
