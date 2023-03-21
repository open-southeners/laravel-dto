<?php

namespace OpenSoutheners\LaravelDto;

use Exception;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class DataTransferObject
{
    /**
     * Initialise data transfer object from a request.
     */
    public static function fromRequest(Request|FormRequest $request): static
    {
        return static::fromArray(
            $request instanceof FormRequest
                ? $request->validated()
                : $request->all()
        );
    }

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
                        $propertiesArr[$key] = array_filter(explode(',', $value));
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

        return tap(new static(...$propertiesArr), fn (self $instance) => $instance->initialise());
    }

    /**
     * Get instance from model class string and ID or key.
     *
     * @param class-string<\Illuminate\Database\Eloquent\Model> $model
     */
    protected static function getInstanceFromModel($model, mixed $id): mixed
    {
        return $model::find($id);
    }

    /**
     * Check if the following property is filled.
     */
    public function filled(string $property): bool
    {
        $request = app(Request::class);
        $classProperty = Str::camel($property);

        if ($request->route()) {
            return $request->has(Str::snake($property))
                ?: $request->has($property)
                ?: $request->has($classProperty);
        }

        $reflection = new \ReflectionClass($this);

        $reflectionProperty = match (true) {
            $reflection->hasProperty($property) => $reflection->getProperty($property),
            $reflection->hasProperty($classProperty) => $reflection->getProperty($classProperty),
            default => throw new Exception("Properties '{$property}' or '{$classProperty}' doesn't exists on class instance."),
        };

        $defaultValue = $reflectionProperty->getDefaultValue();
        $propertyValue = $reflectionProperty->getValue($this);

        $reflectionPropertyType = $reflectionProperty->getType();

        if ($reflectionPropertyType === null) {
            return function_exists('filled') && filled($propertyValue);
        }

        /**
         * Not filled when DTO property's default value is set to null while none is passed through
         */
        if (! $propertyValue && $reflectionPropertyType->allowsNull() && $defaultValue === null) {
            return false;
        }

        /**
         * Not filled when property isn't promoted and does have a default value matching value sent
         *
         * @see problem with promoted properties and hasDefaultValue/getDefaultValue https://bugs.php.net/bug.php?id=81386
         */
        if (! $reflectionProperty->isPromoted() && $reflectionProperty->hasDefaultValue() && $propertyValue === $defaultValue) {
            return false;
        }

        return true;
    }

    /**
     * Initialise data transfer object (defaults, etc).
     */
    public function initialise()
    {
        $this->withDefaults();

        return $this;
    }

    /**
     * Add default data to data transfer object.
     *
     * @codeCoverageIgnore
     */
    public function withDefaults(): void
    {
        //
    }
}
