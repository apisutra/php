<?php

declare(strict_types=1);

use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\ProviderB\Requests\ProviderBDownloadRequest;
use ApiSutra\Tests\Support\TestClientFactory;
use ApiSutra\VO\Files\FileResponse;

describe('Download response', function () {
    it('возвращает FileResponse для download-запроса', function () {
        $client = TestClientFactory::make([
            ProviderBDownloadRequest::class => MockResponse::make(
                'binary-content',
                200,
                ['Content-Type' => 'application/octet-stream'],
            ),
        ]);

        $request = new ProviderBDownloadRequest('op-1');
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->status)->toBe(ResultStatus::SUCCESS);
        expect($result->data)->toBeInstanceOf(FileResponse::class);
        expect($result->data->mimeType())->toBe('application/octet-stream');
        expect($result->data->size())->toBe(strlen('binary-content'));
    });
});
