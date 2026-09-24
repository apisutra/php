<?php

declare(strict_types=1);

return [
    'generation.invalid_name' => 'Invalid PHP name: {name}.',
    'generation.explicit_root' => 'An explicit directory requires a non-empty namespace.',
    'generation.invalid_composer' => 'Cannot read PSR-4 configuration from composer.json.',
    'generation.ambiguous_root' => 'Choose one PSR-4 root with --namespace, or supply both --namespace and --directory.',
    'generation.outside_root' => 'The generated file would leave the selected directory.',
    'generation.cannot_create' => 'Cannot create {path}; it may already exist or be unwritable.',
    'generation.request_options' => 'Endpoint and DTO options are only supported for requests.',
    'generation.invalid_endpoint' => 'Endpoint must be a non-empty string without control characters.',
    'generation.missing_template' => 'The generation template is missing from the package.',
    'generation.invalid_arguments' => 'Invalid generation arguments. Run apisutra --help.',
];
