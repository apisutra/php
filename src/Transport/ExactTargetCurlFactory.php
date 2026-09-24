<?php

declare(strict_types=1);

namespace ApiSutra\Transport;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use GuzzleHttp\Handler\CurlFactory;
use GuzzleHttp\Handler\CurlFactoryInterface;
use GuzzleHttp\Handler\EasyHandle;
use GuzzleHttp\Utils;
use Psr\Http\Message\RequestInterface;

/** Устанавливает проверенный request target после подготовки cURL штатной фабрикой. */
final readonly class ExactTargetCurlFactory implements CurlFactoryInterface
{
    private CurlFactory $factory;

    public function __construct()
    {
        $this->factory = new CurlFactory(0);
    }

    /** @param array<string, mixed> $options */
    public function create(RequestInterface $request, array $options): EasyHandle
    {
        $easy = $this->factory->create($request, $options);
        $uri = $request->getUri();
        // Даже совпадающий с PSR URI target нельзя отдавать на нормализацию libcurl.
        $target = $request->getRequestTarget();
        if ($this->usesForwardProxy($request, $options)) {
            $target = $uri->getScheme() . '://' . $uri->getAuthority() . $target;
        }
        $success = curl_setopt($easy->handle, CURLOPT_PATH_AS_IS, true)
            && curl_setopt($easy->handle, CURLOPT_REQUEST_TARGET, $target);
        if (!$success) {
            $this->factory->release($easy);
            throw new ConfigurationException(new Message('transport.curl_does_not_support_preserving_the_request_target'));
        }
        return $easy;
    }

    /** @param array<string, mixed> $options */
    private function usesForwardProxy(RequestInterface $request, array $options): bool
    {
        if ($request->getUri()->getScheme() !== 'http') {
            return false;
        }
        $proxy = $options['proxy'] ?? null;
        if (is_array($proxy)) {
            $no = $proxy['no'] ?? [];
            // Используем те же правила no_proxy, что установленная версия Guzzle.
            $excluded = method_exists(Utils::class, 'isUriInNoProxy')
                ? Utils::isUriInNoProxy($request->getUri(), $no)
                : Utils::isHostInNoProxy($request->getUri()->getHost(), $no);
            if ($excluded) {
                return false;
            }
            $proxy = $proxy['http'] ?? null;
        }
        $proxy ??= Utils::getenv('http_proxy') ?: Utils::getenv('all_proxy') ?: Utils::getenv('ALL_PROXY');
        return is_string($proxy) && $proxy !== '' && !str_starts_with(strtolower($proxy), 'socks');
    }

    public function release(EasyHandle $easy): void
    {
        $this->factory->release($easy);
    }
}
