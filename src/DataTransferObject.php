<?php

namespace OpenSoutheners\LaravelDto;

use Exception;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

abstract class DataTransferObject
{
    use SerializesModels;

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
        $propertiesMapper = new PropertiesMapper(array_merge(...$args), static::class);

        $propertiesMapper->run();

        return tap(new static(...$propertiesMapper->get()), fn (self $instance) => $instance->initialise());
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
