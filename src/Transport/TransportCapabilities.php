<?php

declare(strict_types=1);

namespace ApiSutra\Transport;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Core\TimeoutAwareTransportInterface;
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\VO\Http\TransportOptions;

final class TransportCapabilities
{
    public static function check(TransportInterface $transport, TransportOptions $options): void
    {
        if ($transport instanceof TimeoutAwareTransportInterface) {
            $transport->assertSupportsTimeouts($options);
        } elseif ($options->hasLimits()) {
            throw new ConfigurationException(new Message('transport.does_not_support_timeout_connecttimeout_deadline', ['value0' => $transport::class]));
        }
    }
}
