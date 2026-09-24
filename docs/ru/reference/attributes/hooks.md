<!-- languages --> <a href="../../../en/reference/attributes/hooks.md">English</a> · <a href="hooks.md">Русский</a> <!-- /languages -->
# Хуки и точки вызова <a id="section-1"></a>

## Сигнатуры и targets <a id="section-2"></a>

Имена классов относятся к `ApiSutra\Attributes\Hooks`.
Сигнатуры показывают параметры и defaults конструктора; target указывает допустимое
место атрибута. Поведение и приоритеты описаны в тематических ссылках ниже.

| Атрибут | Target | Конструктор |
| --- | --- | --- |
| AfterHydrate | CLASS / REPEATABLE | `AfterHydrate(string $handler, HookPriority $priority = HookPriority::Normal, ?string $name = null)` |
| AfterResponse | CLASS / REPEATABLE | `AfterResponse(string $handler, HookPriority $priority = HookPriority::Normal, ?string $name = null)` |
| BeforeHydrate | CLASS / REPEATABLE | `BeforeHydrate(string $handler, HookPriority $priority = HookPriority::Normal, ?string $name = null)` |
| BeforeSend | CLASS / REPEATABLE | `BeforeSend(string $handler, HookPriority $priority = HookPriority::Normal, ?string $name = null)` |

Атрибуты для подключения hook‑обработчиков к жизненному циклу запроса.
Все атрибуты повторяемые (`IS_REPEATABLE`).

## Когда использовать <a id="section-3"></a>
- **BeforeSend** — добавить/изменить заголовки, trace‑id, подписи.
- **AfterResponse** — логирование, метрики, аудит.
- **BeforeHydrate** — нормализация данных до DTO.
- **AfterHydrate** — пост‑обработка DTO.

Подробный гайд по централизованным хукам: `docs/ru/reference/extensions/hooks.md`.

## BeforeSend <a id="section-4"></a>
**Параметры:**
- `handler: string` — класс обработчика
- `priority: HookPriority = Normal`
- `name?: string` — имя для защиты от дубликатов

## AfterResponse <a id="section-5"></a>
**Параметры:** как у `BeforeSend`

## BeforeHydrate <a id="section-6"></a>
**Параметры:** как у `BeforeSend`

## AfterHydrate <a id="section-7"></a>
**Параметры:** как у `BeforeSend`

Пример:
```php
use ApiSutra\Attributes\Hooks\AfterResponse;
use ApiSutra\Attributes\Hooks\BeforeSend;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Hooks\HookPriority;

#[BeforeSend(AddTraceHook::class, priority: HookPriority::First, name: 'trace')]
#[AfterResponse(LogResponseHook::class)]
final class SomeRequest extends AbstractRequest {}
```
