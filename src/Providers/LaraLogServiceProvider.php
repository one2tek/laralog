<?php

namespace one2tek\laralog\Providers;

use Illuminate\Support\ServiceProvider;

class LaraLogServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__. '/../../config/laralog.php',
            'laralog'
        );
    }

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__. '/../../config/laralog.php' => config_path('laralog.php'),
        ]);

        if (! class_exists('CreateLogsTable')) {
            $timestamp = date('Y_m_d_His', time());

            $this->publishes([
                __DIR__.'/../../migrations/create_logs_table.php.stub' => database_path("/migrations/{$timestamp}_create_logs_table.php"),
            ], 'migrations');
        }
    }
}
