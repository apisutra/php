<?php

declare(strict_types=1);

use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Extensions\TestResponseExtension;
use ApiSutra\Tests\Stubs\Requests\PlainRequest;
use ApiSutra\Tests\Support\TestClientFactory;

describe('Extension response handler', function () {
    it('перехватывает ответ и вызывается один раз при boot', function () {
        TestResponseExtension::reset();

        $client = TestClientFactory::make(
            [
                PlainRequest::class => MockResponse::success(['value' => 1]),
            ],
            [
                'extensions' => [new TestResponseExtension()],
            ],
        );

        $request = new PlainRequest('payload');
        $request->setClient($client);

        $first = $request->send()->raw();
        $second = $request->send()->raw();

        expect($first->data)->toBe(['handled' => true, 'status' => 200]);
        expect($second->data)->toBe(['handled' => true, 'status' => 200]);
        expect(TestResponseExtension::$bootCount)->toBe(1);
    });
});
