<?php

declare(strict_types=1);

use ApiSutra\Auth\OAuth2\Internal\TokenRequest;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\OAuth2Config;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\GuzzleHttpClient;
use ApiSutra\Transport\HttpTransport;
use GuzzleHttp\Psr7\HttpFactory;

it('отправляет изолированный form по TLS и не следует redirect', function (bool $async, int $status): void {
    $directory = sys_get_temp_dir() . '/apisutra-oauth-tls-' . bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    $csr = openssl_csr_new(['commonName' => 'localhost'], $key);
    $certificate = openssl_csr_sign($csr, null, $key, 1);
    openssl_x509_export($certificate, $public);
    openssl_pkey_export($key, $private);
    file_put_contents($directory . '/server.pem', $public . $private);
    $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/Support/oauth2-https-server.php', $directory . '/server.pem', $directory . '/capture.json', (string) $status], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    try {
        stream_set_timeout($pipes[1], 5);
        $address = trim(fgets($pipes[1]));
        expect($address)->not->toBe('');
        $factory = new HttpFactory();
        // Только тестовый self-signed endpoint. SDK требует HTTPS и не ослабляет проверку сертификата.
        $transport = new HttpTransport(new GuzzleHttpClient(['verify' => false, 'auth' => ['api-user', 'api-password'], 'headers' => ['X-Api-Secret' => 'api-secret']]), $factory, $factory);
        $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test'), $transport);
        $request = new TokenRequest(new OAuth2Config('https://' . $address . '/token', 'client', 'client-secret'), 'authorization_code', ['code' => 'a+b&c', 'code_verifier' => 'fixture-verifier']);
        $result = $async ? $client->sendAsync($request)->wait()->raw() : $client->send($request)->raw();
        expect($result->response?->status)->toBe($status)->and($result->isSuccess())->toBe($status === 200);
        $capture = json_decode(file_get_contents($directory . '/capture.json'), true);
        expect($capture['headers'])->toContain('application/x-www-form-urlencoded')->not->toContain('api-secret')->not->toContain('api-password');
        parse_str($capture['body'], $form);
        expect($form)->toBe(['code' => 'a+b&c', 'code_verifier' => 'fixture-verifier', 'grant_type' => 'authorization_code']);
    } finally {
        proc_terminate($process);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($process);
        foreach (glob($directory . '/*') as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
})->with([false, true])->with([200, 302]);
