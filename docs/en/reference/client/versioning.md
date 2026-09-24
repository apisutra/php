<!-- languages --> <a href="versioning.md">English</a> · <a href="../../../ru/reference/client/versioning.md">Русский</a> <!-- /languages -->
# Service versions <a id="section-1"></a>

A guide to designing API service versions in an ApiSutra provider, covering
architectural rules, DX patterns, and customization boundaries.

## Basic idea <a id="section-2"></a>
A version belongs to a **service**, not the entire provider.

If a service's global settings or contract change, represent the version as a separate
branch or separate service client.

## When a separate version is needed <a id="section-3"></a>
Create a version router if any of these change:
- `baseUrl`
- `auth`
- `pagination`
- `serialization`
- An endpoint DTO contract

Otherwise, one client with resources is usually sufficient.

## Recommended DX <a id="section-4"></a>
- Canonical: `->service()->v3()->resource()->method()`
- Alternative: `->service()->useVersion(ServiceVersion::V3)->resource()->method()`

Recommendations:
- Use canonical fluent style in business code.
- Use `useVersion(...)` for infrastructure or dynamic version selection.
- Use `serviceVersion(...)` only as a provider-specific API already adopted by the project.

## Minimal version router <a id="section-5"></a>
```php
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Core\AbstractResource;
use ApiSutra\Versioning\VersionedResourceTrait;

enum ServiceVersion: string
{
    case V2 = 'v2';
    case V3 = 'v3';
}

final class ServiceResource extends AbstractResource
{
    use VersionedResourceTrait;

    public function __construct(
        ClientInterface $client,
        private readonly string $version = 'v2',
    ) {
        parent::__construct($client);
    }

    public function v2(): self
    {
        return $this->v(ServiceVersion::V2);
    }

    public function v3(): self
    {
        return $this->v(ServiceVersion::V3);
    }

    public function useVersion(ServiceVersion $version): self
    {
        return $this->v($version);
    }

    public function resourceEntry(): AbstractResource
    {
        return $this->resourceByVersion([
            'v2' => V2\ResourceEntry::class,
            'v3' => V3\ResourceEntry::class,
        ]);
    }

    protected function currentVersionKey(): string
    {
        return $this->version;
    }

    protected function recreateWithVersion(string $version): static
    {
        return new self($this->client, $version);
    }
}
```

## Provider-level wrapper (recommended) <a id="section-6"></a>
For large providers, create a base class such as `AbstractVersionedResource` over
`VersionedResourceTrait` and place these there:
- Current version key storage.
- `currentVersionKey()` and `recreateWithVersion()`.
- Additional helpers, such as `requestBySingleVersion(...)`.

## VersionedResourceTrait behavior <a id="section-7"></a>
- Switches versions through `v(UnitEnum $version)`.
- Routes nested resources through `resourceByVersion(...)`.
- Routes requests through `requestByVersion(...)`.
- Throws `UnsupportedVersionException` for an unsupported version.

The trait is technical and optional; business methods stay in resources.

## Backward compatibility policy <a id="section-8"></a>
- The default version is part of the public SDK contract.
- Change the default only in a provider major release.
- Keep legacy methods in a separate `v1()/legacy()` branch or temporarily mark them
  Deprecated for an agreed period.

## Shared/ and V* boundary <a id="section-9"></a>
Put only invariant classes in `Shared/`:
- Identical DTO fields/types.
- Identical validation and `serialization`.
- Identical error/`pagination` semantics.
- No version-specific branches inside the class.

If a condition is not met, keep the class in V*.

## compat-default and strict explicit mode <a id="section-10"></a>
During migration, `compat-default` may route different methods in the default branch
to different versions, for example some to v2 and others to v3.

Rules:
- Explicit mode (`vX()` / `useVersion(...)`) forbids fallback.
- An unsupported route must end with `UnsupportedVersionException`.

## Test matrix <a id="section-11"></a>
Minimum checks for each versioned service:
- A successful contract case for V2 and V3.
- Router behavior: `service()` = default, `service()->v3()` = new version.
- Explicit selection through `useVersion(...)`.
- `UnsupportedVersionException` for an unsupported version.

## Antipatterns <a id="section-12"></a>
- A hidden environment version switch in production.
- Duplicating the entire `Resources` tree without extracting `Shared/`.
- Mixing methods from different versions in one resource class without explicit routing.
