<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Errors;

use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Localization\Message;

enum ErrorCode: string
{
    case ExecutionError = 'execution_error';
    case FileTransferError = 'file_transfer_error';
    case HookError = 'hook_error';
    case ResponseDecodingError = 'response_decoding_error';
    case TransportError = 'transport_error';
    case InvalidRequest = 'invalid_request';
    case BadRequest = 'bad_request';
    case ClientError = 'client_error';

    case ConnectionFailed = 'connection_failed';
    case Timeout = 'timeout';
    case DnsError = 'dns_error';

    case Unauthorized = 'unauthorized';
    case Forbidden = 'forbidden';
    case NotFound = 'not_found';
    case ValidationFailed = 'validation_failed';
    case RateLimited = 'rate_limited';

    case ServerError = 'server_error';
    case BadGateway = 'bad_gateway';
    case ServiceUnavailable = 'service_unavailable';
    case GatewayTimeout = 'gateway_timeout';

    case ConfigurationError = 'configuration_error';
    case HydrationError = 'hydration_error';
    case SerializationError = 'serialization_error';
    case ExtensionError = 'extension_error';
    case RequestContractViolation = 'request_contract_violation';

    public static function fromHttpStatus(int $status): self
    {
        return match ($status) {
            400 => self::BadRequest,
            401 => self::Unauthorized,
            403 => self::Forbidden,
            404 => self::NotFound,
            408 => self::Timeout,
            422 => self::ValidationFailed,
            429 => self::RateLimited,
            502 => self::BadGateway,
            503 => self::ServiceUnavailable,
            504 => self::GatewayTimeout,
            default => $status >= 400 && $status < 500 ? self::ClientError : self::ServerError,
        };
    }

    public function title(?LocalizationConfig $localization = null): string
    {
        return match ($this) {
            self::ExecutionError => (new Message('errors.execution_error'))->render($localization),
            self::FileTransferError => (new Message('errors.file_transfer_error'))->render($localization),
            self::HookError => (new Message('errors.hook_error'))->render($localization),
            self::ResponseDecodingError => (new Message('errors.response_decoding_error'))->render($localization),
            self::TransportError => (new Message('errors.transport_error'))->render($localization),
            self::InvalidRequest => (new Message('errors.invalid_http_request'))->render($localization),
            self::BadRequest => (new Message('errors.bad_request'))->render($localization),
            self::ClientError => (new Message('errors.client_error'))->render($localization),

            self::ConnectionFailed => (new Message('errors.connection_failed'))->render($localization),
            self::Timeout => (new Message('errors.timeout'))->render($localization),
            self::DnsError => (new Message('errors.dns_error'))->render($localization),
            self::Unauthorized => (new Message('errors.unauthorized'))->render($localization),
            self::Forbidden => (new Message('errors.forbidden'))->render($localization),
            self::NotFound => (new Message('errors.not_found'))->render($localization),
            self::ValidationFailed => (new Message('errors.validation_failed'))->render($localization),
            self::RateLimited => (new Message('errors.rate_limit_exceeded'))->render($localization),
            self::ServerError => (new Message('errors.server_error'))->render($localization),
            self::BadGateway => (new Message('errors.bad_gateway'))->render($localization),
            self::ServiceUnavailable => (new Message('errors.service_unavailable'))->render($localization),
            self::GatewayTimeout => (new Message('errors.gateway_timeout'))->render($localization),
            self::ConfigurationError => (new Message('errors.configuration_error'))->render($localization),
            self::HydrationError => (new Message('errors.hydration_error'))->render($localization),
            self::SerializationError => (new Message('errors.serialization_error'))->render($localization),
            self::ExtensionError => (new Message('errors.extension_error'))->render($localization),
            self::RequestContractViolation => (new Message('errors.request_contract_violation'))->render($localization),
        };
    }
}
