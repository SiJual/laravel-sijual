<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Auth::provider('supabase', function ($app, array $config) {
            return new \App\Providers\SupabaseUserProvider();
        });

        \Illuminate\Support\Facades\Blade::directive('rupiahShort', function ($expression) {
            return "<?php
                \$amount = (float)($expression);
                \$isNegative = \$amount < 0;
                \$absAmount = abs(\$amount);
                if (\$absAmount >= 1000000000000) {
                    \$val = round(\$absAmount / 1000000000000, 1);
                    \$suffix = 'T';
                } elseif (\$absAmount >= 1000000000) {
                    \$val = round(\$absAmount / 1000000000, 1);
                    \$suffix = 'M';
                } elseif (\$absAmount >= 1000000) {
                    \$val = round(\$absAmount / 1000000, 1);
                    \$suffix = 'jt';
                } elseif (\$absAmount >= 1000) {
                    \$val = round(\$absAmount / 1000, 1);
                    \$suffix = 'rb';
                } else {
                    \$val = \$absAmount;
                    \$suffix = '';
                }
                if (\$suffix) {
                    \$formatted = str_replace('.', ',', (string)\$val) . \$suffix;
                } else {
                    \$formatted = number_format(\$val, 0, ',', '.');
                }
                echo (\$isNegative ? '-' : '') . 'Rp ' . \$formatted;
            ?>";
        });
    }
}
