<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->each->not->toBeUsed();

arch('strict types are declared')
    ->expect('Cbox\LaravelPostal')
    ->toUseStrictTypes();

arch('no package class is final')
    ->expect('Cbox\LaravelPostal')
    ->classes()
    ->not->toBeFinal();

arch('contracts are interfaces')
    ->expect('Cbox\LaravelPostal\Contracts')
    ->toBeInterfaces();

arch('commands extend the console Command')
    ->expect('Cbox\LaravelPostal\Console')
    ->toExtend('Illuminate\Console\Command');

arch('DTOs are readonly')
    ->expect('Cbox\LaravelPostal\Dto')
    ->classes()
    ->toBeReadonly()
    ->ignoring('Cbox\LaravelPostal\Dto\SendMessage');

arch('webhook payloads are readonly')
    ->expect('Cbox\LaravelPostal\Webhooks\Payloads')
    ->classes()
    ->toBeReadonly();

arch('events implement the webhook event contract')
    ->expect('Cbox\LaravelPostal\Events')
    ->classes()
    ->toImplement('Cbox\LaravelPostal\Events\PostalWebhookEvent');
