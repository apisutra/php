<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Contracts\Interfaces\Diagnostics\SensitiveFieldsProviderInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Pipeline\Error\ErrorPolicy;
use ApiSutra\Pipeline\Result\ResultFactory;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Pipeline\PipelineContext;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use ApiSutra\Exceptions\Request\RequestException;
use ApiSutra\Localization\Message;

describe('ResultFactory', function () {
    it('маскирует подписанный URL в локализуемом сообщении выбранного исключения', function (): void {
        $request = new #[Get('/')] class extends AbstractRequest {
            protected function getRequestException(ProviderResponse $response): ?Throwable
            {
                return new RequestException(new Message('fixture.error', [
                    'url' => $response->request->url,
                    'detail' => new Message('fixture.detail', ['url' => $response->request->url]),
                ]), $response);
            }
        };
        $transport = new MockTransport();
        $transport->fake(['*' => MockResponse::serverError()]);
        $client = new TestClient(new ClientConfig(
            baseUrl: 'https://api.test',
            localization: new LocalizationConfig('en', ['en' => ['fixture.error' => 'Error at {url}: {detail}', 'fixture.detail' => 'Address {url}']]),
        ), $transport);
        $result = $client->send($request->withUrl('https://id.test/token?signature=private-signature'))->raw();
        expect($result->errors->first()->message)->toContain('[redacted]')->not->toContain('private-signature');
        $translated = $result->localized(new LocalizationConfig('ru', ['ru' => ['fixture.error' => 'Ошибка {url}: {detail}', 'fixture.detail' => 'Адрес {url}']]));
        expect($translated->errors->first()->message)->toContain('Ошибка')->not->toContain('private-signature');
    });

    it('sensitive fields не подменяют сообщение; явная политика ошибок имеет приоритет', function (bool $customMessage): void {
        $request = new #[Get('/')] class ($customMessage) extends AbstractRequest implements SensitiveFieldsProviderInterface {
            public function __construct(private bool $customMessage) {}
            public function sensitiveFields(): array { return ['personal_field']; }
            protected function getRequestException(ProviderResponse $response): ?Throwable
            {
                return $this->customMessage ? new RuntimeException('Application message') : null;
            }
        };
        $transport = new MockTransport();
        $transport->fake(['*' => new MockResponse(['message' => 'Provider message'], 503)]);
        $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test'), $transport);
        $result = $client->send($request)->raw();
        expect($result->errors->first()->message)->toBe($customMessage ? 'Application message' : 'Provider message')
            ->and($result->exception->getMessage())->toBe($result->errors->first()->message);
    })->with([false, true]);

    it('строит ошибку из ответа 404', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $request = new SimpleGetRequest('q');
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );
        $context->response = new ProviderResponse(
            status: 404,
            headers: ['Content-Type' => ['application/json']],
            body: json_encode(['message' => 'Not Found'], JSON_UNESCAPED_UNICODE) ?: '',
            request: new PreparedRequest(HttpMethod::GET, 'https://api.test'),
            duration: 0,
        );

        $factory = new ResultFactory(new ErrorPolicy());
        $result = $factory->buildFailedResult($request, $context, []);

        expect($result->status)->toBe(ResultStatus::FAILED);
        expect($result->errors->first()?->code)->toBe(ErrorCode::NotFound);
        expect($result->errors->first()?->message)->toBe('Not Found');
    });

    it('использует ServerError при отсутствии ответа', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $request = new SimpleGetRequest('q');
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $factory = new ResultFactory(new ErrorPolicy());
        $result = $factory->buildFailedResult($request, $context, []);

        expect($result->status)->toBe(ResultStatus::FAILED);
        expect($result->errors->first()?->code)->toBe(ErrorCode::ExecutionError);
    });
});
