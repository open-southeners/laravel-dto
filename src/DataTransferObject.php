<?php

namespace OpenSoutheners\LaravelDto;

use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSoutheners\LaravelDto\Attributes\BindModelUsing;
use OpenSoutheners\LaravelDto\Attributes\BindModelWith;
use OpenSoutheners\LaravelDto\Attributes\WithDefaultValue;
use Symfony\Component\PropertyInfo\Type;

abstract class DataTransferObject implements Arrayable
{
    private bool $fromRequestContext = false;

    /**
     * Initialise data transfer object from a request.
     */
    public static function fromRequest(Request|FormRequest $request)
    {
        return static::fromArray(array_merge(
            is_object($request->route()) ? $request->route()->parameters() : [],
            $request instanceof FormRequest
                ? $request->validated()
                : $request->all(),
        ))->fromRequestContext();
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

        if ($this->fromRequestContext && $request->route()) {
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
        /** @var array<\ReflectionProperty> $properties */
        $properties = (new \ReflectionClass($this))->getProperties(\ReflectionProperty::IS_PUBLIC);
        $newPropertiesArr = [];

        foreach ($properties as $property) {
            if (! $this->filled($property->name) && count($property->getAttributes(WithDefaultValue::class)) === 0) {
                continue;
            }

            $propertyValue = $property->getValue($this) ?? $property->getDefaultValue();

            if ($propertyValue instanceof Arrayable) {
                $propertyValue = $propertyValue->toArray();
            }

            if ($propertyValue instanceof \stdClass) {
                $propertyValue = (array) $propertyValue;
            }

            $newPropertiesArr[Str::snake($property->name)] = $propertyValue;
        }

        return $newPropertiesArr;
    }

    /**
     * Data transfer object instanced from request context.
     */
    public function fromRequestContext(bool $value = true): self
    {
        $this->fromRequestContext = $value;

        return $this;
    }

    public function __serialize(): array
    {
        $reflection = new \ReflectionClass($this);
        
        /** @var array<\ReflectionProperty> $properties */
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        
        $serialisableArr = [];
        
        foreach ($properties as $property) {
            $key = $property->getName();
            $value = $property->getValue($this);
            
            /** @var array<\ReflectionAttribute> $propertyAttributes */
            $propertyAttributes = $property->getAttributes();
            $propertyBindingAttributes = [
                'using' => null,
            ];

            foreach ($propertyAttributes as $attribute) {
                $attributeInstance = $attribute->newInstance();

                if ($attributeInstance instanceof BindModelUsing) {
                    $propertyBindingAttributes['using'] = $attributeInstance->attribute;
                }
            }

            $serialisableArr[$key] = match (true) {
                $value instanceof Model => $value->getAttribute($propertyBindingAttributes['using'] ?? $value->getRouteKeyName()),
                $value instanceof Collection => $value->first() instanceof Model ? $value->map(fn (Model $model) => $model->getAttribute($propertyBindingAttributes['using'] ?? $model->getRouteKeyName()))->join(',') : $value->join(','),
                $value instanceof Arrayable => $value->toArray(),
                $value instanceof \Stringable => (string) $value,
                is_array($value) => head($value) instanceof Model ? implode(',', array_map(fn (Model $model) => $model->getAttribute($propertyBindingAttributes['using'] ?? $model->getRouteKeyName()), $value)) : implode(',', $value),
                default => $value,
            };
        }

        return $serialisableArr;
    }

    /**
     * Called during unserialization of the object.
     */
    public function __unserialize(array $data): void
    {
        $properties = (new \ReflectionClass($this))->getProperties(\ReflectionProperty::IS_PUBLIC);

        $propertiesMapper = new PropertiesMapper(array_merge($data), static::class);

        $propertiesMapper->run();

        $data = $propertiesMapper->get();

        foreach ($properties as $property) {
            $key = $property->getName();

            $this->{$key} = $data[$key] ?? $property->getDefaultValue();
        }
    }
}
