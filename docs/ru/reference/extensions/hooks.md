<!-- languages --> <a href="../../../en/reference/extensions/hooks.md">English</a> · <a href="hooks.md">Русский</a> <!-- /languages -->
# Хуки и точки вызова <a id="section-1"></a>

Hooks — это централизованные обработчики этапов пайплайна. Они позволяют
добавлять поведение без изменения кода запросов или DTO.

## Типы хуков <a id="section-2"></a>
- `BeforeSend` — перед отправкой HTTP‑запроса
- `AfterResponse` — после получения ответа
- `BeforeHydrate` — перед гидрацией DTO (можно вернуть изменённые данные)
- `AfterHydrate` — после гидрации DTO

## HookRegistry: регистрация <a id="section-3"></a>
```php
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Enums\Hooks\HookPriority;
use ApiSutra\Hooks\HookRegistry;

$hooks = new HookRegistry(resolver: fn (string $class) => new $class());

// Глобальный хук
$hooks->on(Hook::BeforeSend, AddTraceHeader::class, priority: HookPriority::First, name: 'trace');

// Хук только для конкретного запроса
$hooks->on(Hook::AfterResponse, LogResponse::class, for: [GetUser::class]);

// Хук только для конкретного DTO
$hooks->on(Hook::BeforeHydrate, UnwrapData::class, forDto: UserDto::class);
```

### Имя и удаление <a id="section-4"></a>
`name` защищает от дубликатов и помогает удалить хук:
```php
$hooks->remove(Hook::BeforeSend, 'trace');
```

## Порядок исполнения <a id="section-5"></a>
Порядок для каждого этапа:
1) **Глобальные** хуки
2) **По запросу** (`for`)
3) **По DTO** (`forDto`)
4) **Атрибуты hooks** на запросе
5) **Методы запроса** (`beforeSend/afterResponse/beforeHydrate/afterHydrate`)

Приоритеты внутри группы: `First → Normal → Last`.

## BeforeHydrate: изменение данных <a id="section-6"></a>
`BeforeHydrate` может вернуть изменённые данные:
```php
use ApiSutra\Contracts\Interfaces\Hooks\BeforeHydrateHookInterface;
use ApiSutra\VO\Pipeline\PipelineContext;

final class UnwrapData implements BeforeHydrateHookInterface
{
    public function handle(PipelineContext $context): array
    {
        return $context->response?->json('data') ?? [];
    }
}
```

Если `BeforeHydrate` не задан — данные идут в гидрацию как есть.
В методе запроса `beforeHydrate()` по умолчанию просто возвращает входной массив.

Наблюдатель, возвращающий null, сохраняет исходные виды JSON-контейнеров. Массив
от обработчика или переопределённого метода запроса — новый PHP-вход, даже если
он равен исходному массиву. [Границы преобразований](../dto/scope.md#section-3).

Для успешного ответа без DTO hook вызывается только при данных-массиве. `null`,
скаляры JSON и `text/plain` проходят без `BeforeHydrate`; `AfterResponse` и
`AfterHydrate` сохраняются. Подробности и правила пустого тела — в
[контракте успешного ответа](../results/handles.md#section-3).

## Где атрибуты <a id="section-7"></a>
Атрибуты‑хуки описаны отдельно: [атрибуты хуков](../attributes/hooks.md).
