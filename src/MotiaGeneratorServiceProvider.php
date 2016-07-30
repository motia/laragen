<?php

namespace Motia\Generator;

use Illuminate\Support\ServiceProvider;
use Motia\Generator\Commands\GenerateAllCommand;
use Motia\Generator\Commands\DummyCommand;


class MotiaGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $configPath = __DIR__.'/../config/laragen.php';

        $this->publishes([
            $configPath => config_path('motia/laragen.php'),
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('motia:generate', function ($app) {
            return new GenerateAllCommand();
        });

        $this->app->singleton('motia:dummy', function ($app) {
            return new DummyCommand();
        });

        $this->commands([
            'motia:generate',
            'motia:dummy'
        ]);
    }
}
