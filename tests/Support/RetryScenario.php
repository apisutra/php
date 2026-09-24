<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Hooks\HookRegistry;
use ApiSutra\Pipeline\Auth\AuthHandler;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\Pipeline\Error\ErrorPolicy;
use ApiSutra\Pipeline\Hooks\HookRunner;
use ApiSutra\Pipeline\Transport\RetrySender;
use ApiSutra\RateLimiting\RateLimiter;
use ApiSutra\Retry\RetryAfterDelay;
use ApiSutra\Retry\RetryDelayCalculator;
use ApiSutra\Timing\ExecutionBudget;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\RateLimiting\Cooldown\Backends\LocalCooldownBackend;
use ApiSutra\Tests\Stubs\Retry\ConsumingHttpClient;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Message\ResponseInterface;
use ApiSutra\Tests\Stubs\Dto\TokenResponseDto;
use Throwable;

final readonly class RetryScenario
{
    public ConsumingHttpClient $http;
    public FakeSleeper $sleeper;
    public PipelineContext $context;
    public HookRegistry $hooks;
    private RetrySender $sender;

    /** @param list<ResponseInterface|Throwable> $results */
    public function __construct(ClientConfig $config, RequestInterface $request, PreparedRequest $prepared, array $results)
    {
        $this->http = new ConsumingHttpClient($results);
        $factory = new HttpFactory();
        $transport = new HttpTransport($this->http, $factory, $factory);
        $clock = new VirtualClock();
        $this->sleeper = new FakeSleeper($clock);
        $this->context = new PipelineContext($request, $config, 'fixture-trace', preparedRequest: $prepared);
        $this->context->executor = new RecordingClientExecutor(new TokenResponseDto('fixture'));
        $this->context->budget = new ExecutionBudget($clock, $config->retry?->totalTimeoutMs);
        $this->hooks = new HookRegistry();
        $this->sender = new RetrySender(
            $config,
            $transport,
            new RetryDelayCalculator(),
            new RateLimiter(),
            new HookRunner($this->hooks),
            new AuthHandler($config),
            new ErrorPolicy(),
            new AuditLogger($config),
            sleeper: $this->sleeper,
            cooldownBackend: new LocalCooldownBackend($clock),
            retryAfterDelay: new RetryAfterDelay(static fn (): int => 1_800_000_000),
        );
    }

    public function run(): ProviderResponse
    {
        return $this->sender->sendWithRetry($this->context->request, $this->context);
    }
}
