<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Inbound\InboundController;
use Cbox\LaravelPostal\Support\InboundConfig;
use Cbox\LaravelPostal\Support\WebhookConfig;
use Cbox\LaravelPostal\Webhooks\WebhookController;
use Illuminate\Support\Facades\Route;

$webhooks = app(WebhookConfig::class);

if ($webhooks->enabled) {
    Route::post($webhooks->path.'/{server?}', WebhookController::class)
        ->middleware($webhooks->middleware)
        ->name('postal.webhook');
}

$inbound = app(InboundConfig::class);

if ($inbound->enabled) {
    Route::post($inbound->path.'/{server?}', InboundController::class)
        ->middleware($inbound->middleware)
        ->name('postal.inbound');
}
