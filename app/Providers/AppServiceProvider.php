<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Nécessaire pour MariaDB avec utf8mb4 et clés primaires sur varchar(255)
        Schema::defaultStringLength(191);
    }
}
