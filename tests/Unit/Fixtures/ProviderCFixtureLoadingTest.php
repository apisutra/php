<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\PaginationConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Tests\Stubs\Auth\RecordingAuthenticator;
use ApiSutra\Tests\Stubs\Pagination\TestItemCollection;
use ApiSutra\Tests\Stubs\ProviderC\Auth\ProviderCAuthPolicy;
use ApiSutra\Tests\Stubs\ProviderC\Dto\ProviderCReportResponseDto;
use ApiSutra\Tests\Stubs\ProviderC\Dto\ProviderCRoleDataItemDto;
use ApiSutra\Tests\Stubs\ProviderC\Dto\ProviderCSystemResponseDto;
use ApiSutra\Tests\Stubs\ProviderC\Enums\ProviderCReportStatus;
use ApiSutra\Tests\Stubs\ProviderC\Enums\ProviderCSystemStatus;
use ApiSutra\Tests\Stubs\ProviderC\Pagination\ProviderCPaginationMetaResolver;
use ApiSutra\Tests\Stubs\ProviderC\ProviderCStatusMap;
use ApiSutra\Tests\Stubs\ProviderC\Requests\ProviderCReportDownloadRequest;
use ApiSutra\Tests\Stubs\ProviderC\Requests\ProviderCReportJsonRequest;
use ApiSutra\Tests\Stubs\ProviderC\Requests\ProviderCReportJudgePreviewRequest;
use ApiSutra\Tests\Stubs\ProviderC\Requests\ProviderCReportJudgeRoleDataRequest;
use ApiSutra\Tests\Stubs\ProviderC\Requests\ProviderCSystemOrgCheckRequest;
use ApiSutra\Tests\Stubs\ProviderC\Requests\ProviderCSystemPeopleCheckRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Files\FileResponse;

describe('Provider C fixtures loading', function () {
    beforeEach(function () {
        $this->providerCConfig = new ClientConfig(
            baseUrl: 'https://provider.test',
            auth: new RecordingAuthenticator(),
            authPolicy: new ProviderCAuthPolicy(),
            paginationConfig: new PaginationConfig(
                pageParam: 'page',
                limitParam: 'rows',
                metaPath: 'response',
                itemsPath: 'response.result',
                metaResolver: ProviderCPaginationMetaResolver::class,
                itemsCollection: TestItemCollection::class,
            ),
            environment: Environment::Testing,
        );
    });
    it('загружает фикстуру системного запроса', function () {
        $transport = new MockTransport();
        $transport->loadFixtures(__DIR__ . '/../../Fixtures/provider-c');

        $config = $this->providerCConfig;
        $client = new TestClient($config, $transport);

        $request = new ProviderCSystemPeopleCheckRequest(
            token: 'token-1',
            lastName: 'Ivanov',
            firstName: 'Ivan',
            regions: '[77]',
            birthDate: '01.01.1990',
        );
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->data)->toBeInstanceOf(ProviderCSystemResponseDto::class);
        expect($result->data->status)->toBe(ProviderCSystemStatus::Ok);
        expect($result->data->uuid)->toBe('uuid-100');
    });

    it('обрабатывает отрицательные статусы из фикстур', function () {
        $transport = new MockTransport();
        $transport->loadFixtures(__DIR__ . '/../../Fixtures/provider-c');

        $config = $this->providerCConfig;
        $client = new TestClient($config, $transport);

        $orgRequest = new ProviderCSystemOrgCheckRequest(
            token: 'token-err',
            inn: '123',
        );
        $orgRequest->setClient($client);

        $orgResult = $orgRequest->send()->raw();

        expect($orgResult->data->status)->toBe(ProviderCSystemStatus::InsufficientFunds);
        expect(ProviderCStatusMap::mapSystem($orgResult->data->status))
            ->toBe(ResultStatus::FAILED);

        $reportRequest = new ProviderCReportJudgePreviewRequest(
            uuid: 'uuid-100',
            token: 'token-1',
        );
        $reportRequest->setClient($client);

        $reportRequest->withoutCache()->send()->raw();
        $reportRequest->withoutCache()->send()->raw();
        $third = $reportRequest->withoutCache()->send()->raw()->data;

        expect($third->status)->toBe(ProviderCReportStatus::TariffExpired);
        expect(ProviderCStatusMap::mapReport($third->status, $third->waitTime))
            ->toBe(ResultStatus::FAILED);
    });

    it('поддерживает последовательность wait → ready через фикстуры', function () {
        $transport = new MockTransport();
        $transport->loadFixtures(__DIR__ . '/../../Fixtures/provider-c');

        $config = $this->providerCConfig;
        $client = new TestClient($config, $transport);

        $request = new ProviderCReportJudgePreviewRequest(
            uuid: 'uuid-100',
            token: 'token-1',
        );
        $request->setClient($client);

        $first = $request->withoutCache()->send()->raw()->data;
        $second = $request->withoutCache()->send()->raw()->data;

        expect($first)->toBeInstanceOf(ProviderCReportResponseDto::class);
        expect($second)->toBeInstanceOf(ProviderCReportResponseDto::class);
        expect($first->status)->toBe(ProviderCReportStatus::Waiting);
        expect($second->status)->toBe(ProviderCReportStatus::Ready);
    });

    it('пагинирует role-data по фикстурам', function () {
        $transport = new MockTransport();
        $transport->loadFixtures(__DIR__ . '/../../Fixtures/provider-c');

        $config = $this->providerCConfig;
        $client = new TestClient($config, $transport);

        $request = new ProviderCReportJudgeRoleDataRequest(
            uuid: 'uuid-100',
            token: 'token-1',
        );
        $request->setClient($client);

        $result = $request->paginate()->withPerPage(2)->all();

        $items = $result->items();
        expect($items)->toBeInstanceOf(TestItemCollection::class);
        $itemsArray = $items->toArray();
        expect($itemsArray[0])->toBeInstanceOf(ProviderCRoleDataItemDto::class);
        expect($itemsArray)->toHaveCount(3);
    });

    it('обрабатывает download и json отчёты по фикстурам', function () {
        $transport = new MockTransport();
        $transport->loadFixtures(__DIR__ . '/../../Fixtures/provider-c');

        $config = $this->providerCConfig;
        $client = new TestClient($config, $transport);

        $download = new ProviderCReportDownloadRequest(
            uuid: 'uuid-100',
            format: 'pdf',
            token: 'token-1',
            reportName: 'org-print-form',
        );
        $download->setClient($client);

        $downloadResult = $download->send()->raw();
        expect($downloadResult->data)->toBeInstanceOf(FileResponse::class);
        expect($downloadResult->data->content())->toBe('file-content');

        $json = new ProviderCReportJsonRequest(
            uuid: 'uuid-100',
            format: 'json',
            token: 'token-1',
            reportName: 'org-print-form',
        );
        $json->setClient($client);

        $jsonResult = $json->send()->raw();
        expect($jsonResult->data)->toBe([
            'status' => 1,
            'response' => ['ok' => true],
        ]);
    });
});
