<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use Generated\Smoke\Data;

$root = $argv[1] ?? dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$folder = sys_get_temp_dir() . '/apisutra-generator-' . bin2hex(random_bytes(6));
mkdir($folder);
file_put_contents($folder . '/composer.json', json_encode(['autoload' => ['psr-4' => ['Generated\\Smoke\\' => 'src/']]]));
$paths = [];
try {
    foreach (['dto' => 'Data', 'client' => 'Client', 'request' => 'Request'] as $kind => $name) {
        $args = [PHP_BINARY, $root . '/bin/apisutra', 'make:' . $kind, $name, '--project=' . $folder];
        if ($kind === 'request') { $args = [...$args, '--endpoint=/test', '--dto=Generated\\Smoke\\Data']; }
        $process = proc_open($args, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) { throw new RuntimeException($error); }
        $paths[] = $path = trim($output);
        require $path;
    }
    $clientClass = 'Generated\\Smoke\\Client';
    $requestClass = 'Generated\\Smoke\\Request';
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success([])]);
    $client = new $clientClass(new ClientConfig(baseUrl: 'https://example.test'), $transport);
    if (!$client->send(new $requestClass())->dataOrFail() instanceof Data) { throw new RuntimeException('Generated DTO contract failed'); }
    echo "Generated CLI/client/request/DTO without dev dependencies — OK\n";
} finally {
    foreach ($paths as $path) { unlink($path); }
    unlink($folder . '/composer.json');
    if (is_dir($folder . '/src')) { rmdir($folder . '/src'); }
    rmdir($folder);
}
