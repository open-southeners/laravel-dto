<?php

namespace OpenSoutheners\LaravelDataMapper;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use OpenSoutheners\LaravelDataMapper\Attributes\Validate;
use OpenSoutheners\LaravelDataMapper\Contracts\RouteTransferableObject;
use ReflectionClass;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/data-mapper.php', 'data-mapper');

        $this->app->singleton(MapperRegistry::class, function () {
            $registry = new MapperRegistry;

            $registry->register(Mappers\MappableObjectMapper::class, 100);
            $registry->register(Mappers\CollectionDataMapper::class, 80);
            $registry->register(Mappers\ModelDataMapper::class, 70);
            $registry->register(Mappers\CarbonDataMapper::class, 60);
            $registry->register(Mappers\BackedEnumDataMapper::class, 50);
            $registry->register(Mappers\GenericObjectDataMapper::class, 40);
            $registry->register(Mappers\ObjectDataMapper::class, 30);

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/data-mapper.php' => config_path('data-mapper.php'),
            ], ['config', 'laravel-data-mapper']);
        }

        $this->app->beforeResolving(
            RouteTransferableObject::class,
            function ($dataClass, $parameters, $app) {
                /** @var Application $app */
                $app->scoped($dataClass, function () use ($dataClass, $app) {
                    $reflector = new ReflectionClass($dataClass);

                    $validateAttributes = $reflector->getAttributes(Validate::class);
                    $validateAttribute = reset($validateAttributes);

                    return map(
                        $app->make($validateAttribute ? $validateAttribute->newInstance()->value : Request::class)
                    )->to($dataClass);
                });
            }
        );

        $this->app->instance(PropertyInfoExtractor::class, new PropertyInfoExtractor);
        $this->app->alias(PropertyInfoExtractor::class, 'propertyInfo');
    }
}
