<!-- languages --> <a href="../../en/glossary/results.md">English</a> · <a href="results.md">Русский</a> <!-- /languages -->
# Результаты и ошибки <a id="section-1"></a>

| Термин | Значение | Подробнее |
| --- | --- | --- |
| <a id="resultinterface"></a> ResultInterface | Базовый интерфейс для всех результатов SDK. | [Контракт](../reference/results/handles.md) |
| <a id="resulthandle"></a> ResultHandle | Унифицированная обёртка результата выполнения запроса. | [Контракт](../reference/results/handles.md) |
| <a id="resolvedresultinterface"></a> ResolvedResultInterface | Контракт «приложенческого» результата. | [Контракт](../reference/results/handles.md) |
| <a id="resolvedresult"></a> ResolvedResult | Дефолтная реализация ResolvedResultInterface. | [Контракт](../reference/results/handles.md) |
| <a id="continuationtokenextractorinterface"></a> ContinuationTokenExtractorInterface | Контракт стратегии извлечения continuation token из ExecutionResult. | [Контракт](../reference/execution/continuation-await.md) |
| <a id="resultmetaextractorinterface"></a> ResultMetaExtractorInterface | Контракт стратегии извлечения provider envelope meta из `ExecutionResult`. | [Контракт](../reference/results/handles.md) |
| <a id="continuationservice"></a> ContinuationService | Ожидание финального результата с явным определением Pending/Ready/Failed. | [Контракт](../reference/execution/continuation-await.md) |
| <a id="continuationstateresolverinterface"></a> ContinuationStateResolverInterface | Контракт оценки ответа провайдера до преобразования финального DTO. | [Контракт](../reference/execution/continuation-state.md) |
| <a id="continuationcontext"></a> ContinuationContext | Контекст определителя готовности: финальный тип, unwrap, класс исходного запроса и режим. | [Контракт](../reference/execution/continuation-state.md) |
| <a id="continuationstate"></a> ContinuationState | Выбранное состояние: pending(), ready(payload, path) или failed(). | [Контракт](../reference/execution/continuation-state.md) |
| <a id="continuationstatus"></a> ContinuationStatus | Enum состояний Pending, Ready и Failed. | [Контракт](../reference/execution/continuation-state.md) |
| <a id="finalpathstateresolver"></a> FinalPathStateResolver | Определяет готовность по наличию ненулевого значения указанного пути. | [Контракт](../reference/execution/continuation-state.md) |
| <a id="continuationmode"></a> ContinuationMode | Режим Sync, Auto или Async; управляет стартом и ожиданием операции провайдера. | [Контракт](../reference/execution/continuation-state.md) |
| <a id="continuationawaitoptions"></a> ContinuationAwaitOptions | Настройки poll: интервал и максимальное число poll-запросов. | [Контракт](../reference/execution/continuation-await.md) |
| <a id="continuationoutcome"></a> ContinuationOutcome | Сохранённый итог ожидания с DTO, исходным payload, путём и последним результатом. | [Контракт](../reference/execution/continuation-await.md) |
| <a id="resolvedresultfactoryinterface"></a> ResolvedResultFactoryInterface | Фабрика для создания ResolvedResultInterface. | [Контракт](../reference/results/handles.md) |
| <a id="clientresponse"></a> ClientResponse | Value Object для клиентского ответа. | [Контракт](../reference/results/handles.md) |
| <a id="clientresponsefactoryinterface"></a> ClientResponseFactoryInterface | Контракт фабрики клиентского ответа. | [Контракт](../reference/results/handles.md) |
| <a id="clienterror"></a> ClientError | Value Object для ошибок клиентского ответа. | [Контракт](../reference/results/errors.md) |
| <a id="clienterrormapperinterface"></a> ClientErrorMapperInterface | Контракт стратегии маппинга RequestError → ClientError. | [Контракт](../reference/results/errors.md) |
| <a id="defaultclienterrormapper"></a> DefaultClientErrorMapper | Дефолтная стратегия маппинга ошибок. | [Контракт](../reference/results/errors.md) |
| <a id="clienterrormapperawareinterface"></a> ClientErrorMapperAwareInterface | Контракт фабрики, принимающей mapper ошибок. | [Контракт](../reference/results/errors.md) |
| <a id="errorcontextfactoryinterface"></a> ErrorContextFactoryInterface | Контракт фабрики типизированного контекста ошибки. | [Контракт](../reference/results/errors.md) |
| <a id="systemerrorcontextkeys"></a> SystemErrorContextKeys | Enum системных ключей error context: `traceId`, `httpStatus`, `requestClass`, `providerCode`. | [Контракт](../reference/results/observability.md) |
| <a id="executionresult"></a> ExecutionResult | Базовый класс результата, implements ResultInterface. | [Контракт](../reference/results/handles.md) |
| <a id="batchresult"></a> BatchResult | Extends ExecutionResult. | [Контракт](../reference/execution/batch-pool.md) |
| <a id="poolresult"></a> PoolResult | Extends ExecutionResult. | [Контракт](../reference/execution/batch-pool.md) |
| <a id="resultmeta"></a> ResultMeta | Интерфейс для метаданных результата. | [Контракт](../reference/results/handles.md) |
| <a id="providerresponse"></a> ProviderResponse | Value Object, инкапсулирующий ответ от внешнего API. | [Контракт](../reference/results/handles.md) |
| <a id="resultstatus"></a> ResultStatus | Итог исполнения: успешный, частично успешный или неуспешный. | [Контракт](../reference/results/handles.md) |
| <a id="requesterror"></a> RequestError | Value Object ошибки на уровне SDK. | [Контракт](../reference/results/errors.md) |
| <a id="errorcollection"></a> ErrorCollection | Типизированная коллекция ошибок. | [Контракт](../reference/results/errors.md) |
| <a id="sdkexception"></a> SdkException | Базовый класс всех исключений SDK. | [Контракт](../reference/results/errors.md) |
| <a id="controlflowexception"></a> ControlFlowException | Базовый класс внутренних исключений SDK. | [Контракт](../reference/results/errors.md) |
| <a id="retryableexception"></a> RetryableException | Extends ControlFlowException. | [Контракт](../reference/results/errors.md) |
| <a id="earlyreturnexception"></a> EarlyReturnException | Extends ControlFlowException. | [Контракт](../reference/results/errors.md) |
| <a id="connectionexception"></a> ConnectionException | Extends SdkException. | [Контракт](../reference/results/errors.md) |
| <a id="requestexception"></a> RequestException | Extends SdkException. | [Контракт](../reference/results/errors.md) |
| <a id="apiexception"></a> ApiException | Общий базовый класс ошибок API провайдера в конкретном SDK. | [Контракт](../reference/results/errors.md) |
| <a id="clientexception"></a> ClientException | Extends RequestException. | [Контракт](../reference/results/errors.md) |
| <a id="validationexception"></a> ValidationException | Extends SdkException. | [Контракт](../reference/results/errors.md) |
| <a id="validator"></a> Validator | Реализация ValidatorInterface. | [Контракт](../reference/client/validation.md) |
| <a id="paymentrequiredexception"></a> PaymentRequiredException | Extends ClientException. | [Контракт](../reference/results/errors.md) |
| <a id="requesttimeoutexception"></a> RequestTimeoutException | Extends ClientException. | [Контракт](../reference/results/errors.md) |
| <a id="unprocessableentityexception"></a> UnprocessableEntityException | Extends ClientException. | [Контракт](../reference/results/errors.md) |
| <a id="serverexception"></a> ServerException | Extends RequestException. | [Контракт](../reference/results/errors.md) |
| <a id="gatewaytimeoutexception"></a> GatewayTimeoutException | Extends ServerException. | [Контракт](../reference/results/errors.md) |
| <a id="configurationexception"></a> ConfigurationException | Extends SdkException. | [Контракт](../reference/results/errors.md) |
| <a id="continuationconfigurationexception"></a> ContinuationConfigurationException | Ошибка неполной или несовместимой декларации ожидания. | [Контракт](../reference/execution/continuation-await.md) |
| <a id="continuationawaitexception"></a> ContinuationAwaitException | Ошибка ожидания, включая неготовность по лимиту и сбой гидратации Ready; содержит последний результат. | [Контракт](../reference/execution/continuation-await.md) |
| <a id="hydrationexception"></a> HydrationException | Ошибка преобразования данных в DTO с причиной и путём; внешний набор добавляет sourcePath. | [Контракт](../reference/results/errors.md) |
| <a id="testingexception"></a> TestingException | Extends SdkException. | [Контракт](../reference/results/errors.md) |
| <a id="unmockedrequestexception"></a> UnmockedRequestException | Extends TestingException. | [Контракт](../reference/results/errors.md) |
| <a id="missingfixtureexception"></a> MissingFixtureException | Extends TestingException. | [Контракт](../reference/results/errors.md) |
| <a id="throw"></a> throw() | Метод ExecutionResult. | [Контракт](../reference/results/errors.md) |
| <a id="hasrequestfailed"></a> hasRequestFailed() | Метод AbstractClient и AbstractRequest для переопределения. | [Контракт](../reference/results/errors.md) |
| <a id="shouldretry"></a> shouldRetry() | Метод AbstractClient и AbstractRequest для переопределения. | [Контракт](../reference/results/errors.md) |
| <a id="getrequestexception"></a> getRequestException() | Метод AbstractClient и AbstractRequest для переопределения. | [Контракт](../reference/results/errors.md) |
| <a id="defaultrequestfailurepolicytrait"></a> DefaultRequestFailurePolicyTrait | Базовые protected-методы определения ошибки ответа и возможности повтора запроса. | [Контракт](../reference/results/errors.md) |

[Все термины](README.md).
