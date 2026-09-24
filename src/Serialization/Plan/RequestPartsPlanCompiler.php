<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Plan;

use ApiSutra\Localization\Message;
use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\BodyRoot;
use ApiSutra\Attributes\Request\File;
use ApiSutra\Attributes\Request\Header;
use ApiSutra\Attributes\Request\Ignore;
use ApiSutra\Attributes\Request\Path;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Attributes\Request\RequestDefaults;
use ApiSutra\Enums\Request\RequestUnmappedTarget;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Metadata\MetadataCatalog;
use ApiSutra\Serialization\Concerns\ReflectionHelperTrait;

/** @internal Компилирует размещение, сохраняя порядок args → defaults → проверка BodyRoot. */
final readonly class RequestPartsPlanCompiler
{
    use ReflectionHelperTrait;

    private MetadataCatalog $catalog;

    public function __construct(private ?AttributeMetadataCache $cache, ?MetadataCatalog $catalog = null)
    {
        $this->catalog = $catalog ?? $cache?->catalog() ?? new MetadataCatalog();
    }

    public function bind(string $class): RequestPartsPlan
    {
        $key = $class . ':request-parts-plan';
        $cached = $this->cache?->get($key);
        if ($cached !== null) {
            return $cached['plan']->bind();
        }

        $description = $this->catalog->forClass($class);
        $fields = $templates = $recipes = $roots = [];
        $rootError = null;
        $cacheEnabled = $this->cache?->isEnabled() ?? false;
        foreach ($description->properties as $property) {
            $factories = [];
            $attrs = $this->getPropertyAttributes($property, [
                'ignore' => Ignore::class,
                'path' => Path::class,
                'query' => Query::class,
                'body' => Body::class,
                'bodyRoot' => BodyRoot::class,
                'header' => Header::class,
                'file' => File::class,
                'cast' => Cast::class,
            ], $factories);
            $name = $property->getName();
            if ($attrs['bodyRoot'] !== null) {
                $roots[] = $name;
                $rootError ??= match (true) {
                    !$property->isPublic(), $property->isStatic() =>
                        new Message('serialization.bodyroot_must_be_declared_on_a_public_non_static'),
                    $attrs['ignore'] !== null => new Message('serialization.bodyroot_cannot_be_combined_with_ignore', ['name' => $name]),
                    $attrs['path'] !== null, $attrs['query'] !== null, $attrs['body'] !== null =>
                        new Message('serialization.bodyroot_cannot_be_combined_with_path_query_body', ['name' => $name]),
                    $attrs['header'] !== null, $attrs['file'] !== null =>
                        new Message('serialization.bodyroot_cannot_be_combined_with_header_file', ['name' => $name]),
                    default => null,
                };
            }
            $target = match (true) {
                !$property->isPublic(), $property->isStatic(), $attrs['ignore'] !== null => RequestFieldTarget::Skip,
                $attrs['bodyRoot'] !== null => RequestFieldTarget::BodyRoot,
                $attrs['file'] !== null => RequestFieldTarget::File,
                $attrs['header'] !== null => RequestFieldTarget::Header,
                $attrs['path'] !== null => RequestFieldTarget::Path,
                $attrs['body'] !== null => RequestFieldTarget::Body,
                $attrs['query'] !== null => RequestFieldTarget::Query,
                default => RequestFieldTarget::Unmapped,
            };
            $field = new RequestFieldPlan(
                name: $name,
                target: $target,
                value: new SerializationValuePlan($property, $attrs['cast']),
                queryName: $attrs['query']?->name,
                queryFormat: $attrs['query']?->arrayFormat,
                queryNullable: $attrs['query']?->nullable,
                bodyNested: $attrs['body']?->nested,
                partName: match ($target) {
                    RequestFieldTarget::File => $attrs['file']->name ?? $name,
                    RequestFieldTarget::Header => $attrs['header']->name,
                    RequestFieldTarget::Path => $attrs['path']->name ?? $name,
                    default => null,
                },
                fileFormat: $attrs['file']?->format,
            );
            $index = count($fields);
            $fields[] = $field;
            if ($cacheEnabled) {
                // Из распознаваемых атрибутов только Cast допускает объектные args.
                $recipe = $factories['cast'] ?? null;
                $templates[] = $recipe === null ? $field : $field->withCast(null);
                if ($recipe !== null) {
                    $recipes[$index] = $recipe;
                }
            }
        }
        $declaration = $description->attributes(RequestDefaults::class)[0] ?? null;
        $unmapped = $declaration?->newInstance()->unmapped ?? RequestUnmappedTarget::Convention;
        if ($rootError !== null) {
            throw new ConfigurationException($rootError);
        }
        if (count($roots) > 1) {
            throw new ConfigurationException(new Message('serialization.more_than_one_bodyroot_found_in_the_request'));
        }
        $plan = new RequestPartsPlan($fields, $roots[0] ?? null, $unmapped);
        if ($cacheEnabled) {
            $this->cache->set($key, ['plan' => new RequestPartsPlan($templates, $plan->bodyRoot, $unmapped, $recipes)]);
        }
        return $plan;
    }
}
