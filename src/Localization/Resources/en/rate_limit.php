<?php

declare(strict_types=1);

return [
    'rate_limit.cooldown_publication_unconfirmed' => 'Cooldown publication could not be confirmed; the original recording failure is preserved.',
    'rate_limit.invalid_cooldown_duration' => 'Cooldown duration must be positive and representable in milliseconds.',
    'rate_limit.invalid_cooldown_scope' => 'Redis cooldown scope must not be empty.',
    'rate_limit.cooldown_resource_missing' => 'The cooldown Lua resource is unavailable.',
    'rate_limit.cooldown_backend_failed' => 'The cooldown backend operation failed.',
    'rate_limit.cooldown_storage' => 'Cooldown backend operation.',
    'rate_limit.server_cooldown_active' => 'The server cooldown is active.',
    'rate_limit.invalid_cooldown_config' => 'Cooldown group and identity must not be empty; the additional wait limit must be nonnegative and representable.',
    'rate_limit.cooldown_identity_failed' => 'Unable to resolve the cooldown identity.',
    'rate_limit.cooldown_identity_unavailable' => 'Cooldown skipped: no stable authentication identity is available.',
    'rate_limit.cooldown_extended' => 'Server cooldown extended.',
    'rate_limit.cooldown_wait' => 'Waiting for retry or server cooldown.',
];
