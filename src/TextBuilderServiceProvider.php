<?php

namespace Ermac\TextBuilder;
use Illuminate\Support\ServiceProvider;

class TextBuilderServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('textBuilder',function (){
            return new TextBuilder;
        });

        $this->mergeConfigFrom(__DIR__ . '/Configs/config.php','textBuilder');

    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/Configs/config.php' => config_path('textBuilder.php'),
        ],'config');
    }
}
