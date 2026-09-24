<?php

declare(strict_types=1);

namespace ApiSutra\Tooling\Generation;

use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Localization\Message;

/** Тонкий standalone CLI: только ввод, общий generator и сообщение результата. */
final class GenerationCommand
{
    public const string HELP = 'Usage: apisutra make:client|make:request|make:dto Name [--project=DIR] [--namespace=Root] [--directory=DIR] [--endpoint=/path] [--dto=Class]';

    /** @param list<string> $arguments */
    public function execute(array $arguments, string $project): string
    {
        if ($arguments === [] || in_array('--help', $arguments, true)) {
            return self::HELP;
        }
        $command = array_shift($arguments);
        $kind = match ($command) {
            'make:client' => GenerationKind::Client, 'make:request' => GenerationKind::Request, 'make:dto' => GenerationKind::Dto,
            default => throw new ConfigurationException(new Message('generation.invalid_arguments')),
        };
        $name = array_shift($arguments);
        if ($name === null) {
            throw new ConfigurationException(new Message('generation.invalid_arguments'));
        }
        $options = [];
        while ($arguments !== []) {
            $argument = array_shift($arguments);
            if (!str_starts_with($argument, '--')) {
                throw new ConfigurationException(new Message('generation.invalid_arguments'));
            }
            $pair = explode('=', substr($argument, 2), 2);
            $key = $pair[0];
            $value = $pair[1] ?? array_shift($arguments);
            if (!in_array($key, ['project', 'namespace', 'directory', 'endpoint', 'dto'], true) || $value === null || $value === '' || isset($options[$key])) {
                throw new ConfigurationException(new Message('generation.invalid_arguments'));
            }
            $options[$key] = $value;
        }
        $root = NamespaceRoot::resolve($options['project'] ?? $project, $options['namespace'] ?? null, $options['directory'] ?? null);
        return new ClassGenerator()->generate($kind, $name, $root, $options['endpoint'] ?? null, $options['dto'] ?? null);
    }
}
