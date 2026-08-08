<?php

namespace OpenSoutheners\LaravelDataMapper\Mappers;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\ContextualAttribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSoutheners\LaravelDataMapper\Attributes\NormaliseProperties;
use OpenSoutheners\LaravelDataMapper\Exceptions\UnresolvableContextualAttributeException;
use OpenSoutheners\LaravelDataMapper\MappingValue;
use OpenSoutheners\LaravelDataMapper\PropertyInfoExtractor;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use stdClass;
use Symfony\Component\TypeInfo\Type;

use function OpenSoutheners\ExtendedPhp\Strings\is_json_structure;
use function OpenSoutheners\LaravelDataMapper\map;

final class ObjectDataMapper extends DataMapper
{
    public function supports(MappingValue $mappingValue): bool
    {
        if (! $mappingValue->objectClass) {
            return false;
        }

        if (is_a($mappingValue->objectClass, Collection::class, true) || is_a($mappingValue->objectClass, Model::class, true)) {
            return false;
        }

        if ($mappingValue->objectClass === stdClass::class || ! class_exists($mappingValue->objectClass) || ! (new ReflectionClass($mappingValue->objectClass))->isInstantiable()) {
            return false;
        }

        return (is_array($mappingValue->data) && Arr::isAssoc($mappingValue->data))
            || (is_string($mappingValue->data) && is_json_structure($mappingValue->data));
    }

    public function resolve(MappingValue $mappingValue): mixed
    {
        $class = new ReflectionClass($mappingValue->objectClass);

        $data = [];

        $mappingData = is_string($mappingValue->data) ? json_decode($mappingValue->data, true) : $mappingValue->data;

        $propertiesData = array_combine(
            array_map(fn ($key) => $this->normalisePropertyKey($class, $key), array_keys($mappingData)),
            array_values($mappingData)
        );

        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $key = $property->getName();
            $value = $propertiesData[$key] ?? null;

            $type = app(PropertyInfoExtractor::class)->typeInfo($class->getName(), $key);

            /** @var Collection<ReflectionAttribute> $propertyAttributes */
            $propertyAttributes = Collection::make($property->getAttributes());

            $containerAttribute = $propertyAttributes->filter(
                fn (ReflectionAttribute $attribute) => is_subclass_of($attribute->getName(), ContextualAttribute::class)
            )->first();

            if ($containerAttribute) {
                $data[$key] = $this->resolveContainerAttribute($containerAttribute, $class, $property);

                continue;
            }

            if (is_null($value)) {
                continue;
            }

            $unwrappedType = app(PropertyInfoExtractor::class)->unwrapType($type);

            if ($type instanceof Type\NullableType) {
                $type = $type->getWrappedType();
            }

            $path = ($mappingValue->path ? $mappingValue->path.'.' : '').$property->getName();

            if ($type instanceof Type\CollectionType) {
                $collectionValueType = $type->getCollectionValueType();

                $data[$key] = map($value)
                    ->withContext($property, $path)
                    ->through((string) $unwrappedType)
                    ->to((string) $collectionValueType);

                continue;
            }

            $data[$key] = match (true) {
                $type instanceof Type\ObjectType => map($value)
                    ->withContext($property, $path)
                    ->when(
                        is_array($value) && Arr::isAssoc($value),
                        fn ($mapper) => $mapper->withProvidedKeys(array_keys($value))
                    )
                    ->to((string) $type),
                default => $value,
            };
        }

        return new $mappingValue->objectClass(...$data);
    }

    /**
     * Cache of whether the installed container's `resolveFromAttribute()` needs
     * a second `ReflectionParameter` argument (Laravel 13+) or not (11/12).
     */
    protected static ?bool $resolveFromAttributeNeedsParameter = null;

    /**
     * Resolve a container contextual attribute (e.g. `#[Authenticated]`, `#[Inject]`)
     * into its value, bridging the signature change of
     * `Container::resolveFromAttribute()` across Laravel versions: 11/12 accept a
     * single `ReflectionAttribute` argument, 13+ additionally require the
     * `ReflectionParameter` being resolved.
     *
     * @param  ReflectionAttribute<ContextualAttribute>  $containerAttribute
     * @param  ReflectionClass<object>  $class
     */
    protected function resolveContainerAttribute(ReflectionAttribute $containerAttribute, ReflectionClass $class, ReflectionProperty $property): mixed
    {
        if (! static::containerResolveFromAttributeNeedsParameter()) {
            return app()->resolveFromAttribute($containerAttribute);
        }

        return app()->resolveFromAttribute($containerAttribute, $this->constructorParameterFor($class, $property));
    }

    /**
     * Whether the container's `resolveFromAttribute()` method requires a second
     * `ReflectionParameter` argument on the currently installed Laravel version.
     *
     * Detected via reflection (not version sniffing) and cached statically since
     * the installed framework's signature never changes within a request.
     */
    protected static function containerResolveFromAttributeNeedsParameter(): bool
    {
        return static::$resolveFromAttributeNeedsParameter ??= (new ReflectionMethod(Container::class, 'resolveFromAttribute'))->getNumberOfParameters() > 1;
    }

    /**
     * Find the constructor parameter matching a property carrying a contextual
     * attribute, needed to satisfy Laravel 13+'s `resolveFromAttribute()` signature.
     *
     * Constructor-promoted properties always have one; a plain property with a
     * same-named constructor parameter is also supported. Anything else can't be
     * resolved and throws a clear exception instead of the confusing
     * `ArgumentCountError` this replaces.
     *
     * @param  ReflectionClass<object>  $class
     */
    protected function constructorParameterFor(ReflectionClass $class, ReflectionProperty $property): ReflectionParameter
    {
        $constructor = $class->getConstructor();

        foreach ($constructor?->getParameters() ?? [] as $constructorParameter) {
            if ($constructorParameter->getName() === $property->getName()) {
                return $constructorParameter;
            }
        }

        throw UnresolvableContextualAttributeException::forProperty($class, $property);
    }

    /**
     * Normalise property key using camel case or original.
     */
    protected function normalisePropertyKey(ReflectionClass $class, string $key): ?string
    {
        $normaliseProperty = count($class->getAttributes(NormaliseProperties::class)) > 0
            ?: (app('config')->get('data-mapper.normalise_properties') ?? true);

        if (! $normaliseProperty) {
            return $key;
        }

        if (Str::endsWith($key, '_id')) {
            $key = Str::replaceLast('_id', '', $key);
        }

        $camelKey = Str::camel($key);

        return match (true) {
            property_exists($class->getName(), $key) => $key,
            property_exists($class->getName(), $camelKey) => $camelKey,
            default => null
        };
    }
}
