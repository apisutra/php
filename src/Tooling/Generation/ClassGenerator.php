<?php

declare(strict_types=1);

namespace ApiSutra\Tooling\Generation;

use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Localization\Message;

/** Общие renderer и запись нового файла для CLI и Laravel Artisan. */
final class ClassGenerator
{
    public function generate(GenerationKind $kind, string $name, NamespaceRoot $root, ?string $endpoint = null, ?string $dto = null): string
    {
        NamespaceRoot::validateName($name);
        NamespaceRoot::validateName($root->namespace);
        $relative = str_starts_with($name, $root->namespace . '\\') ? substr($name, strlen($root->namespace) + 1) : $name;
        $parts = explode('\\', $relative);
        $class = array_pop($parts);
        $namespace = $root->namespace . ($parts === [] ? '' : '\\' . implode('\\', $parts));
        $code = $this->render($kind, $namespace, $class, $endpoint, $dto);
        $base = $this->directory($root->directory);
        $parent = $base;
        foreach ($parts as $part) {
            $parent = $this->directory($parent . '/' . $part);
            if ($parent !== $base && !str_starts_with($parent, $base . DIRECTORY_SEPARATOR)) {
                throw new ConfigurationException(new Message('generation.outside_root'));
            }
        }
        $file = $parent . '/' . $class . '.php';
        if (file_exists($file) || is_link($file)) {
            throw new ConfigurationException(new Message('generation.cannot_create', ['path' => $file]));
        }
        $stream = @fopen($file, 'x');
        if ($stream === false) {
            throw new ConfigurationException(new Message('generation.cannot_create', ['path' => $file]));
        }
        try {
            if (fwrite($stream, $code) !== strlen($code)) {
                unlink($file);
                throw new ConfigurationException(new Message('generation.cannot_create', ['path' => $file]));
            }
        } finally {
            fclose($stream);
        }
        return $file;
    }

    private function render(GenerationKind $kind, string $namespace, string $class, ?string $endpoint, ?string $dto): string
    {
        if ($kind !== GenerationKind::Request && ($endpoint !== null || $dto !== null)) {
            throw new ConfigurationException(new Message('generation.request_options'));
        }
        if ($dto !== null) {
            NamespaceRoot::validateName($dto);
        }
        if ($endpoint !== null && ($endpoint === '' || preg_match('/[\x00-\x1F\x7F]/', $endpoint) === 1)) {
            throw new ConfigurationException(new Message('generation.invalid_endpoint'));
        }
        $template = file_get_contents(dirname(__DIR__, 3) . '/resources/stubs/' . $kind->value . '.stub');
        if ($template === false) {
            throw new ConfigurationException(new Message('generation.missing_template'));
        }
        $base = $this->alias('SdkBase', $class);
        $get = $this->alias('HttpGet', $class);
        $returns = $this->alias('ResponseType', $class);
        $response = $this->alias('MappedResponse', $class);
        return strtr($template, [
            '{{namespace}}' => $namespace, '{{class}}' => $class, '{{base}}' => $base, '{{get}}' => $get,
            '{{endpoint}}' => var_export($endpoint ?? '/replace-me', true),
            '{{responseImports}}' => $dto === null ? '' : "use ApiSutra\\Attributes\\Response\\Returns as $returns;\nuse " . $dto . " as $response;\n",
            '{{responseAttribute}}' => $dto === null ? '' : "#[$returns($response::class)]\n",
        ]);
    }

    private function alias(string $preferred, string $class): string
    {
        return strcasecmp($preferred, $class) === 0 ? $preferred . 'Base' : $preferred;
    }

    private function directory(string $path): string
    {
        if (!is_dir($path) && !@mkdir($path, 0777, true) && !is_dir($path)) {
            throw new ConfigurationException(new Message('generation.cannot_create', ['path' => $path]));
        }
        $resolved = realpath($path);
        if ($resolved === false) {
            throw new ConfigurationException(new Message('generation.cannot_create', ['path' => $path]));
        }
        return $resolved;
    }
}
