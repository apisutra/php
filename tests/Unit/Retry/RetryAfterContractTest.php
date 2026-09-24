<?php

declare(strict_types=1);

use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Pipeline\Error\ErrorPolicy;
use ApiSutra\Retry\RetryAfterDelay;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;

it('согласует Retry-After исключения и задержки на одних часах', function (?string $header, ?int $seconds): void {
    $clock = new VirtualClock();
    $policy = new ErrorPolicy(clock: $clock);
    $delay = new RetryAfterDelay($clock->unixTime(...));
    $response = new ProviderResponse(429, $header === null ? [] : ['Retry-After' => [$header]], '{}', new PreparedRequest(HttpMethod::GET, 'https://fixture.test'), 0);
    $exception = $policy->getRequestExceptionInternal(new RetryPolicyRequest(), $response);
    expect($exception->retryAfter)->toBe($seconds)->and($delay->forResponse($response))->toBe(($seconds ?? 0) * 1000);
})->with([
    [null, null], ['', null], ['garbage', null], ['-1', null], ['+1', null], ['1.5', null],
    ['999999999999999999999', null], ['0', 0], [' 00120 ', 120],
    ['Fri, 15 Jan 2027 08:02:00 GMT', 120],
    ['Friday, 15-Jan-27 08:02:00 GMT', 120],
    ['Fri Jan 15 08:02:00 2027', 120],
    ['Fri, 15 Jan 2027 07:59:00 GMT', 0],
    ['Fri, 32 Jan 2027 08:02:00 GMT', null],
]);
