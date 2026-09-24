<!-- languages --> <a href="troubleshooting.md">English</a> · <a href="../../ru/guides/troubleshooting.md">Русский</a> <!-- /languages -->
# Troubleshooting by symptom <a id="section-1"></a>

Short solutions to common problems.

## No client is set for the request <a id="section-2"></a>
**Cause:** the request is sent without a client and without `ClientResolver`.
**Solution:** call `$request->setClient($client)` or configure a resolver through the container.

## Auth scope not found <a id="section-3"></a>
**Cause:** `#[AuthScope]` is used, but the scope is missing from `ClientConfig.authScopes`.
**Solution:** add the scope to the configuration or change the attribute.

## Auth is enabled but not configured <a id="section-4"></a>
**Cause:** `withAuth()`/`forceAuth()` was called or `AuthPolicy` was set, but `auth` was not configured.
**Solution:** set `ClientConfig.auth` or remove forced authentication.

## Unexpected DateTime/string union output <a id="section-5"></a>
**Symptom:** a `string|DateTimeInterface` field has an unexpected serialized representation.
**Cause:** union branch selection uses the runtime value rather than the first declared type.
`DateTimeCast::serialize()` accepts only `DateTimeInterface`; DX and wire serialization
can use different policies.
**Solution:** check the field type, `DateTimeFrom` / `DateTimeTo`, `DtoSerializationProfile`,
and, if needed, `ClientConfig.requestDateTime` / `wireBodySerializationPolicy`.

## The request does not support pagination <a id="section-6"></a>
**Cause:** `paginate()` was called on a request without `PaginableInterface`.
**Solution:** extend `AbstractPaginatedRequest`.

## Pagination DTO container does not implement the interface <a id="section-7"></a>
**Cause:** a paginated request declares `#[Returns]`, but the DTO does not implement
`PaginationItemsContainerInterface`.
**Solution:** implement the interface or remove `#[Returns]` from this request.

## Files are not downloading <a id="section-8"></a>
**Cause:** `#[Download]` is missing or the response is not a file response.
**Solution:** add `#[Download]` and use `FileResponse`.

## MultipartStream is unavailable <a id="section-9"></a>
**Cause:** `guzzlehttp/psr7`, required for multipart, is missing.
**Solution:** install `guzzlehttp/psr7`.

## DTO validation does not run <a id="section-10"></a>
**Cause:** no validator factory is available.
**Solution:** configure `ContainerProvider` or call `Validator::useFactory()`.

## Unmocked request in tests <a id="section-11"></a>
**Cause:** `preventStrayRequests()` is enabled and there is no fixture/mock.
**Solution:** add `fake()`/a fixture or disable the protection.

## Rate limits are not shared between processes <a id="section-12"></a>
**Cause:** the default backend belongs to the client instance.
**Solution:** for atomic shared accounting, connect a [Redis backend](../reference/integrations/redis.md)
with the same scope and server across workers. A PSR-16 store remains available for a
single quota, but its get/set operations do not guarantee atomicity.

In Laravel, package discovery loads the provider without requiring configuration publication.
Ordinary DI preserves values set on an SDK request; incoming HTTP data is transferred
through an explicit RequestFactory. User bindings take priority.
See [setup and testing](https://github.com/apisutra/laravel/blob/master/docs/en/guides/integration/laravel.md).
