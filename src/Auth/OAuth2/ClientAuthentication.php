<?php

declare(strict_types=1);

namespace ApiSutra\Auth\OAuth2;

enum ClientAuthentication: string
{
    case Basic = 'client_secret_basic';
    case Post = 'client_secret_post';
    case None = 'none';
}
