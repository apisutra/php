<?php

declare(strict_types=1);

use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\SerializationException;
use ApiSutra\Serialization\RequestUrlBuilder;
use ApiSutra\Enums\Http\QueryArrayFormat;

it('соединяет компоненты URL без потери query и base path', function (string $base, string $endpoint, string $expected): void {
    $url = (new RequestUrlBuilder())->buildUrl($base, $endpoint, [], ['page' => ['value' => 2]], null);
    expect($url)->toBe($expected);
})->with([
    ['https://api.test/v1', '/resources?fixed=1', 'https://api.test/v1/resources?fixed=1&page=2'],
    ['https://api.test/v1/?a=1&a=2#base', 'resources?x=%2F&x=+#fragment', 'https://api.test/v1/resources?a=1&a=2&x=%2F&x=+&page=2'],
    ['https://api.test/v1', '/resources#fragment', 'https://api.test/v1/resources?page=2'],
    ['https://api.test/v1', '/resources?page=1&', 'https://api.test/v1/resources?page=1&page=2'],
    ['https://api.test/v1?', 'resources?', 'https://api.test/v1/resources?page=2'],
]);

it('сохраняет boolean, нули и пустую строку в query', function (): void {
    $query = [];
    foreach (['yes' => true, 'no' => false, 'zero' => 0, 'text' => '0', 'empty' => '', 'null' => null] as $key => $value) {
        $query[$key] = ['value' => $value];
    }
    expect((new RequestUrlBuilder())->buildUrl('https://api.test', '/items', [], $query, null))
        ->toBe('https://api.test/items?yes=1&no=0&zero=0&text=0&empty=&null=');
});

it('отклоняет query структуры которые нельзя передать без потери ключей', function (array $value): void {
    expect(fn () => (new RequestUrlBuilder())->buildUrl('https://api.test', '/items', [], ['filter' => ['value' => $value]], null))
        ->toThrow(SerializationException::class);
})->with([[['state' => 'open']], [[2 => 'x']], [[['x']]]]);

it('отклоняет абсолютные endpoint до сборки адреса', function (string $endpoint): void {
    expect(fn () => (new RequestUrlBuilder())->buildUrl('https://api.test', $endpoint, [], [], null))
        ->toThrow(ConfigurationException::class);
})->with(['https://other.test/items', '//other.test/items', 'file:///tmp/fixture', 'mailto:fixture']);

it('не отправляет отсутствующий или пустой path параметр', function (mixed $id): void {
    expect(fn () => (new RequestUrlBuilder())->buildUrl('https://api.test', '/items/{id}', ['id' => $id], [], null))
        ->toThrow(SerializationException::class);
})->with([null, '', '.', '..']);

it('кодирует path значение ровно как исходный сегмент', function (string|int $id, string $expected): void {
    expect((new RequestUrlBuilder())->buildUrl('https://api.test', '/items/{id}', ['id' => $id], [], null))
        ->toBe('https://api.test/items/' . $expected);
})->with([[0, '0'], ['a/b +%', 'a%2Fb%20%2B%25'], ['%2F', '%252F'], ['мир', '%D0%BC%D0%B8%D1%80']]);

it('сохраняет порядок дубликаты и null внутри плоских списков', function (QueryArrayFormat $format, string $expected): void {
    $query = ['q' => ['value' => [false, true, 0, '0', '', null], 'format' => $format]];
    expect((new RequestUrlBuilder())->buildUrl('https://api.test', '/items', [], $query, null))->toBe('https://api.test/items?' . $expected);
})->with([
    [QueryArrayFormat::Brackets, 'q[]=0&q[]=1&q[]=0&q[]=0&q[]=&q[]='],
    [QueryArrayFormat::Indices, 'q[0]=0&q[1]=1&q[2]=0&q[3]=0&q[4]=&q[5]='],
    [QueryArrayFormat::Repeat, 'q=0&q=1&q=0&q=0&q=&q='],
    [QueryArrayFormat::Comma, 'q=0%2C1%2C0%2C0%2C%2C'],
]);

it('отклоняет неоднозначный элемент Comma', function (): void {
    expect(fn () => (new RequestUrlBuilder())->buildUrl('https://api.test', '/', [], ['q' => ['value' => ['a,b'], 'format' => QueryArrayFormat::Comma]], null))
        ->toThrow(SerializationException::class);
});

it('отклоняет неподдерживаемые значения query', function (mixed $value): void {
    expect(fn () => (new RequestUrlBuilder())->buildUrl('https://api.test', '/', [], ['q' => ['value' => $value]], null))
        ->toThrow(SerializationException::class);
})->with([INF, NAN, new stdClass()]);

it('ищет placeholder только в path и удаляет любой fragment', function (): void {
    $builder = new RequestUrlBuilder();
    expect($builder->extractPathParams('/items/{id}?q={literal}#anything else'))->toBe(['id' => null])
        ->and($builder->buildUrl('https://api.test', '/items/{id}?q=%7Bliteral%7D#anything else', ['id' => 0], [], null))
        ->toBe('https://api.test/items/0?q=%7Bliteral%7D');
});

it('отклоняет незаполненные и некорректные placeholders', function (string $endpoint): void {
    expect(fn () => (new RequestUrlBuilder())->buildUrl('https://api.test', $endpoint, [], [], null))->toThrow(SerializationException::class);
})->with(['/items/{id}', '/items/{invalid-name}', '/items/{']);
