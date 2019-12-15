<?php

namespace BotMan\Drivers\Line\Providers;

use Illuminate\Support\ServiceProvider;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\Line\LineDriver;
use BotMan\Drivers\Line\LineFileDriver;
use BotMan\Drivers\Line\LineAudioDriver;
use BotMan\Drivers\Line\LineImageDriver;
use BotMan\Drivers\Line\LineVideoDriver;
use BotMan\Studio\Providers\StudioServiceProvider;
use BotMan\Drivers\Line\LineLocationDriver;
use BotMan\Drivers\Line\Commands\AddGreetingText;
use BotMan\Drivers\Line\Commands\WhitelistDomains;
use BotMan\Drivers\Line\Commands\AddPersistentMenu;
use BotMan\Drivers\Line\Commands\AddStartButtonPayload;

class LineServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        if (! $this->isRunningInBotManStudio()) {
            $this->loadDrivers();

            $this->publishes([
                __DIR__.'/../../stubs/line.php' => config_path('botman/line.php'),
            ]);

            $this->mergeConfigFrom(__DIR__.'/../../stubs/line.php', 'botman.line');

            if ($this->app->runningInConsole()) {
                $this->commands([
                    AddGreetingText::class,
                    AddPersistentMenu::class,
                    AddStartButtonPayload::class,
                    WhitelistDomains::class,
                ]);
            }
        }
    }

    /**
     * Load BotMan drivers.
     */
    protected function loadDrivers()
    {
        DriverManager::loadDriver(LineDriver::class);
        DriverManager::loadDriver(LineAudioDriver::class);
        DriverManager::loadDriver(LineFileDriver::class);
        DriverManager::loadDriver(LineImageDriver::class);
        DriverManager::loadDriver(LineLocationDriver::class);
        DriverManager::loadDriver(LineVideoDriver::class);
    }

    /**
     * @return bool
     */
    protected function isRunningInBotManStudio()
    {
        return class_exists(StudioServiceProvider::class);
    }
}
