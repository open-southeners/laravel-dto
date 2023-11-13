<?php

namespace OpenSoutheners\LaravelDto;

use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSoutheners\LaravelDto\Attributes\BindModel;
use OpenSoutheners\LaravelDto\Attributes\NormaliseProperties;
use OpenSoutheners\LaravelDto\Attributes\WithDefaultValue;
use function OpenSoutheners\LaravelHelpers\Strings\is_json_structure;
use ReflectionAttribute;
use ReflectionClass;
use stdClass;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
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
        $phpStanExtractor = new PhpStanExtractor();
        $reflectionExtractor = new ReflectionExtractor();

        return new PropertyInfoExtractor(
            [$reflectionExtractor],
            [$phpStanExtractor, $reflectionExtractor],
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

        $propertiesData = array_combine(
            array_map(fn ($key) => $this->normalisePropertyKey($key), array_keys($this->properties)),
            array_values($this->properties)
        );

        foreach ($propertyInfoExtractor->getProperties($this->dataClass) as $key) {
            $value = $propertiesData[$key] ?? null;

            /** @var array<\Symfony\Component\PropertyInfo\Type> $propertyTypes */
            $propertyTypes = $propertyInfoExtractor->getTypes($this->dataClass, $key) ?? [];

            if (count($propertyTypes) === 0) {
                $this->data[$key] = $value;

                continue;
            }

            $preferredType = reset($propertyTypes);
            $propertyTypesClasses = array_filter(array_map(fn (Type $type) => $type->getClassName(), $propertyTypes));
            $propertyTypesModelClasses = array_filter($propertyTypesClasses, fn ($typeClass) => is_a($typeClass, Model::class, true));
            $preferredTypeClass = $preferredType->getClassName();

            /** @var \Illuminate\Support\Collection<\ReflectionAttribute> $propertyAttributes */
            $propertyAttributes = Collection::make(
                $this->reflector->getProperty($key)->getAttributes()
            );

            $propertyAttributesDefaultValue = $propertyAttributes->filter(
                fn (\ReflectionAttribute $attribute) => $attribute->getName() === WithDefaultValue::class
            )->first();

            $defaultValue = null;

            if (! $value && $propertyAttributesDefaultValue) {
                $defaultValue = $propertyAttributesDefaultValue->newInstance()->value;
            }

            if (
                ! $value
                && ($preferredTypeClass === Authenticatable::class || $defaultValue === Authenticatable::class)
                && app('auth')->check()
            ) {
                $this->data[$key] = app('auth')->user();

                continue;
            }

            $value ??= $defaultValue;

            if (is_null($value)) {
                continue;
            }

            if (
                $preferredTypeClass
                && ! is_array($value)
                && ! $preferredType->isCollection()
                && $preferredTypeClass !== Collection::class
                && ! is_a($preferredTypeClass, Model::class, true)
                && (is_a($value, $preferredTypeClass, true)
                    || (is_object($value) && in_array(get_class($value), $propertyTypesClasses)))
            ) {
                $this->data[$key] = $value;

                continue;
            }

            $this->data[$key] = match (true) {
                $preferredType->isCollection() || $preferredTypeClass === Collection::class || $preferredTypeClass === EloquentCollection::class => $this->mapIntoCollection($propertyTypes, $key, $value, $propertyAttributes),
                $preferredTypeClass === Model::class || is_subclass_of($preferredTypeClass, Model::class) => $this->mapIntoModel(count($propertyTypesModelClasses) === 1 ? $preferredTypeClass : $propertyTypesClasses, $key, $value, $propertyAttributes),
                is_subclass_of($preferredTypeClass, BackedEnum::class) => $preferredTypeClass::tryFrom($value) ?? (count($propertyTypes) > 1 ? $value : null),
                is_subclass_of($preferredTypeClass, CarbonInterface::class) || $preferredTypeClass === CarbonInterface::class => $this->mapIntoCarbonDate($preferredTypeClass, $value),
                $preferredTypeClass === stdClass::class && is_array($value) => (object) $value,
                $preferredTypeClass === stdClass::class && Str::isJson($value) => json_decode($value),
                $preferredTypeClass && class_exists($preferredTypeClass) && (new ReflectionClass($preferredTypeClass))->isInstantiable() && is_array($value) && is_string(array_key_first($value)) => new $preferredTypeClass(...$value),
                $preferredTypeClass && class_exists($preferredTypeClass) && (new ReflectionClass($preferredTypeClass))->isInstantiable() && Str::isJson($value) => new $preferredTypeClass(...json_decode($value, true)),
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
     * @param  string|int|\Illuminate\Database\Eloquent\Model  $id
     * @param  string|\Illuminate\Database\Eloquent\Model  $usingAttribute
     */
    protected function getModelInstance(string $model, mixed $id, mixed $usingAttribute, array $with)
    {
        if (is_a($usingAttribute, $model)) {
            return $usingAttribute;
        }

        if (is_a($id, $model)) {
            return empty($with) ? $id : $id->loadMissing($with);
        }

        $baseQuery = $model::query()->when(
            $usingAttribute,
            fn (Builder $query) => is_iterable($id) ? $query->whereIn($usingAttribute, $id) : $query->where($usingAttribute, $id),
            fn (Builder $query) => $query->whereKey($id)
        );

        if (count($with) > 0) {
            $baseQuery->with($with);
        }

        if (is_iterable($id)) {
            return $baseQuery->get();
        }

        return $baseQuery->first();
    }

    /**
     * Normalise property key using camel case or original.
     */
    protected function normalisePropertyKey(string $key): ?string
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
     * @param  class-string<\Illuminate\Database\Eloquent\Model>|array<class-string<\Illuminate\Database\Eloquent\Model>>  $modelClass
     * @param  \Illuminate\Support\Collection<\ReflectionAttribute>  $attributes
     */
    protected function mapIntoModel(string|array $modelClass, string $propertyKey, mixed $value, Collection $attributes)
    {
        /** @var \ReflectionAttribute<\OpenSoutheners\LaravelDto\Attributes\BindModel>|null $bindModelAttribute */
        $bindModelAttribute = $attributes
            ->filter(fn (ReflectionAttribute $reflection) => $reflection->getName() === BindModel::class)
            ->first();

        /** @var \OpenSoutheners\LaravelDto\Attributes\BindModel|null $bindModelAttribute */
        $bindModelAttribute = $bindModelAttribute
            ? $bindModelAttribute->newInstance()
            : new BindModel(morphTypeKey: BindModel::getDefaultMorphKeyFrom($propertyKey));

        $modelType = $modelClass;
        $valueClass = null;

        if (is_object($value) && ! $value instanceof Collection) {
            $valueClass = get_class($value);
            $modelType = is_array($modelClass) ? ($modelClass[$valueClass] ?? null) : $valueClass;
        }

        if (
            (! is_array($modelType) && $modelType === Model::class)
            || ($bindModelAttribute && is_array($modelClass))
        ) {
            $modelType = $bindModelAttribute->getMorphModel(
                $propertyKey,
                $this->properties,
                $modelClass === Model::class ? [] : (array) $modelClass
            );
        }

        $usingAttribute = null;
        $with = [];

        if ($bindModelAttribute) {
            $with = $bindModelAttribute->getRelationshipsFor($modelType);
            $usingAttribute = $bindModelAttribute->getBindingAttribute($propertyKey, $modelType, $with);
        }

        return $this->getModelInstance($modelType, $value, $usingAttribute, $with);
    }

    /**
     * Map data value into Carbon date/datetime instance.
     */
    public function mapIntoCarbonDate($carbonClass, mixed $value): ?CarbonInterface
    {
        if ($carbonClass === CarbonImmutable::class) {
            return CarbonImmutable::make($value);
        }

        return Carbon::make($value);
    }

    /**
     * Map data value into collection of items with subtypes.
     *
     * @param  array<\Symfony\Component\PropertyInfo\Type>  $propertyTypes
     * @param  \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection|array|string  $value
     * @param  \Illuminate\Support\Collection<\ReflectionAttribute>  $attributes
     */
    protected function mapIntoCollection(array $propertyTypes, string $propertyKey, mixed $value, Collection $attributes)
    {
        if ($value instanceof Collection) {
            return $value instanceof EloquentCollection ? $value->toBase() : $value;
        }

        $propertyType = reset($propertyTypes);

        if (
            count(array_filter($propertyTypes, fn (Type $type) => $type->getBuiltinType() === Type::BUILTIN_TYPE_STRING)) > 0
            && ! str_contains($value, ',')
        ) {
            return $value;
        }

        if (is_json_structure($value)) {
            $collection = Collection::make(json_decode($value, true));
        } else {
            $collection = Collection::make(
                is_array($value)
                    ? $value
                    : explode(',', $value)
            );
        }

        $collectionTypes = $propertyType->getCollectionValueTypes();

        $preferredCollectionType = reset($collectionTypes);
        $preferredCollectionTypeClass = $preferredCollectionType ? $preferredCollectionType->getClassName() : null;

        $collection = $collection->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter();

        if ($preferredCollectionType && $preferredCollectionType->getBuiltinType() === Type::BUILTIN_TYPE_OBJECT) {
            if (is_subclass_of($preferredCollectionTypeClass, Model::class)) {
                $collectionTypeModelClasses = array_filter(
                    array_map(fn (Type $type) => $type->getClassName(), $collectionTypes),
                    fn ($typeClass) => is_a($typeClass, Model::class, true)
                );

                $collection = $this->mapIntoModel(
                    count($collectionTypeModelClasses) === 1 ? $preferredCollectionTypeClass : $collectionTypeModelClasses,
                    $propertyKey,
                    $collection,
                    $attributes
                );
            } else {
                $collection = $collection->map(
                    fn ($item) => is_array($item)
                        ? new $preferredCollectionTypeClass(...$item)
                        : new $preferredCollectionTypeClass($item)
                );
            }
        }

        if ($propertyType->getBuiltinType() === Type::BUILTIN_TYPE_ARRAY) {
            $collection = $collection->all();
        }

        return $collection;
    }
}
