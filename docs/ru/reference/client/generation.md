<!-- languages --> <a href="../../../en/reference/client/generation.md">English</a> · <a href="generation.md">Русский</a> <!-- /languages -->
# Генерация SDK-классов <a id="generation"></a>

Composer binary `vendor/bin/apisutra` создаёт заготовки клиента, GET-запроса и DTO ответа
по одному поставляемому набору шаблонов, без Laravel и dev-зависимостей:

```bash
vendor/bin/apisutra make:client CatalogClient
vendor/bin/apisutra make:dto Product
vendor/bin/apisutra make:request GetProduct --endpoint=/products/1 --dto='Acme\Sdk\Product'
```

Namespace и каталог берутся из non-dev PSR-4 в `composer.json` текущего проекта.
Единственный корень выбирается автоматически. При нескольких укажите `--namespace='Acme\Sdk'`;
неоднозначное соответствие каталогов требует одновременно `--namespace` и `--directory`.
Вложенное имя `'Requests\GetProduct'` создаёт соответствующие подкаталоги. Полное имя
внутри выбранного namespace тоже разрешено. Генератор не считает App обязательным
namespace и не навязывает всем SDK одну раскладку.

```bash
vendor/bin/apisutra make:client Client --project=/work/catalog
vendor/bin/apisutra make:dto Product --namespace='Acme\Catalog' --directory=/work/catalog/src
```

`--project` выбирает манифест другого проекта. Явные namespace/directory работают и без
манифеста. `--endpoint` и `--dto` доступны только запросу. Без них запрос использует
`/replace-me` и не объявляет DTO ответа. У сгенерированного DTO пока нет полей; добавьте
реальную схему и маппинг провайдера. Клиент принимает обычные ClientConfig и транспорт;
адрес и credentials задаёт автор SDK. Генерация не выводит контракт провайдера и не
импортирует OpenAPI/Postman.

Некорректные PHP-имена, выход из выбранного каталога (включая symlink), неоднозначные
корни и существующие файлы явно отклоняются. Force overwrite и перезаписи composer.json нет.
CLI выводит созданный путь; некорректный вызов завершается с кодом 1. `--help` показывает опции.
Binary и шаблоны намеренно входят и в production-установку vendor.

Тот же renderer и файловая политика используются в [Laravel Artisan](https://github.com/apisutra/laravel/blob/master/docs/ru/reference/integrations/generation.md).
