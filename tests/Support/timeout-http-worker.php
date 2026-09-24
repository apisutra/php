<?php

declare(strict_types=1);

use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Exceptions\Transport\TimeoutException;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\TransportOptions;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$transport = HttpTransport::createDefault();
echo "ready\n";
flush();
fgets(STDIN);
$start = microtime(true);
try {
    $response = $transport->send(new PreparedRequest(HttpMethod::GET, $argv[1], transportOptions: new TransportOptions((int) $argv[2], 1000)));
    $outcome = $response->status;
} catch (TimeoutException) {
    $outcome = 'timeout';
}
echo json_encode(['start' => $start, 'end' => microtime(true), 'outcome' => $outcome], JSON_THROW_ON_ERROR) . "\n";
