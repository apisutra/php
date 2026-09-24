<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Serialization\Serializer;
use ApiSutra\Tests\Stubs\MappingHttp\BodyDefaultsRequest;
use ApiSutra\Tests\Stubs\MappingHttp\InvalidRootRequest;
use ApiSutra\Tests\Stubs\MappingHttp\RootRequest;
use ApiSutra\Tests\Stubs\MappingHttp\RoutingRequest;
use ApiSutra\Tests\Stubs\MappingHttp\Trace;
use ApiSutra\VO\Pipeline\PipelineContext;
use Throwable;

/** Корпус использует публичный фасад и запускается также на принятом commit до W. */
final class RequestPartsPlanCorpus
{
    /** @var array<string, array{expected: mixed, actual: mixed}> */
    public array $observations = [];

    public function run(): void
    {
        foreach ([false, true] as $cache) {
            $serializer = new Serializer(new CastRegistry(), new AttributeMetadataCache($cache));
            foreach ([0, 1] as $round) {
                $prefix = (int) $cache . ':' . $round . ':';
                foreach ([HttpMethod::GET, HttpMethod::POST] as $method) {
                    foreach ([false, true] as $placeholder) {
                        $id = $prefix . $method->value . ':' . (int) $placeholder;
                        $request = new RoutingRequest($method, $placeholder ? '/items/{id}/{query}/{body}' : '/items/{id}');
                        $query = $placeholder ? [] : ['q' => ['value' => 14, 'format' => null]];
                        if ($method === HttpMethod::GET) {
                            $query['free'] = ['value' => 16, 'format' => null];
                        }
                        $query['nullable'] = ['value' => null, 'format' => null];
                        $body = $placeholder ? [] : ['data' => ['value' => 15]];
                        if ($method === HttpMethod::POST) {
                            $body['free'] = 16;
                        }
                        $casts = $placeholder ? ['cast:free:1'] : ['cast:query:1', 'cast:body:1', 'cast:free:1'];
                        $parts = $this->execute($serializer, $request);
                        $this->check($id . ':parts', [$query, $body, ['X-Token' => '3'], false], $parts);
                        $this->check($id . ':trace', [...$this->args(), ...$casts], Trace::$events);
                        $this->check($id . ':released', 0, $this->retained());
                    }
                }
                $parts = $this->execute($serializer, new BodyDefaultsRequest());
                $this->check($prefix . 'class-default', [
                    ['q' => ['value' => 14, 'format' => null], 'nullable' => ['value' => null, 'format' => null]],
                    ['data' => ['value' => 15], 'free' => 16], ['X-Token' => '3'], false,
                ], $parts);
                Trace::$fail = 'path';
                try {
                    $this->check($prefix . 'args-error', ['LogicException', 'args:path'], $this->execute($serializer, new RoutingRequest()));
                    $this->check($prefix . 'args-order', ['new:ignored', 'new:header', 'new:path'], Trace::$events);
                } finally {
                    Trace::$fail = null;
                }
                $this->execute($serializer, new RoutingRequest());
                $this->check($prefix . 'recovery', [...$this->args(), 'cast:query:1', 'cast:body:1', 'cast:free:1'], Trace::$events);
                $this->check($prefix . 'invalid-root', [
                    'ApiSutra\\Exceptions\\Configuration\\ConfigurationException',
                    'BodyRoot cannot be combined with Ignore: root',
                ], $this->execute($serializer, new InvalidRootRequest()));
                // У дочернего класса Reflection сначала возвращает его поле, затем унаследованные.
                $this->check($prefix . 'invalid-root-trace', ['new:root', ...$this->args()], Trace::$events);
                $this->check($prefix . 'root-query', [
                    ['other' => ['value' => 8, 'format' => null]], ['id' => 7], [], true,
                ], $this->execute($serializer, new RootRequest()));
                $this->check($prefix . 'root-body-conflict', [
                    'ApiSutra\\Exceptions\\Configuration\\ConfigurationException',
                    'BodyRoot conflicts with a field included in the body by default: other',
                ], $this->execute($serializer, new RootRequest(method: HttpMethod::POST)));
                $this->check($prefix . 'root-placeholder', [
                    'ApiSutra\\Exceptions\\Configuration\\ConfigurationException',
                    'BodyRoot cannot be combined with Path/placeholder: root',
                ], $this->execute($serializer, new RootRequest(endpoint: '/root/{root}')));
            }
        }
    }

    /** @return array<mixed> */
    private function execute(Serializer $serializer, RequestInterface $request): array
    {
        Trace::$events = Trace::$arguments = [];
        try {
            $prepared = $serializer->serialize($request, new PipelineContext($request, new ClientConfig(baseUrl: 'https://parts.test'), 'parts'));
            $headers = $prepared->headers;
            unset($headers['Content-Type']);
            return [$prepared->meta['query'], $prepared->meta['body'], $headers, $prepared->meta['bodyIsRoot']];
        } catch (Throwable $error) {
            return [$error::class, $error->getMessage()];
        }
    }

    /** @return list<string> */
    private function args(): array
    {
        return ['new:ignored', 'new:header', 'new:path', 'new:query', 'new:body', 'new:free', 'new:file', 'new:static'];
    }

    private function retained(): int
    {
        return count(array_filter(Trace::$arguments, static fn ($reference): bool => $reference->get() !== null));
    }

    private function check(string $id, mixed $expected, mixed $actual): void
    {
        $this->observations[$id] = ['expected' => $expected, 'actual' => $actual];
    }
}
