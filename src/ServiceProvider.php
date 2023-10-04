<?php

namespace OpenSoutheners\LaravelDto;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use OpenSoutheners\LaravelDto\Commands\DtoMakeCommand;
use OpenSoutheners\LaravelDto\Contracts\ValidatedDataTransferObject;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([DtoMakeCommand::class]);
        }

        $this->app->beforeResolving(
            DataTransferObject::class,
            function ($dataClass, $parameters, $app) {
                /** @var \Illuminate\Foundation\Application $app */
                $app->scoped($dataClass, fn () => $dataClass::fromRequest(
                    app($dataClass instanceof ValidatedDataTransferObject ? $dataClass::request() : Request::class)
                ));
            }
        );
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // 
    }
}
