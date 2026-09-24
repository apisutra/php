<?php

declare(strict_types=1);

use ApiSutra\Pipeline\Auth\AuthRefreshLock;
use ApiSutra\Tests\Support\TestAuthLockProvider;
use ApiSutra\Tests\Support\VirtualClock;

it('фасад освобождает lease через исходный provider', function (): void {
    $provider = new TestAuthLockProvider();
    $lock = new AuthRefreshLock(locks: $provider);
    $token = $lock->acquire('fixture', 5);
    expect($token)->not->toBeNull()->and($lock->acquire('fixture', 5))->toBeNull();
    $lock->release('fixture', $token);
    expect($provider->releases)->toBe(1)->and($lock->acquire('fixture', 5))->not->toBeNull();
});

it('фасад работает без кеша и не освобождает чужой key/token', function (): void {
    $lock = new AuthRefreshLock();
    $token = $lock->acquire('fixture', 5);
    $lock->release('fixture', 'wrong');
    $lock->release('other', $token);
    expect($lock->acquire('fixture', 5))->toBeNull();
    $lock->release('fixture', $token);
    expect($lock->acquire('fixture', 5))->not->toBeNull();
});

it('истечение TTL не позволяет старому фасаду удалить нового владельца', function (): void {
    $clock = new VirtualClock();
    $lock = new AuthRefreshLock(clock: $clock);
    $first = $lock->acquire('fixture', 1);
    $clock->advance(1000);
    $second = $lock->acquire('fixture', 1);
    expect($second)->not->toBeNull();
    $lock->release('fixture', $first);
    expect($lock->acquire('fixture', 1))->toBeNull();
    $lock->release('fixture', $second);
    expect($lock->acquire('fixture', 1))->not->toBeNull();
});
