<?php

namespace OpenSoutheners\LaravelDto;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSoutheners\LaravelDto\Attributes\BindModelWith;
use OpenSoutheners\LaravelDto\Attributes\NormaliseProperties;
use ReflectionClass;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Type;

/**
 * The idea here is to leave DataTransferObject class without any properties that
 * could harm developers experience with the package.
 */
class PropertiesMapper
{
    protected ReflectionClass $reflector;

    /**
     * Get instance of property info extractor.
     */
    public static function propertyInfoExtractor(): PropertyInfoExtractor
    {
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();

        return new PropertyInfoExtractor(
            [$reflectionExtractor],
            [$phpDocExtractor, $reflectionExtractor],
        );
    }

    public function __construct(
        protected array $properties,
        protected string $dataClass,
        protected array $data = []
    ) {
        $this->reflector = new ReflectionClass($this->dataClass);
    }

    /**
     * Run properties mapper through all sent class properties.
     */
    public function run(): static
    {
        $propertyInfoExtractor = static::propertyInfoExtractor();

        foreach ($this->properties as $key => $value) {
            $propertyKey = $this->normalisePropertyKey($key);

            if (! $propertyKey) {
                continue;
            }

            $propertyTypes = $propertyInfoExtractor->getTypes($this->dataClass, $propertyKey);

            if (! $propertyTypes || count($propertyTypes) === 0) {
                $this->data[$propertyKey] = $value;

                continue;
            }

            $preferredType = reset($propertyTypes);
            $preferredTypeClass = $preferredType->getClassName();

            $this->data[$propertyKey] = match (true) {
                $preferredType->isCollection() => $this->mapIntoCollection($propertyTypes, $propertyKey, $value),
                is_subclass_of($preferredTypeClass, Model::class) => $this->mapIntoModel($preferredTypeClass, $propertyKey, $value),
                is_subclass_of($preferredTypeClass, BackedEnum::class) => $preferredTypeClass::tryFrom($value),
                default => $value,
            };
        }

        return $this;
    }

    /**
     * Get data array from mapped typed properties.
     */
    public function get(): array
    {
        return $this->data;
    }

    /**
     * Get model instance(s) for model class and given IDs.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    protected function getModelInstance(string $model, mixed $id, array $with = [])
    {
        $baseQuery = $model::query()->whereKey($id);

        if (count($with) > 0) {
            $baseQuery->with($with);
        }

        if (is_array($id)) {
            return $baseQuery->get();
        }

        return $baseQuery->first();
    }

    /**
     * Normalise property key using camel case or original.
     */
    protected function normalisePropertyKey(string $key): string|null
    {
        $normaliseProperty = count($this->reflector->getAttributes(NormaliseProperties::class)) > 0
            ?: (app('config')->get('data-transfer-objects.normalise_properties') ?? true);

        if (! $normaliseProperty) {
            return $key;
        }

        if (Str::endsWith($key, '_id')) {
            $key = Str::replaceLast('_id', '', $key);
        }

        $camelKey = Str::camel($key);

        return match (true) {
            property_exists($this->dataClass, $key) => $key,
            property_exists($this->dataClass, $camelKey) => $camelKey,
            default => null
        };
    }

    /**
     * Map data value into model instance.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    protected function mapIntoModel(string $modelClass, string $propertyKey, mixed $value)
    {
        $bindModelWithAttribute = $this->reflector->getProperty($propertyKey)->getAttributes(BindModelWith::class);
        $bindModelWithAttribute = reset($bindModelWithAttribute);

        $bindModelWithRelationships = $bindModelWithAttribute
            ? (array) $bindModelWithAttribute->newInstance()->relationships
            : [];

        return $this->getModelInstance($modelClass, $value, $bindModelWithRelationships);
    }

    /**
     * Map data value into collection of items with subtypes.
     *
     * @param  array<\Symfony\Component\PropertyInfo\Type>  $propertyTypes
     */
    protected function mapIntoCollection(array $propertyTypes, string $propertyKey, mixed $value)
    {
        $propertyType = reset($propertyTypes);

        if (
            count(array_filter($propertyTypes, fn (Type $type) => $type->getBuiltinType() === Type::BUILTIN_TYPE_STRING)) > 0
            && ! str_contains($value, ',')
        ) {
            return $value;
        }

        $collection = array_filter(explode(',', $value));

        $collectionTypes = $propertyType->getCollectionValueTypes();

        if (count($collectionTypes) === 0) {
            return $collection;
        }

        $preferredCollectionType = reset($collectionTypes);
        $preferredCollectionTypeClass = $preferredCollectionType->getClassName();

        $collection = array_map(fn ($item) => trim($item), $collection);

        if ($preferredCollectionType->getBuiltinType() === Type::BUILTIN_TYPE_OBJECT) {
            if (is_subclass_of($preferredCollectionTypeClass, Model::class)) {
                $bindModelWithAttribute = $this->reflector->getProperty($propertyKey)->getAttributes(BindModelWith::class);
                $bindModelWithAttribute = reset($bindModelWithAttribute);

                $bindModelWith = $bindModelWithAttribute ? (array) $bindModelWithAttribute->newInstance()->relationships : [];

                $collection = $this->getModelInstance($preferredCollectionTypeClass, $collection, $bindModelWith);
            } else {
                $collection = Collection::make($collection)->mapInto($preferredCollectionTypeClass);
            }
        }

        if ($propertyType->getBuiltinType() === Type::BUILTIN_TYPE_ARRAY && $collection instanceof Collection) {
            $collection = $collection->all();
        }

        return $collection;
    }
}
