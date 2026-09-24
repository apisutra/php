<?php

declare(strict_types=1);

use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Support\NullContainerProvider;
use ApiSutra\Transport\DefaultTransportFactory;

require ($argv[1] ?? dirname(__DIR__, 2)) . '/vendor/autoload.php';
if (class_exists(GuzzleHttp\Client::class)) {
    throw new RuntimeException('Smoke requires a production install without Guzzle Client.');
}
$key = 'transport.transport_is_not_configured_bind_transportinterface_in_the_container';
try {
    (new DefaultTransportFactory())->create(new NullContainerProvider());
    throw new RuntimeException('Missing transport accepted.');
} catch (ConfigurationException $error) {
    $ru = $error->localized(new LocalizationConfig('ru'));
    $custom = $error->localized(new LocalizationConfig('en', messages: ['en' => [$key => 'Custom transport error']]));
    if ($ru->getMessage() === $key || $ru->getMessage() === $error->getMessage()
        || $custom->getMessage() !== 'Custom transport error'
        || $ru->localized(new LocalizationConfig('en'))->getMessage() !== $error->getMessage()) {
        throw new RuntimeException('Transport message catalogs or overrides lost.');
    }
}
echo "Standalone default transport and messages — OK.\n";
