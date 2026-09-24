<!-- languages --> <a href="validation.md">English</a> · <a href="../../../ru/reference/client/validation.md">Русский</a> <!-- /languages -->
# Request input and DTO validation <a id="section-1"></a>

## Validation <a id="section-2"></a>
Use this to validate DTOs locally and obtain readable errors.
```php
use ApiSutra\Attributes\DataTransfer\Label;
use ApiSutra\Attributes\DataTransfer\Validate;

#[Label('Email')]
#[Validate('required|email', message: 'Некорректный email')]
public string $email;
```

Validation uses Validate and Label attributes and relies on Laravel Validation
(`Illuminate\Contracts\Validation\Factory`).

## Where validation runs <a id="section-3"></a>
- **Requests** — before HTTP is sent.
- **DTOs** — when `validate()` or `isValid()` is called.

## Attributes <a id="section-4"></a>
```php
use ApiSutra\Attributes\DataTransfer\Label;
use ApiSutra\Attributes\DataTransfer\Validate;

#[Label('Email')]
#[Validate('required|email', message: 'Некорректный email')]
public string $email;
```

## Connecting the validator <a id="section-5"></a>

With `apisutra/laravel` installed, the registered default provider supplies Laravel's `validator`. No need to pass the
factory again in ClientConfig. Requests and DTOs without Validate never access the
factory; the ordinary core and custom preflight work without Illuminate.

Declared Validate rules require a compatible `Illuminate\Contracts\Validation\Factory`.
If it is missing or the provider returns an unsuitable object, the SDK reports
`ConfigurationException`, or `configuration_error` before HTTP in the pipeline. A missing
engine does not count as successful validation. No new mandatory settings or Strict flags exist.

### Factory priority <a id="section-6"></a>

| Situation | Source |
| --- | --- |
| Sending a request | containerProvider from the executing client's configuration. |
| Manual validate()/isValid()/errors() on a bound request | containerProvider of the already bound client. |
| Explicit provider: in Validator::check()/validateOrThrow() | Supplied provider, above request binding and shared bootstrap. |
| No provider specified | Validator::useFactory(), then explicit/default registry provider. |

An explicit provider is used without a global factory fallback even if its
`validatorFactory()` returns null. Configure the factory in that selected provider.
Different clients may have different rules and messages: the SDK does not store
their factories globally.

An unbound request does not auto-resolve a client for manual validation. A standalone
DTO uses shared bootstrap or an explicit provider; it does not remember the client
that produced it. DTO validation does not run automatically after hydration.

### Standalone <a id="section-7"></a>

For Laravel rules, install Illuminate Validation and Translation components without
a Laravel application and configure a shared factory during application startup:

```php
use ApiSutra\VO\Validation\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

$factory = new Factory(new Translator(new ArrayLoader(), 'ru'));
// If necessary, add translations and the provider’s own rules.
Validator::useFactory($factory);
```

Installing packages alone does not create a configured `validator`: custom rules,
translations, and rules with external dependencies require appropriate factory
configuration. For separate client containers, provide the factory through their
`ContainerProviderInterface::validatorFactory()`.

To validate a DTO with another `provider:`

```php
use ApiSutra\VO\Validation\Validator;

// $dto is the DTO to validate; $provider is a configured ContainerProviderInterface.
$validation = Validator::check($dto, provider: $provider);
Validator::validateOrThrow($dto, provider: $provider);
```

`Validator::resetFactory()` explicitly clears only the shared factory set through
`useFactory()`. The explicit/default registry provider applies again after reset. This is
useful when ending a bootstrap context or in tests; ordinary client switching needs
no reset. `ContainerProviderRegistry::reset()` has its own scope and does not clear this factory.

## Request validation <a id="section-8"></a>
On failure:
- The request is **not sent**.
- An `ExecutionResult` with FAILED status is created.
- `validationErrors` contains a list of `ValidationError`.

## Custom preflight validation <a id="section-9"></a>

For checks beyond Validate, such as files, cross-field logic, or preflight before
serialization, implement `CustomValidatableRequestInterface`:

```php
use ApiSutra\Attributes\Request\File;
use ApiSutra\Contracts\Interfaces\Validation\CustomValidatableRequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\VO\Errors\ValidationError;
use ApiSutra\VO\Files\FileInput;

final class DocumentUploadRequest extends AbstractRequest implements CustomValidatableRequestInterface
{
    public function __construct(
        #[File]
        public ?FileInput $document = null,
    ) {}

    public function validateCustom(): array
    {
        if ($this->document === null) {
            return [];
        }
        $errors = [];
        // Check size, mime-type, damage, etc.
        if (!$this->isDocumentValid($this->document)) {
            $errors[] = new ValidationError(
                field: 'document',
                rule: 'file_valid',
                message: 'Документ повреждён или формат не поддерживается',
                input: $this->document->filename,
            );
        }
        return $errors;
    }
}
```

Pipeline order: attribute validation → `validateCustom()` → RequestContractValidator
→ serialization. Data errors are combined and returned through normal
`buildValidationFailure` without a provider HTTP call. If declared attribute checks
are unavailable, a configuration error stops execution before custom preflight,
composite, serialization, and HTTP.

For path-based file validation, use non-throwing `FileInput::tryFromPath()`; when it
returns null, add `ValidationError` instead of an early `ConfigurationException`.

## DTO validation <a id="section-10"></a>
```php
$dto = UserDto::from($data)->validate();   // throws ValidationException
$ok = $dto->isValid();                     // bool
$errors = $dto->errors();                  // array<ValidationError>
```

## Error distinctions <a id="section-11"></a>

| Result | Behavior |
| --- | --- |
| Data violates a working validator's rules | validation_failed, ValidationError list, no HTTP; manual isValid() returns false. |
| Rules declared but no compatible factory / factory retrieval failed | configuration_error, no HTTP or field errors; direct validate()/isValid()/errors() throw ConfigurationException. |
| No Validate | No factory required; custom preflight and request contracts still work. |

Declared rules require a compatible validator factory. An explicit client provider
takes precedence over the global factory. Validator also accepts a single argument.
In the pipeline, `raw()`/`resolved()` return the error;
`dataOrFail()`/`throwOnErrors` throw the corresponding exception.
Sync and promise APIs use one contract.

## Custom messages <a id="section-12"></a>
Two levels:
1) `message` in Validate.
2) `static validationMessages(): array` on the class, for many rules.

Use `message` for 1–2 simple property rules. `validationMessages()` is convenient for
covering several fields/rules in one place.

Example:
```php
public static function validationMessages(): array
{
    return [
        'email.required' => 'Email обязателен',
        'email.email' => 'Некорректный email',
    ];
}
```
