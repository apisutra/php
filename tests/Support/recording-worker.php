<?php

declare(strict_types=1);

use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use ApiSutra\Transport\RecordingTransport;
use ApiSutra\VO\Http\PreparedRequest;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$transport = new MockTransport();
$transport->fake(['*' => MockResponse::success(['worker' => $argv[2], 'padding' => str_repeat('x', 10000)])]);
$recorder = new RecordingTransport($transport, $argv[1]);
for ($index = 0; $index < 10; $index++) {
    $recorder->send(new PreparedRequest(HttpMethod::GET, 'https://fixture.test'));
}
