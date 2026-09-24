<!-- languages --> <a href="results.md">English</a> · <a href="../../ru/glossary/results.md">Русский</a> <!-- /languages -->
# Results and errors <a id="section-1"></a>

| Term | Meaning | Details |
| --- | --- | --- |
| <a id="resultinterface"></a> ResultInterface | The base interface for all SDK results. | [Contract](../reference/results/handles.md) |
| <a id="resulthandle"></a> ResultHandle | A unified wrapper for a request execution result. | [Contract](../reference/results/handles.md) |
| <a id="resolvedresultinterface"></a> ResolvedResultInterface | The application-level result contract. | [Contract](../reference/results/handles.md) |
| <a id="resolvedresult"></a> ResolvedResult | The default implementation of ResolvedResultInterface. | [Contract](../reference/results/handles.md) |
| <a id="continuationtokenextractorinterface"></a> ContinuationTokenExtractorInterface | A strategy contract for extracting a continuation token from ExecutionResult. | [Contract](../reference/execution/continuation-await.md) |
| <a id="resultmetaextractorinterface"></a> ResultMetaExtractorInterface | A strategy contract for extracting provider envelope metadata from `ExecutionResult`. | [Contract](../reference/results/handles.md) |
| <a id="continuationservice"></a> ContinuationService | Waits for a final result with explicit Pending/Ready/Failed detection. | [Contract](../reference/execution/continuation-await.md) |
| <a id="continuationstateresolverinterface"></a> ContinuationStateResolverInterface | A contract for evaluating the provider's response before converting the final DTO. | [Contract](../reference/execution/continuation-state.md) |
| <a id="continuationcontext"></a> ContinuationContext | The readiness resolver context: final type, unwrap, original request class, and mode. | [Contract](../reference/execution/continuation-state.md) |
| <a id="continuationstate"></a> ContinuationState | The selected state: pending(), ready(payload, path), or failed(). | [Contract](../reference/execution/continuation-state.md) |
| <a id="continuationstatus"></a> ContinuationStatus | The Pending, Ready, and Failed state enum. | [Contract](../reference/execution/continuation-state.md) |
| <a id="finalpathstateresolver"></a> FinalPathStateResolver | Determines readiness by the presence of a non-null value at the specified path. | [Contract](../reference/execution/continuation-state.md) |
| <a id="continuationmode"></a> ContinuationMode | Sync, Auto, or Async mode; controls starting and waiting for a provider operation. | [Contract](../reference/execution/continuation-state.md) |
| <a id="continuationawaitoptions"></a> ContinuationAwaitOptions | Polling settings: interval and maximum number of poll requests. | [Contract](../reference/execution/continuation-await.md) |
| <a id="continuationoutcome"></a> ContinuationOutcome | The stored waiting outcome with the DTO, original payload, path, and last result. | [Contract](../reference/execution/continuation-await.md) |
| <a id="resolvedresultfactoryinterface"></a> ResolvedResultFactoryInterface | A factory for creating ResolvedResultInterface. | [Contract](../reference/results/handles.md) |
| <a id="clientresponse"></a> ClientResponse | A value object for a client response. | [Contract](../reference/results/handles.md) |
| <a id="clientresponsefactoryinterface"></a> ClientResponseFactoryInterface | The client response factory contract. | [Contract](../reference/results/handles.md) |
| <a id="clienterror"></a> ClientError | A value object for client response errors. | [Contract](../reference/results/errors.md) |
| <a id="clienterrormapperinterface"></a> ClientErrorMapperInterface | A strategy contract for mapping RequestError → ClientError. | [Contract](../reference/results/errors.md) |
| <a id="defaultclienterrormapper"></a> DefaultClientErrorMapper | The default error mapping strategy. | [Contract](../reference/results/errors.md) |
| <a id="clienterrormapperawareinterface"></a> ClientErrorMapperAwareInterface | A contract for a factory that accepts an error mapper. | [Contract](../reference/results/errors.md) |
| <a id="errorcontextfactoryinterface"></a> ErrorContextFactoryInterface | A factory contract for typed error context. | [Contract](../reference/results/errors.md) |
| <a id="systemerrorcontextkeys"></a> SystemErrorContextKeys | The enum of system error context keys: `traceId`, `httpStatus`, `requestClass`, `providerCode`. | [Contract](../reference/results/observability.md) |
| <a id="executionresult"></a> ExecutionResult | The base result class; implements ResultInterface. | [Contract](../reference/results/handles.md) |
| <a id="batchresult"></a> BatchResult | Extends ExecutionResult. | [Contract](../reference/execution/batch-pool.md) |
| <a id="poolresult"></a> PoolResult | Extends ExecutionResult. | [Contract](../reference/execution/batch-pool.md) |
| <a id="resultmeta"></a> ResultMeta | The interface for result metadata. | [Contract](../reference/results/handles.md) |
| <a id="providerresponse"></a> ProviderResponse | A value object that encapsulates an external API response. | [Contract](../reference/results/handles.md) |
| <a id="resultstatus"></a> ResultStatus | The execution outcome: successful, partially successful, or unsuccessful. | [Contract](../reference/results/handles.md) |
| <a id="requesterror"></a> RequestError | A value object for an SDK-level error. | [Contract](../reference/results/errors.md) |
| <a id="errorcollection"></a> ErrorCollection | A typed collection of errors. | [Contract](../reference/results/errors.md) |
| <a id="sdkexception"></a> SdkException | The base class for all SDK exceptions. | [Contract](../reference/results/errors.md) |
| <a id="controlflowexception"></a> ControlFlowException | The base class for internal SDK exceptions. | [Contract](../reference/results/errors.md) |
| <a id="retryableexception"></a> RetryableException | Extends ControlFlowException. | [Contract](../reference/results/errors.md) |
| <a id="earlyreturnexception"></a> EarlyReturnException | Extends ControlFlowException. | [Contract](../reference/results/errors.md) |
| <a id="connectionexception"></a> ConnectionException | Extends SdkException. | [Contract](../reference/results/errors.md) |
| <a id="requestexception"></a> RequestException | Extends SdkException. | [Contract](../reference/results/errors.md) |
| <a id="apiexception"></a> ApiException | The common base class for provider API errors in a specific SDK. | [Contract](../reference/results/errors.md) |
| <a id="clientexception"></a> ClientException | Extends RequestException. | [Contract](../reference/results/errors.md) |
| <a id="validationexception"></a> ValidationException | Extends SdkException. | [Contract](../reference/results/errors.md) |
| <a id="validator"></a> Validator | An implementation of ValidatorInterface. | [Contract](../reference/client/validation.md) |
| <a id="paymentrequiredexception"></a> PaymentRequiredException | Extends ClientException. | [Contract](../reference/results/errors.md) |
| <a id="requesttimeoutexception"></a> RequestTimeoutException | Extends ClientException. | [Contract](../reference/results/errors.md) |
| <a id="unprocessableentityexception"></a> UnprocessableEntityException | Extends ClientException. | [Contract](../reference/results/errors.md) |
| <a id="serverexception"></a> ServerException | Extends RequestException. | [Contract](../reference/results/errors.md) |
| <a id="gatewaytimeoutexception"></a> GatewayTimeoutException | Extends ServerException. | [Contract](../reference/results/errors.md) |
| <a id="configurationexception"></a> ConfigurationException | Extends SdkException. | [Contract](../reference/results/errors.md) |
| <a id="continuationconfigurationexception"></a> ContinuationConfigurationException | An error caused by an incomplete or incompatible await declaration. | [Contract](../reference/execution/continuation-await.md) |
| <a id="continuationawaitexception"></a> ContinuationAwaitException | An await error, including failure to become ready within the limit and Ready hydration failure; contains the last result. | [Contract](../reference/execution/continuation-await.md) |
| <a id="hydrationexception"></a> HydrationException | An error converting data into a DTO, with a cause and path; an external rule set adds sourcePath. | [Contract](../reference/results/errors.md) |
| <a id="testingexception"></a> TestingException | Extends SdkException. | [Contract](../reference/results/errors.md) |
| <a id="unmockedrequestexception"></a> UnmockedRequestException | Extends TestingException. | [Contract](../reference/results/errors.md) |
| <a id="missingfixtureexception"></a> MissingFixtureException | Extends TestingException. | [Contract](../reference/results/errors.md) |
| <a id="throw"></a> throw() | An ExecutionResult method. | [Contract](../reference/results/errors.md) |
| <a id="hasrequestfailed"></a> hasRequestFailed() | An overridable AbstractClient and AbstractRequest method. | [Contract](../reference/results/errors.md) |
| <a id="shouldretry"></a> shouldRetry() | An overridable AbstractClient and AbstractRequest method. | [Contract](../reference/results/errors.md) |
| <a id="getrequestexception"></a> getRequestException() | An overridable AbstractClient and AbstractRequest method. | [Contract](../reference/results/errors.md) |
| <a id="defaultrequestfailurepolicytrait"></a> DefaultRequestFailurePolicyTrait | Base protected methods for detecting response errors and deciding whether a request can be retried. | [Contract](../reference/results/errors.md) |

[All terms](README.md).
