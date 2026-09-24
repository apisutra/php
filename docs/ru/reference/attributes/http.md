<!-- languages --> <a href="../../../en/reference/attributes/http.md">English</a> · <a href="http.md">Русский</a> <!-- /languages -->
# HTTP-атрибуты <a id="section-1"></a>

## Сигнатуры и targets <a id="section-2"></a>

Имена классов относятся к `ApiSutra\Attributes\Http`.
Сигнатуры показывают параметры и defaults конструктора; target указывает допустимое
место атрибута. Поведение и приоритеты описаны в тематических ссылках ниже.

| Атрибут | Target | Конструктор |
| --- | --- | --- |
| Delete | CLASS | `Delete(string $path)` |
| Get | CLASS | `Get(string $path)` |
| Patch | CLASS | `Patch(string $path)` |
| Post | CLASS | `Post(string $path)` |
| Put | CLASS | `Put(string $path)` |

Атрибуты, задающие HTTP‑метод и endpoint запроса. Применяются к классу запроса.

## Когда использовать <a id="section-3"></a>
- **Get** — безопасные чтения без сайд‑эффектов.
- **Post** — создание или сложные запросы с body.
- **Put/Patch** — обновление ресурса (полное/частичное).
- **Delete** — удаление или отмена.

## Get <a id="section-4"></a>
**Target:** class
**Параметры:** `path: string` — путь запроса
**Эффект:** метод GET + endpoint

Пример:
```php
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;

#[Get('/users/{id}')]
final class GetUser extends AbstractRequest {}
```

## Post <a id="section-5"></a>
**Target:** class
**Параметры:** `path: string`
**Эффект:** метод POST + endpoint

## Put <a id="section-6"></a>
**Target:** class
**Параметры:** `path: string`
**Эффект:** метод PUT + endpoint

## Patch <a id="section-7"></a>
**Target:** class
**Параметры:** `path: string`
**Эффект:** метод PATCH + endpoint

## Delete <a id="section-8"></a>
**Target:** class
**Параметры:** `path: string`
**Эффект:** метод DELETE + endpoint

## Примечания <a id="section-9"></a>
- Плейсхолдеры в `path` можно заполнять через `#[Path]`.
- Если имя свойства совпадает с `{id}`, `#[Path]` не требуется.
  Используйте `#[Path('id')]`, когда имя свойства другое
  или плейсхолдеров несколько.
