<?php

namespace OpenSoutheners\LaravelDataMapper\Mappers;

use Illuminate\Contracts\Container\ContextualAttribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSoutheners\LaravelDataMapper\Attributes\NormaliseProperties;
use OpenSoutheners\LaravelDataMapper\MappingValue;
use OpenSoutheners\LaravelDataMapper\PropertyInfoExtractor;
use ReflectionAttribute;
use ReflectionClass;
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

            /** @var \Illuminate\Support\Collection<\ReflectionAttribute> $propertyAttributes */
            $propertyAttributes = Collection::make($property->getAttributes());

            $containerAttribute = $propertyAttributes->filter(
                fn (ReflectionAttribute $attribute) => is_subclass_of($attribute->getName(), ContextualAttribute::class)
            )->first();

            if ($containerAttribute) {
                $data[$key] = app()->resolveFromAttribute($containerAttribute);

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
