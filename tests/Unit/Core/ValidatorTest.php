<?php

declare(strict_types=1);

use ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use ApiSutra\Support\ContainerProviderRegistry;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use ApiSutra\Tests\Stubs\Requests\InvalidCompositeRequest;
use ApiSutra\VO\Validation\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidationFactory;

describe('Validator', function () {
    it('возвращает passed при отсутствии правил', function () {
        $dto = new SimpleResponseDto(1, 'name');

        $result = Validator::check($dto);

        expect($result->passed())->toBeTrue();
        expect($result->failed())->toBeFalse();
        expect($result->errors())->toBe([]);
    });

    it('использует validatorFactory из ContainerProvider', function () {
        Validator::resetFactory();

        $translator = new Translator(new ArrayLoader(), 'en');
        $factory = new ValidationFactory($translator);
        $provider = new class($factory) implements ContainerProviderInterface {
            public function __construct(
                private ValidationFactory $factory,
            ) {}

            #[\Override]
            public function bound(string $id): bool
            {
                return false;
            }

            #[\Override]
            public function make(string $id): ?object
            {
                return null;
            }

            #[\Override]
            public function basePath(): ?string
            {
                return null;
            }

            #[\Override]
            public function environment(): ?string
            {
                return null;
            }

            #[\Override]
            public function isDebug(): ?bool
            {
                return null;
            }

            #[\Override]
            public function validatorFactory(): ?object
            {
                return $this->factory;
            }
        };

        ContainerProviderRegistry::set($provider);

        $dto = new InvalidCompositeRequest();
        $result = Validator::check($dto);

        expect($result->failed())->toBeTrue()
            ->and($result->errors())->not->toBe([]);
    });
});
