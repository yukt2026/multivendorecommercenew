<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind OTP Service Interface with default Log Driver
        $this->app->singleton(\App\Services\Otp\OtpServiceInterface::class, function () {
            return new \App\Services\Otp\LogOtpDriver();
        });

        // Bind Penny-drop Bank Verification Service Interface with default Stub Driver
        $this->app->singleton(\App\Services\Banking\BankVerificationServiceInterface::class, function () {
            return new \App\Services\Banking\StubBankVerificationService();
        });

        // Bind Search Service Interface with Database / MySQL Fulltext & Trigram Fallback Driver
        $this->app->singleton(\App\Services\Search\SearchServiceInterface::class, function () {
            return new \App\Services\Search\DatabaseSearchService();
        });

        // Bind Logistics Provider
        $this->app->singleton(\App\Services\Cargo\Contracts\LogisticsProvider::class, function ($app) {
            return $app->make(\App\Services\Cargo\OneStallCargoProvider::class);
        });

        // Bind Notification Gateways (Pluggable SMS, WhatsApp, Push)
        $this->app->singleton(\App\Services\Notification\Drivers\SmsGatewayInterface::class, function () {
            return new \App\Services\Notification\Drivers\MockSmsDriver();
        });

        $this->app->singleton(\App\Services\Notification\Drivers\WhatsAppGatewayInterface::class, function () {
            return new \App\Services\Notification\Drivers\MockWhatsAppDriver();
        });

        $this->app->singleton(\App\Services\Notification\Drivers\PushGatewayInterface::class, function () {
            return new \App\Services\Notification\Drivers\MockPushDriver();
        });

        // Bind OneStall Marketplace AI Engine Services
        $this->app->singleton(\App\Services\AI\AiManager::class);

        $this->app->singleton(
            \App\Services\AI\Contracts\SemanticSearchParserInterface::class,
            \App\Services\AI\SemanticSearch\NaturalLanguageSearchParser::class
        );

        $this->app->singleton(
            \App\Services\AI\Contracts\RecommendationServiceInterface::class,
            \App\Services\AI\Recommendations\MarketplaceRecommendationService::class
        );

        $this->app->singleton(
            \App\Services\AI\Contracts\SellerAiAssistantInterface::class,
            \App\Services\AI\SellerAssistant\SellerAiAssistantService::class
        );

        $this->app->singleton(
            \App\Services\AI\Contracts\DemandForecastingInterface::class,
            \App\Services\AI\Forecasting\DemandForecastingService::class
        );

        $this->app->singleton(
            \App\Services\AI\Contracts\OrderFraudScorerInterface::class,
            \App\Services\AI\Fraud\OrderFraudScorerService::class
        );

        $this->app->singleton(
            \App\Services\AI\Contracts\SupportAiReplyInterface::class,
            \App\Services\AI\Support\SupportAiReplyService::class
        );

        // Graceful Redis fallback: check Redis reachability if queue or session is set to redis
        if (config('session.driver') === 'redis' || config('queue.default') === 'redis') {
            try {
                $redisHost = config('database.redis.default.host', '127.0.0.1');
                $redisPort = (int) config('database.redis.default.port', 6379);
                $socket = @fsockopen($redisHost, $redisPort, $errno, $errstr, 0.1);
                if (!$socket) {
                    if (config('session.driver') === 'redis') {
                        config(['session.driver' => 'database']);
                    }
                    if (config('queue.default') === 'redis') {
                        config(['queue.default' => 'database']);
                    }
                } else {
                    fclose($socket);
                }
            } catch (\Throwable) {
                if (config('session.driver') === 'redis') {
                    config(['session.driver' => 'database']);
                }
                if (config('queue.default') === 'redis') {
                    config(['queue.default' => 'database']);
                }
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Performance: Enforce strict eager loading in development & testing to prevent N+1 queries
        Model::preventLazyLoading(! $this->app->isProduction());

        // Horizon authorization gate (when enabled via config)
        if (config('onestall.enable_horizon', false) && class_exists(\Laravel\Horizon\Horizon::class)) {
            Gate::define('viewHorizon', function ($user = null) {
                return (bool) optional($user)->hasRole('super-admin');
            });
        }

        // Notification Delivery & Failure Telemetry Listener
        Event::listen(\Illuminate\Notifications\Events\NotificationSent::class, function ($event) {
            if ($event->channel === 'mail' && is_object($event->notifiable)) {
                \App\Models\NotificationLog::create([
                    'notifiable_type' => get_class($event->notifiable),
                    'notifiable_id' => $event->notifiable->id ?? null,
                    'event_key' => property_exists($event->notification, 'eventKey') ? $event->notification->eventKey : 'mail_notification',
                    'channel' => 'mail',
                    'recipient' => $event->notifiable->email ?? (string) ($event->notifiable->id ?? ''),
                    'content' => property_exists($event->notification, 'eventKey') ? "Sent mail for {$event->notification->eventKey}" : 'Sent mail notification',
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }
        });

        Event::listen(\Illuminate\Notifications\Events\NotificationFailed::class, function ($event) {
            if (is_object($event->notifiable)) {
                $errorMsg = isset($event->data['exception']) && $event->data['exception'] instanceof \Throwable
                    ? $event->data['exception']->getMessage()
                    : 'Channel delivery failure';

                \App\Models\NotificationLog::create([
                    'notifiable_type' => get_class($event->notifiable),
                    'notifiable_id' => $event->notifiable->id ?? null,
                    'event_key' => property_exists($event->notification, 'eventKey') ? $event->notification->eventKey : 'notification_failed',
                    'channel' => is_string($event->channel) ? $event->channel : 'channel',
                    'recipient' => $event->notifiable->email ?? $event->notifiable->phone ?? (string) ($event->notifiable->id ?? ''),
                    'content' => 'Notification dispatch failed',
                    'status' => 'failed',
                    'error_message' => $errorMsg,
                ]);
            }
        });
    }
}
