<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal;

use Cbox\LaravelPostal\Console\DoctorCommand;
use Cbox\LaravelPostal\Console\InstallCommand;
use Cbox\LaravelPostal\Console\MessageCommand;
use Cbox\LaravelPostal\Console\PingCommand;
use Cbox\LaravelPostal\Console\TailCommand;
use Cbox\LaravelPostal\Console\WebhookKeyCommand;
use Cbox\LaravelPostal\Contracts\Factory;
use Cbox\LaravelPostal\Contracts\InboundProcessor;
use Cbox\LaravelPostal\Contracts\ServerRegistry;
use Cbox\LaravelPostal\Contracts\SignatureVerifier;
use Cbox\LaravelPostal\Contracts\WebhookProcessor;
use Cbox\LaravelPostal\Inbound\StoreInboundProcessor;
use Cbox\LaravelPostal\Mail\PostalTransport;
use Cbox\LaravelPostal\Notifications\PostalChannel;
use Cbox\LaravelPostal\Support\Coerce;
use Cbox\LaravelPostal\Support\ConfigServerRegistry;
use Cbox\LaravelPostal\Support\Doctor;
use Cbox\LaravelPostal\Support\HttpConfig;
use Cbox\LaravelPostal\Support\InboundConfig;
use Cbox\LaravelPostal\Support\WebhookConfig;
use Cbox\LaravelPostal\Webhooks\RsaSignatureVerifier;
use Cbox\LaravelPostal\Webhooks\StoreWebhookProcessor;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Mail\MailManager;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class LaravelPostalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/postal.php', 'postal');

        $this->app->singleton(ServerRegistry::class, ConfigServerRegistry::class);

        $this->app->singleton('postal', static function (Application $app): PostalManager {
            $config = Coerce::map($app->make(ConfigRepository::class)->get('postal', []));

            return new PostalManager(
                $app->make(HttpFactory::class),
                $app->make(ServerRegistry::class),
                Coerce::string($config['default'] ?? null, 'default'),
                HttpConfig::fromArray(Coerce::map($config['http'] ?? null)),
            );
        });

        $this->app->alias('postal', PostalManager::class);
        $this->app->alias('postal', Factory::class);

        // Deliberately not singletons: the webhook posture is re-read from
        // config per resolution, so runtime config changes (and tests) are
        // always honoured.
        $this->app->bind(WebhookConfig::class, static function (Application $app): WebhookConfig {
            return WebhookConfig::fromArray(
                Coerce::map($app->make(ConfigRepository::class)->get('postal.webhooks', [])),
            );
        });

        $this->app->bind(SignatureVerifier::class, static function (Application $app): RsaSignatureVerifier {
            return new RsaSignatureVerifier($app->make(WebhookConfig::class)->publicKey);
        });

        $this->app->bind(WebhookProcessor::class, StoreWebhookProcessor::class);

        $this->app->bind(InboundConfig::class, static function (Application $app): InboundConfig {
            return InboundConfig::fromArray(
                Coerce::map($app->make(ConfigRepository::class)->get('postal.inbound', [])),
            );
        });

        $this->app->bind(Doctor::class, static function (Application $app): Doctor {
            return new Doctor(
                $app->make(ServerRegistry::class),
                $app->make(Factory::class),
                $app->make(WebhookConfig::class),
                $app->make(InboundConfig::class),
                $app->make(ConfigRepository::class),
                $app->make(DatabaseManager::class)->connection()->getSchemaBuilder(),
                $app->make(Router::class),
            );
        });

        $this->app->bind(InboundProcessor::class, StoreInboundProcessor::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/postal.php');

        $this->callAfterResolving(MailManager::class, function (MailManager $mail): void {
            $mail->extend('postal', function (array $config = []): PostalTransport {
                return new PostalTransport(
                    $this->app->make(Factory::class),
                    Coerce::stringOrNull($config['server'] ?? null),
                    Coerce::stringOrNull($this->app->make(ConfigRepository::class)->get('postal.redirect_to')),
                );
            });
        });

        $this->callAfterResolving(ChannelManager::class, function (ChannelManager $channels): void {
            $channels->extend('postal', function (Application $app): PostalChannel {
                return new PostalChannel(
                    $app->make(Factory::class),
                    $app->make(WebhookConfig::class),
                );
            });
        });

        // The store is optional: with both store flags off the package
        // registers no migrations, so `php artisan migrate` creates nothing.
        // (The publish tag below stays available either way.)
        $webhooks = $this->app->make(WebhookConfig::class);
        $inbound = $this->app->make(InboundConfig::class);

        if ($webhooks->store || $inbound->store) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/postal.php' => $this->app->configPath('postal.php'),
            ], 'postal-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'postal-migrations');

            $this->commands([
                InstallCommand::class,
                DoctorCommand::class,
                PingCommand::class,
                TailCommand::class,
                MessageCommand::class,
                WebhookKeyCommand::class,
            ]);
        }
    }
}
