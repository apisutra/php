<?php

declare(strict_types=1);

use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Rules\SourceLocation;
use ApiSutra\Serialization\Rules\SourcePathKind;

it('сохраняет исходную причину без цепочки промежуточных путей и переводов', function (string $locale): void {
    $cause = new DomainException('synthetic private cause');
    $original = HydrationException::invalidValue('invalid_field_type', 'int', 'string', 'id', $cause);
    $originalMessage = $original->getMessage();
    $originalTrace = $original->getTrace();
    $location = new SourceLocation(
        ['private/key'], ['*'], SourcePathKind::Expected, ['/private~1key'], ['/*'],
    );
    $localization = new LocalizationConfig($locale);
    $error = $original;
    for ($i = 0; $i < 32; $i++) {
        $error = $error->withSource($location)->prependSourcePath('data')
            ->localized($localization)->prependPath('child');
    }
    $chain = [];
    for ($current = $error; $current !== null; $current = $current->getPrevious()) {
        $chain[] = $current;
    }

    expect(count($chain))->toBeLessThanOrEqual(4)
        ->and(in_array($original, $chain, true))->toBeTrue()
        ->and($chain[array_key_last($chain)])->toBe($cause)
        ->and($error->reason)->toBe('invalid_field_type')
        ->and($error->path)->toBe(str_repeat('child.', 32) . 'id')
        ->and($error->expected)->toBe('int')->and($error->actual)->toBe('string')
        ->and($error->sourcePath)->toBe('/data/private~1key')
        ->and($error->sourceCandidates)->toBe(['/data/private~1key'])
        ->and($error->sourcePathKind)->toBe(SourcePathKind::Expected)
        ->and($error->logContext()['sourcePath'])->toBe('/data/*')
        ->and($error->logContext()['sourceCandidates'])->toBe(['/data/*'])
        ->and($error->getMessage())->toStartWith($locale === 'ru' ? 'Некорректные данные' : 'Invalid data')
        ->and($original->path)->toBe('id')->and($original->sourcePath)->toBeNull()
        ->and($original->getMessage())->toBe($originalMessage)
        ->and($original->getTrace())->toBe($originalTrace)
        ->and($original->getPrevious())->toBe($cause);
})->with(['en', 'ru']);

it('оставляет независимые снимки диагностики при повторном использовании ошибки', function (): void {
    $original = HydrationException::invalidValue('invalid_field_type', 'int', 'string', 'id');
    $source = $original->withSource(new SourceLocation(['private/key'], ['*']));
    $first = $source->prependPath('left')->prependSourcePath('first');
    $second = $source->prependPath('right')->prependSourcePath('second');

    expect($first->path)->toBe('left.id')->and($first->sourcePath)->toBe('/first/private~1key')
        ->and($second->path)->toBe('right.id')->and($second->sourcePath)->toBe('/second/private~1key')
        ->and($source->path)->toBe('id')->and($source->sourcePath)->toBe('/private~1key')
        ->and($source->logContext()['sourcePath'])->toBe('/*')
        ->and($first->getPrevious())->toBe($original)->and($second->getPrevious())->toBe($original)
        ->and($original->getPrevious())->toBeNull()->and($original->sourcePath)->toBeNull();
});
