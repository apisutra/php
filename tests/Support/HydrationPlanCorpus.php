<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\DefaultSpec;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use ApiSutra\Serialization\Rules\ValueShape;
use ApiSutra\Tests\Stubs\AttributeHydration\Envelope;
use ApiSutra\Tests\Stubs\AttributeHydration\Presence;
use ApiSutra\Tests\Stubs\MappingHydration\Marker;
use ApiSutra\Tests\Stubs\MappingHydration\PlainDto;
use ApiSutra\Tests\Stubs\MappingHydration\ProfiledDto;
use ApiSutra\Tests\Stubs\MappingHydration\Trace;
use Closure;
use Throwable;

/** Общий корпус для Pest и сравнения двух исходников; ожидания заданы вручную. */
final class HydrationPlanCorpus
{
    /** @var array<string, array{expected: mixed, actual: mixed}> */
    public array $observations = [];
    /** @var array<string, list<array<string, mixed>>> */
    public array $errors = [];

    public function run(): void
    {
        foreach ([false, true] as $enabled) {
            $cache = new AttributeMetadataCache($enabled);
            $prefix = $enabled ? 'on.' : 'off.';
            foreach ([false, true] as $enhanced) {
                $mode = $prefix . ($enhanced ? 'enhanced.' : 'legacy.');
                $hydrator = new Hydrator(new CastRegistry(), $cache, config: $enhanced ? new HydrationConfig() : null);
                foreach ([false, true] as $snake) {
                    Trace::$events = Trace::$arguments = [];
                    Trace::$snake = $snake;
                    $dto = $hydrator->hydrate([
                        'backup' => ['value' => 4], 'casted' => 5,
                        $snake ? 'display_name' : 'displayName' => '  ',
                    ], ProfiledDto::class);
                    $id = $mode . ($snake ? 'warm' : 'cold');
                    $this->record($id . '.values', [5, 6, $snake ? null : '  ', Marker::class], [$dto->count, $dto->casted, $dto->displayName, $dto->marker::class]);
                    $this->record($id . '.trace', ['computed', 'argument', 'profile-new', 'policy', 'casts', 'provider:Present', 'cast-new', 'cast:1', 'constructor-default', 'dto'], Trace::$events);
                    $this->record($id . '.args-released', [null], array_map(static fn ($ref): ?object => $ref->get(), Trace::$arguments));
                }
                foreach (['missing' => [], 'null' => ['payload' => ['value' => null], 'casted' => null, 'marker' => null]] as $state => $source) {
                    Trace::$events = [];
                    $dto = $hydrator->hydrate($source, ProfiledDto::class);
                    $this->record($mode . $state . '.values', [10, null, $state === 'missing'], [$dto->count, $dto->casted, $dto->marker instanceof Marker]);
                    $this->record($mode . $state . '.trace', ['computed', 'argument', 'profile-new', 'policy', 'casts', 'provider:' . ucfirst($state), ...($state === 'missing' ? ['constructor-default'] : []), 'dto'], Trace::$events);
                }
                $marker = new Marker();
                Trace::$events = [];
                $dto = $hydrator->hydrate(['marker' => $marker], ProfiledDto::class);
                $this->record($mode . 'supplied-default', [true, false], [$dto->marker === $marker, in_array('constructor-default', Trace::$events, true)]);
                Trace::$fail = true;
                Trace::$events = [];
                $this->failure($mode . 'failed-recipe', ['RuntimeException', 'argument failed'], fn () => $hydrator->hydrate([], ProfiledDto::class));
                $this->record($mode . 'failed-recipe.trace', ['computed', 'argument'], Trace::$events);
                Trace::$fail = false;
                Trace::$events = [];
                $this->record($mode . 'recovery', 3, $hydrator->hydrate(['casted' => 2], ProfiledDto::class)->casted);
                $this->failure($mode . 'provider-error', ['provider_failed', 'count'], fn () => $hydrator->hydrate(['backup' => ['value' => 'bad']], ProfiledDto::class));
            }
            $hydrator = new Hydrator(new CastRegistry(), $cache);
            $this->failure($prefix . 'required-before-default', ['required_field_missing', 'id'], fn () => $hydrator->hydrate([], Presence::class));
            $this->failure($prefix . 'null-before-default', ['explicit_null_not_allowed', 'stock'], fn () => $hydrator->hydrate(['old' => 7, 'stock' => null], Presence::class));
            $this->failure($prefix . 'projected-error', ['required_field_missing', 'items[0].id'], fn () => $hydrator->hydrate(['legacy' => [['value' => []]]], Envelope::class));
            $dto = $hydrator->hydrate(['legacy' => [['value' => ['id' => 7, 'unknown' => null], 'meta' => false]]], Envelope::class);
            $this->record($prefix . 'each-remainder', [7, ['unknown' => null], ['legacy' => [['sourceKey' => 0, 'remainder' => ['meta' => false]]]]], [$dto->items[0]->id, $dto->items[0]->_extra, $dto->_extra]);

            $rules = HydrationRules::create()->withDto(PlainDto::class, DtoRules::create()
                ->field('id', FieldRule::create()->from('wire', 'fallback')->required())
                ->field('rows', FieldRule::create()->shape(ValueShape::list(ValueShape::int(), normalizeKeys: true)))
                ->extras('_extra'));
            $strict = new Hydrator(new CastRegistry(), $cache, config: new HydrationConfig(policy: new RulePolicy(scalars: ScalarPolicy::Strict), rules: $rules));
            $legacy = new Hydrator(new CastRegistry(), $cache, config: new HydrationConfig(rules: $rules));
            for ($i = 0; $i < 2; $i++) {
                $this->record($prefix . 'shared.' . $i . '.legacy', 7, $legacy->hydrate(['wire' => '7'], PlainDto::class)->id);
                $this->failure($prefix . 'shared.' . $i . '.strict', ['invalid_field_type', 'id'], fn () => $strict->hydrate(['fallback' => '7'], PlainDto::class));
                $dto = $strict->hydrate(['wire' => 7, 'rows' => ['a' => 1, 'b' => 2], 'other' => true], PlainDto::class);
                $this->record($prefix . 'shared.' . $i . '.shape', [[1, 2], ['other' => true]], [$dto->rows, $dto->_extra]);
            }
            $rules = HydrationRules::create()->withDto(PlainDto::class, DtoRules::create()
                ->field('id', FieldRule::create()->noTransform()->default(DefaultSpec::value(9, ValueState::Null))));
            $identity = new Hydrator(new CastRegistry(), $cache, config: new HydrationConfig(rules: $rules));
            $this->record($prefix . 'identity-default', 9, $identity->hydrate(['id' => null], PlainDto::class)->id);
        }
        Trace::$events = Trace::$arguments = [];
        Trace::$snake = Trace::$fail = false;
    }

    private function record(string $id, mixed $expected, mixed $actual): void
    {
        $this->observations[$id] = ['expected' => $expected, 'actual' => $actual];
    }

    /** @param array{string, string} $expected */
    private function failure(string $id, array $expected, Closure $call): void
    {
        try {
            $call();
            $this->record($id, $expected, 'no exception');
        } catch (Throwable $exception) {
            $this->record($id, $expected, $exception instanceof HydrationException
                ? [$exception->reason, $exception->path] : [$exception::class, $exception->getMessage()]);
            for ($error = $exception; $error !== null; $error = $error->getPrevious()) {
                $this->errors[$id][] = ['class' => $error::class, 'message' => $error->getMessage(),
                    'context' => $error instanceof HydrationException ? $error->context() : [],
                    'log' => $error instanceof HydrationException ? $error->logContext() : []];
            }
        }
    }
}
