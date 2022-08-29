<?php

declare(strict_types=1);

namespace Erg\SsoDepartmentPosition;

use Erg\SsoDepartmentPosition\App\Console\Commands\LoadDepartmentPositions;
use Illuminate\Support\ServiceProvider;

class SsoDepartmentPositionProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/sso_department_position.php' => config_path('sso_department_position.php'),

            // использовать так, а не через $this->loadMigrationsFrom (чтобы можно было добавлять свои поля, или, если таблица уже создана, тогда не использовать)
            __DIR__.'/database/migrations/2022_09_29_090000_create_department_positions_table.php' =>
                database_path('/migrations/2022_09_29_090000_create_department_positions_table.php'),

            __DIR__.'/App/Console/Utils/FunctionalDirections.php' => app_path('Console/Utils/FunctionalDirections.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                LoadDepartmentPositions::class,
            ]);
        }

    }
}
