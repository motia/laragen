<?php

namespace Motia\Generator;

use Illuminate\Support\ServiceProvider;
use Motia\Generator\Commands\DummyCommand;
use Motia\Generator\Commands\GenerateAllCommand;
use Motia\Generator\Commands\GUIGenCommand;

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

        $this->app->singleton('motia:guigen', function ($app) {
            return new GUIGenCommand();
        });

        $this->commands([
            'motia:generate',
            'motia:guigen',
            'motia:dummy',
        ]);
    }
}
