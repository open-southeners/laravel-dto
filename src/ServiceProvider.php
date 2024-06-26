<?php

namespace OpenSoutheners\LaravelDto;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use OpenSoutheners\LaravelDto\Commands\DtoMakeCommand;
use OpenSoutheners\LaravelDto\Commands\DtoTypescriptGenerateCommand;
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
            $this->publishes([
                __DIR__.'/../config/data-transfer-objects.php' => config_path('data-transfer-objects.php'),
            ], 'config');

            $this->commands([DtoMakeCommand::class, DtoTypescriptGenerateCommand::class]);
        }

        $this->app->bind('dto.context.booted', fn () => '');

        $this->app->beforeResolving(
            DataTransferObject::class,
            function ($dataClass, $parameters, $app) {
                /** @var \Illuminate\Foundation\Application $app */
                $app->scoped($dataClass, fn () => $dataClass::fromRequest(
                    app(is_subclass_of($dataClass, ValidatedDataTransferObject::class) ? $dataClass::request() : Request::class)
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
