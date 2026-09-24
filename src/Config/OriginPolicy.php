<?php

declare(strict_types=1);

namespace ApiSutra\Config;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Http\Origin;

/** Разрешает явный выбор credentials для точных origin; сама авторизацию не включает. */
final readonly class OriginPolicy
{
    /** @var list<string> */
    private array $allowedOrigins;

    /** @param list<string> $allowedOrigins */
    public function __construct(array $allowedOrigins = [])
    {
        $normalized = [];
        foreach ($allowedOrigins as $origin) {
            if (!is_string($origin)) {
                throw new ConfigurationException(new Message('configuration.origin_policy_expects_a_list_of_origins'));
            }
            $parts = parse_url($origin);
            if (
                $parts === false || isset($parts['pass']) || isset($parts['user'])
                || isset($parts['query']) || isset($parts['fragment']) || ($parts['path'] ?? '') !== ''
            ) {
                throw new ConfigurationException(new Message('configuration.allowlist_entries_must_contain_only_scheme_host_and_port'));
            }
            $normalized[] = Origin::fromUrl($origin);
        }
        $this->allowedOrigins = array_values(array_unique($normalized));
    }

    public function allows(string $origin): bool
    {
        return in_array($origin, $this->allowedOrigins, true);
    }
}
