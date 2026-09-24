<?php

declare(strict_types=1);

namespace ApiSutra\Auth\OAuth2;

enum OAuth2FailureReason: string
{
    case InvalidCallback = 'oauth2_invalid_callback';
    case AuthorizationRequired = 'oauth2_authorization_required';
    case RefreshOutcomeUnknown = 'oauth2_refresh_outcome_unknown';
    case TokenPersistenceFailed = 'oauth2_token_persistence_failed';
}
