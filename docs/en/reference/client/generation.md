<!-- languages --> <a href="generation.md">English</a> · <a href="../../../ru/reference/client/generation.md">Русский</a> <!-- /languages -->
# Generate SDK classes <a id="generation"></a>

The Composer binary `vendor/bin/apisutra` creates client, GET request and response DTO
skeletons from one shipped template set, without Laravel or dev dependencies:

```bash
vendor/bin/apisutra make:client CatalogClient
vendor/bin/apisutra make:dto Product
vendor/bin/apisutra make:request GetProduct --endpoint=/products/1 --dto='Acme\Sdk\Product'
```

The current project's `composer.json` supplies the non-dev PSR-4 namespace and directory.
A single root is selected automatically. With several roots, select `--namespace='Acme\Sdk'`;
an ambiguous directory mapping requires both `--namespace` and `--directory`. Nested
names such as `'Requests\GetProduct'` create matching subdirectories. A qualified name
inside the selected namespace is accepted too. No assumption that the namespace is App
or that every SDK shares one layout is made.

```bash
vendor/bin/apisutra make:client Client --project=/work/catalog
vendor/bin/apisutra make:dto Product --namespace='Acme\Catalog' --directory=/work/catalog/src
```

`--project` selects another project's manifest. Explicit namespace/directory also work
without a manifest. `--endpoint` and `--dto` apply only to requests. Without them, a request
uses `/replace-me` and declares no response DTO. The generated DTO has no fields yet;
add the provider's actual schema and mapping. A client accepts the normal ClientConfig
and transport; address and credentials remain the SDK author's responsibility.
Generation does not infer a provider contract or import OpenAPI/Postman.

Invalid PHP names, traversal outside the chosen directory (including symlinks), ambiguous
roots and existing files are refused. There is no force overwrite or composer.json rewrite.
CLI prints the created path; invalid invocation exits with status 1. `--help` lists options.
Binary and templates intentionally ship in production vendor installations too.

The same renderer and file policy back [Laravel Artisan commands](https://github.com/apisutra/laravel/blob/master/docs/en/reference/integrations/generation.md).
