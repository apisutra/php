<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\HttpClientOptionsInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\GuzzleHttpClient;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\VO\Http\TransportOptions;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

it('отказывает до HTTP при неподдерживаемом connect timeout', function (): void {
    $http = new class implements ClientInterface, HttpClientOptionsInterface {
        public int $calls = 0;
        public function assertSupportsTimeouts(TransportOptions $options): void
        {
            if ($options->connectTimeoutMs > 0) {
                throw new ConfigurationException('fixture: connectTimeout не поддерживается');
            }
        }
        public function sendWithOptions(RequestInterface $request, TransportOptions $options): ResponseInterface
        {
            $this->assertSupportsTimeouts($options->effective());
            return $this->sendRequest($request);
        }
        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            $this->calls++;
            return new Response(204);
        }
    };
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), new HttpTransport($http, $factory, $factory));
    $request = (new RetryPolicyRequest())->setClient($client);
    $result = $request->send()->raw();
    expect($result->errors->first()->code->value)->toBe('configuration_error')
        ->and($result->exception->getMessage())->toContain('connectTimeout')->and($http->calls)->toBe(0);
    expect($request->withTimeout(5, 0)->send()->raw()->isSuccess())->toBeTrue()->and($http->calls)->toBe(1);
});

it('не подменяет неизвестный Guzzle handler штатным и не заявляет его capability', function (): void {
    if (!extension_loaded('curl')) {
        $this->markTestSkipped('Штатный Guzzle-адаптер требует ext-curl');
    }
    expect(fn () => new GuzzleHttpClient(['handler' => static fn (): null => null]))
        ->toThrow(ConfigurationException::class, 'handler');
});
