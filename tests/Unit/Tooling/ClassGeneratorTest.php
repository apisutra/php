<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tooling\Generation\ClassGenerator;
use ApiSutra\Tooling\Generation\GenerationCommand;
use ApiSutra\Tooling\Generation\GenerationKind;
use ApiSutra\Tooling\Generation\NamespaceRoot;
use ApiSutra\Transport\MockTransport;

it('генерирует исполняемый комплект DTO/request/client из PSR-4 и не перезаписывает файлы', function (): void {
    $folder = sys_get_temp_dir() . '/apisutra-generation-' . bin2hex(random_bytes(5));
    mkdir($folder);
    file_put_contents($folder . '/composer.json', json_encode(['autoload' => ['psr-4' => ['Generated\\Catalog\\' => 'src/']]]));
    $command = new GenerationCommand();
    $paths = [];
    try {
        foreach (['dto' => 'Product', 'request' => 'GetProduct', 'client' => 'Client'] as $kind => $name) {
            $args = ['make:' . $kind, $name];
            if ($kind === 'request') { $args = [...$args, '--endpoint=/products/1', '--dto=Generated\\Catalog\\Product']; }
            $paths[] = $path = $command->execute($args, $folder);
            require $path;
        }
        $transport = new MockTransport();
        $transport->fake(['*' => MockResponse::make('{}')]);
        $clientClass = 'Generated\\Catalog\\Client';
        $requestClass = 'Generated\\Catalog\\GetProduct';
        $client = new $clientClass(new ClientConfig(baseUrl: 'https://test.example'), $transport);
        expect($client->send(new $requestClass())->dataOrFail())->toBeInstanceOf('Generated\\Catalog\\Product')
            ->and($transport->getRecorded()[0]->url)->toBe('https://test.example/products/1');
        expect(fn () => $command->execute(['make:client', 'Client'], $folder))->toThrow(ConfigurationException::class);
    } finally {
        foreach ($paths as $path) { unlink($path); }
        unlink($folder . '/composer.json');
        rmdir($folder . '/src');
        rmdir($folder);
    }
});

it('отказывает до записи при неоднозначности, неверном имени и выходе через symlink', function (): void {
    $folder = sys_get_temp_dir() . '/apisutra-path-' . bin2hex(random_bytes(5));
    mkdir($folder);
    mkdir($folder . '/src');
    mkdir($folder . '/outside');
    symlink($folder . '/outside', $folder . '/src/Escape');
    file_put_contents($folder . '/composer.json', json_encode(['autoload' => ['psr-4' => ['App\\' => 'app', 'Sdk\\' => 'src']]]));
    try {
        expect(fn () => NamespaceRoot::resolve($folder))->toThrow(ConfigurationException::class);
        $root = NamespaceRoot::resolve($folder, 'Sdk');
        foreach (['../Oops', 'class', 'Good\\match', 'x.php', 'A;exit', 'Escape\\Oops'] as $name) {
            expect(fn () => new ClassGenerator()->generate(GenerationKind::Client, $name, $root))->toThrow(ConfigurationException::class);
        }
        expect(glob($folder . '/outside/*'))->toBe([]);
    } finally {
        unlink($folder . '/src/Escape');
        unlink($folder . '/composer.json');
        rmdir($folder . '/outside'); rmdir($folder . '/src'); rmdir($folder);
    }
});
