<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Enrichment;

use ApiSutra\Localization\Message;
use ApiSutra\Config\CredentialsEnrichmentConfig;
use ApiSutra\Config\CredentialsScopeConfig;
use ApiSutra\Attributes\Request\SkipCredentialsEnrichment;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Serialization\RequestPartsEnricherInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Enums\Http\FileFormat;
use ApiSutra\Enums\Request\CredentialsMergeMode;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Serialization\VO\RequestPartsBag;
use ApiSutra\Support\ArrayPath;
use ApiSutra\VO\Pipeline\PipelineContext;
use ReflectionClass;

final readonly class CredentialsEnricher implements RequestPartsEnricherInterface
{
    public function __construct(
        private CredentialsEnrichmentConfig $config,
    ) {
    }

    #[\Override]
    public function enrich(
        RequestInterface $request,
        RequestPartsBag $parts,
        ?PipelineContext $context = null,
    ): RequestPartsBag {
        $options = $context?->options;
        $enabledOverride = $options?->getCredentialsEnrichmentEnabledOverride();
        $isEnabled = $enabledOverride
            ?? ($this->config->enabled && !$this->requestSkipsEnrichment($request));

        if (!$isEnabled) {
            return $parts;
        }

        $scope = $this->resolveScope($request, $context);
        $scopeConfig = $this->resolveScopeConfig($scope);
        $mergeMode = $options?->getCredentialsMergeModeOverride()
            ?? $scopeConfig->mergeMode
            ?? $this->config->mergeMode;

        $bodyDefaults = $this->mergeDefaults($this->config->body, $scopeConfig->body ?? []);
        $queryDefaults = $this->mergeDefaults($this->config->query, $scopeConfig->query ?? []);
        $formDefaults = $this->mergeDefaults($this->config->form, $scopeConfig->form ?? []);

        $changed = false;
        $appliedBody = [];
        $appliedQuery = [];
        $appliedForm = [];

        $changed = $this->applyBodyDefaults($parts, $bodyDefaults, $mergeMode, $appliedBody) || $changed;
        $changed = $this->applyQueryDefaults($parts, $queryDefaults, $mergeMode, $appliedQuery) || $changed;
        $changed = $this->applyFormDefaults($parts, $formDefaults, $mergeMode, $appliedForm) || $changed;

        $parts->enrichment['credentials'] = [
            'applied' => $changed,
            'scope' => $scope,
            'mergeMode' => $mergeMode->value,
            'fields' => [
                'body' => $appliedBody,
                'query' => $appliedQuery,
                'form' => $appliedForm,
            ],
            'secretKeys' => $this->normalizeSecretKeys($this->config->secretKeys),
        ];

        return $parts;
    }

    private function requestSkipsEnrichment(RequestInterface $request): bool
    {
        if ($request instanceof AbstractRequest) {
            return $request->hasSkipCredentialsEnrichment();
        }

        $attributes = (new ReflectionClass($request))->getAttributes(SkipCredentialsEnrichment::class);

        return $attributes !== [];
    }

    private function resolveScope(RequestInterface $request, ?PipelineContext $context): ?string
    {
        $scope = $context?->options?->getCredentialsScopeOverride()
            ?? $context?->options?->getAuthScopeOverride();

        if ($scope !== null && trim($scope) !== '') {
            return $scope;
        }

        if ($request instanceof AbstractRequest) {
            return $request->getAuthScope();
        }

        return null;
    }

    private function resolveScopeConfig(?string $scope): ?CredentialsScopeConfig
    {
        if ($scope === null || trim($scope) === '') {
            return null;
        }

        $resolved = $this->config->scopes[$scope] ?? null;

        return $resolved instanceof CredentialsScopeConfig ? $resolved : null;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $scope
     * @return array<string, mixed>
     */
    private function mergeDefaults(array $base, array $scope): array
    {
        return array_replace_recursive($base, $scope);
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<int, string> $appliedPaths
     */
    private function applyBodyDefaults(
        RequestPartsBag $parts,
        array $defaults,
        CredentialsMergeMode $mergeMode,
        array &$appliedPaths,
    ): bool {
        if ($defaults === [] || $parts->bodyIsRoot) {
            return false;
        }

        $body = is_array($parts->body) ? $parts->body : [];
        $changed = false;

        foreach ($defaults as $path => $value) {
            if (!is_string($path) || trim($path) === '') {
                continue;
            }

            $pathChanged = $this->mergePathValue($body, $path, $value, $mergeMode, 'body');
            $changed = $pathChanged || $changed;
            if ($pathChanged) {
                $appliedPaths[] = $path;
            }
        }

        if ($changed) {
            $parts->body = $body;
        }

        return $changed;
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<int, string> $appliedKeys
     */
    private function applyQueryDefaults(
        RequestPartsBag $parts,
        array $defaults,
        CredentialsMergeMode $mergeMode,
        array &$appliedKeys,
    ): bool {
        if ($defaults === []) {
            return false;
        }

        $changed = false;
        foreach ($defaults as $key => $value) {
            if (!is_string($key) || trim($key) === '') {
                continue;
            }

            $existing = $parts->query[$key]['value'] ?? null;
            $exists = array_key_exists($key, $parts->query);
            if ($this->shouldSkipMerge($exists, $existing, $value, $mergeMode, 'query', $key)) {
                continue;
            }

            $parts->query[$key] = [
                'value' => $value,
                'format' => $parts->query[$key]['format'] ?? null,
            ];
            $changed = true;
            $appliedKeys[] = $key;
        }

        return $changed;
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<int, string> $appliedPaths
     */
    private function applyFormDefaults(
        RequestPartsBag $parts,
        array $defaults,
        CredentialsMergeMode $mergeMode,
        array &$appliedPaths,
    ): bool {
        if ($defaults === [] || $parts->fileFormat !== FileFormat::Multipart || $parts->bodyIsRoot) {
            return false;
        }

        $body = is_array($parts->body) ? $parts->body : [];
        $changed = false;

        foreach ($defaults as $path => $value) {
            if (!is_string($path) || trim($path) === '') {
                continue;
            }

            $pathChanged = $this->mergePathValue($body, $path, $value, $mergeMode, 'form');
            $changed = $pathChanged || $changed;
            if ($pathChanged) {
                $appliedPaths[] = $path;
            }
        }

        if ($changed) {
            $parts->body = $body;
        }

        return $changed;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function mergePathValue(
        array &$payload,
        string $path,
        mixed $value,
        CredentialsMergeMode $mergeMode,
        string $target,
    ): bool {
        $result = ArrayPath::getByPathWithStatus($payload, $path);
        $exists = $result->state !== ValueState::Missing;
        $current = $result->value;

        if ($this->shouldSkipMerge($exists, $current, $value, $mergeMode, $target, $path)) {
            return false;
        }

        ArrayPath::setByPath($payload, $path, $value);

        return true;
    }

    private function shouldSkipMerge(
        bool $exists,
        mixed $current,
        mixed $value,
        CredentialsMergeMode $mergeMode,
        string $target,
        string $key,
    ): bool {
        return match ($mergeMode) {
            CredentialsMergeMode::FillMissing => $exists,
            CredentialsMergeMode::Overwrite => false,
            CredentialsMergeMode::FailOnConflict => $this->failOnConflict($exists, $current, $value, $target, $key),
        };
    }

    private function failOnConflict(
        bool $exists,
        mixed $current,
        mixed $value,
        string $target,
        string $key,
    ): bool {
        if (!$exists) {
            return false;
        }

        if ($current === $value) {
            return true;
        }

        throw new ConfigurationException(new Message('serialization.credentials_enrichment_conflict_for_s_s_merge_mode_fail', ['target' => $target, 'key' => $key]));
    }

    /**
     * @param array<int, string> $keys
     * @return array<int, string>
     */
    private function normalizeSecretKeys(array $keys): array
    {
        $normalized = array_values(array_filter(
            array_map(static fn (string $key): string => strtolower(trim($key)), $keys),
            static fn (string $key): bool => $key !== '',
        ));

        return array_values(array_unique($normalized));
    }
}
