<?php

namespace OpenSoutheners\LaravelDto;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class DataTransferObject
{
    /**
     * Initialise data transfer object from array.
     */
    public static function fromArray(...$args): static
    {
        $dataArray = array_merge(...$args);

        $reflector = new \ReflectionClass(static::class);

        $propertiesArr = [];

        foreach ($dataArray as $key => $value) {
            if (Str::endsWith($key, '_id')) {
                $key = Str::replaceLast('_id', '', $key);
            }

            if (Str::contains($key, '_')) {
                $key = Str::camel($key);
            }

            if ($reflector->hasProperty($key)) {
                $type = (string) $reflector->getProperty($key)->getType();

                if (str_contains($type, 'array')) {
                    if (str_contains($type, 'string') && ! str_contains($value, ',')) {
                        $propertiesArr[$key] = $value;
                    } else {
                        $propertiesArr[$key] = explode(',', $value);
                    }

                    continue;
                }

                $type = Str::replaceFirst('?', '', $type);

                if (is_subclass_of($type, 'Illuminate\Database\Eloquent\Model')) {
                    $propertiesArr[$key] = static::getInstanceFromModel($type, $value);

                    continue;
                }

                if (is_subclass_of($type, \BackedEnum::class)) {
                    $propertiesArr[$key] = $type::tryFrom($value);

                    continue;
                }

                $propertiesArr[$key] = $value;
            }
        }

        return tap(new static(...$propertiesArr), fn (self $instance) => $instance->withDefaults());
    }

    /**
     * Get instance from model class string and ID or key.
     * 
     * @param class-string<\Illuminate\Database\Eloquent\Model> $model
     */
    protected static function getInstanceFromModel($model, mixed $id)
    {
        return $model::find($id);
    }

    /**
     * Check if the following property is filled.
     */
    public function filled(string $property): bool
    {
        $request = app(Request::class);

        if (! $request->route()) {
            return property_exists($this, $property) && filled($this->{$property});
        }

        return app(Request::class)->has($property);
    }

    /**
     * Add default data to data transfer object.
     */
    public function withDefaults(): void
    {
        //
    }
}
