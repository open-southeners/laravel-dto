<?php

namespace OpenSoutheners\LaravelDto;

use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Symfony\Component\PropertyInfo\Type;

abstract class DataTransferObject implements Arrayable
{
    /**
     * Initialise data transfer object from a request.
     */
    public static function fromRequest(Request|FormRequest $request): static
    {
        return static::fromArray(array_merge(
            is_object($request->route()) ? $request->route()->parameters() : [],
            $request instanceof FormRequest
                ? $request->validated()
                : $request->all(),
        ));
    }

    /**
     * Initialise data transfer object from array.
     */
    public static function fromArray(...$args): static
    {
        $propertiesMapper = new PropertiesMapper(array_merge(...$args), static::class);

        $propertiesMapper->run();

        return tap(
            new static(...$propertiesMapper->get()), 
            fn (self $instance) => $instance->initialise()
        );
    }

    /**
     * Check if the following property is filled.
     */
    public function filled(string $property): bool
    {
        /** @var \Illuminate\Http\Request $request */
        $request = app(Request::class);
        $camelProperty = Str::camel($property);

        if ($request->route()) {
            $requestHasProperty = $request->has(Str::snake($property))
                ?: $request->has($property)
                ?: $request->has($camelProperty);

            if (! $requestHasProperty && $request->route() instanceof Route) {
                return $request->route()->hasParameter($property)
                    ?: $request->route()->hasParameter($camelProperty);
            }

            return $requestHasProperty;
        }

        $propertyInfoExtractor = PropertiesMapper::propertyInfoExtractor();

        $reflection = new \ReflectionClass($this);

        $classProperty = match (true) {
            $reflection->hasProperty($property) => $property,
            $reflection->hasProperty($camelProperty) => $camelProperty,
            default => throw new Exception("Properties '{$property}' or '{$camelProperty}' doesn't exists on class instance."),
        };

        $classPropertyTypes = $propertyInfoExtractor->getTypes(get_class($this), $classProperty);

        $reflectionProperty = $reflection->getProperty($classProperty);
        $propertyValue = $reflectionProperty->getValue($this);

        if ($classPropertyTypes === null) {
            return function_exists('filled') && filled($propertyValue);
        }

        $propertyDefaultValue = $reflectionProperty->getDefaultValue();

        $propertyIsNullable = in_array(true, array_map(fn (Type $type) => $type->isNullable(), $classPropertyTypes), true);

        /**
         * Not filled when DTO property's default value is set to null while none is passed through
         */
        if (! $propertyValue && $propertyIsNullable && $propertyDefaultValue === null) {
            return false;
        }

        /**
         * Not filled when property isn't promoted and does have a default value matching value sent
         *
         * @see problem with promoted properties and hasDefaultValue/getDefaultValue https://bugs.php.net/bug.php?id=81386
         */
        if (! $reflectionProperty->isPromoted() && $reflectionProperty->hasDefaultValue() && $propertyValue === $propertyDefaultValue) {
            return false;
        }

        return true;
    }

    /**
     * Initialise data transfer object (defaults, etc).
     */
    public function initialise(): static
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

    /**
     * Get the instance as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray()
    {
        $properties = get_class_vars(get_class($this));
        $newPropertiesArr = [];

        foreach ($properties as $key => $value) {
            if (! $this->filled($key)) {
                continue;
            }
            
            $propertyValue = $this->{$key} ?? $value;

            if ($propertyValue instanceof Arrayable) {
                $propertyValue = $propertyValue->toArray();
            }

            $newPropertiesArr[Str::snake($key)] = $propertyValue;
        }

        return $newPropertiesArr;
    }
}
