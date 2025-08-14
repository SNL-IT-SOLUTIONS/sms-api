<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Validator::extend('alpha_spaces', function ($attribute, $value) {
            return preg_match('/^[a-zA-Z\s]+$/', $value);
        });
    
        Validator::replacer('alpha_spaces', function ($message, $attribute, $rule, $parameters) {
            return str_replace(':attribute', $attribute, ':attribute may only contain letters and spaces.');
        });
    }
}
