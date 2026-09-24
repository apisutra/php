<?php

declare(strict_types=1);

namespace ApiSutra\Diagnostics;

use ApiSutra\Contracts\Interfaces\Diagnostics\SensitiveFieldsProviderInterface;
use ApiSutra\VO\Http\PreparedRequest;

/** @internal Единый список для exporters, recordings и результатов. */
final class SensitiveFields
{
    /** @return list<string> */
    public static function of(?PreparedRequest $request): array
    {
        $fields = $request?->meta['credentialsEnrichment']['secretKeys'] ?? [];
        $local = $request?->meta['sensitiveFields'] ?? [];
        return array_values(array_unique(array_filter([
            ...(is_array($fields) ? $fields : []),
            ...(is_array($local) ? $local : []),
        ], is_string(...))));
    }

    public static function policy(RedactionPolicy $policy, ?PreparedRequest $request): RedactionPolicy
    {
        $fields = self::of($request);
        return $fields === [] ? $policy : $policy->withFields($fields);
    }

    /** @return list<string> */
    public static function declared(object $request): array
    {
        return $request instanceof SensitiveFieldsProviderInterface ? $request->sensitiveFields() : [];
    }
}
