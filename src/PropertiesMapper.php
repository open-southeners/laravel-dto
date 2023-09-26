<?php

namespace OpenSoutheners\LaravelDto;

use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSoutheners\LaravelDto\Attributes\BindModelUsing;
use OpenSoutheners\LaravelDto\Attributes\BindModelWith;
use OpenSoutheners\LaravelDto\Attributes\NormaliseProperties;
use ReflectionClass;
use stdClass;
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

        $propertiesData = array_combine(
            array_map(fn ($key) => $this->normalisePropertyKey($key), array_keys($this->properties)),
            array_values($this->properties)
        );

        foreach ($propertyInfoExtractor->getProperties($this->dataClass) as $key) {
            $value = $propertiesData[$key] ?? null;

            $propertyTypes = $propertyInfoExtractor->getTypes($this->dataClass, $key) ?? [];

            if (count($propertyTypes) === 0) {
                $this->data[$key] = $value;

                continue;
            }

            $preferredType = reset($propertyTypes);
            $preferredTypeClass = $preferredType->getClassName();

            if (
                ! $value
                && $preferredTypeClass === Authenticatable::class
                && app('auth')->check()
            ) {
                $this->data[$key] = app('auth')->user();

                continue;
            }

            if (is_null($value)) {
                continue;
            }

            $this->data[$key] = match (true) {
                $preferredType->isCollection() || $preferredTypeClass === Collection::class => $this->mapIntoCollection($propertyTypes, $key, $value),
                is_subclass_of($preferredTypeClass, Model::class) => $this->mapIntoModel($preferredTypeClass, $key, $value),
                is_subclass_of($preferredTypeClass, BackedEnum::class) => $preferredTypeClass::tryFrom($value),
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
     */
    protected function getModelInstance(string $model, mixed $id, string $usingAttribute = null, array $with = [])
    {
        $usingAttribute ??= (new $model)->getRouteKeyName();

        if (is_a($id, $model)) {
            return empty($with) ? $id : $id->loadMissing($with);
        }

        $baseQuery = $model::query()->whereKey($id);

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
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    protected function mapIntoModel(string $modelClass, string $propertyKey, mixed $value)
    {
        $bindModelWithAttribute = $this->reflector->getProperty($propertyKey)->getAttributes(BindModelWith::class);
        /** @var \ReflectionAttribute<\OpenSoutheners\LaravelDto\Attributes\BindModelWith>|null $bindModelWithAttribute */
        $bindModelWithAttribute = reset($bindModelWithAttribute);

        $bindModelUsingAttribute = $this->reflector->getProperty($propertyKey)->getAttributes(BindModelUsing::class);
        /** @var \ReflectionAttribute<\OpenSoutheners\LaravelDto\Attributes\BindModelUsing>|null $bindModelUsingAttribute */
        $bindModelUsingAttribute = reset($bindModelUsingAttribute);

        return $this->getModelInstance(
            $modelClass,
            $value,
            $bindModelUsingAttribute ? $bindModelUsingAttribute->newInstance()->attribute : null,
            $bindModelWithAttribute
                ? (array) $bindModelWithAttribute->newInstance()->relationships
                : []
        );
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

        $collection = Collection::make(
            is_array($value)
                ? $value
                : explode(',', $value)
        );

        $collectionTypes = $propertyType->getCollectionValueTypes();

        $preferredCollectionType = reset($collectionTypes);
        $preferredCollectionTypeClass = $preferredCollectionType ? $preferredCollectionType->getClassName() : null;

        $collection = $collection->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter()
            ->values();

        if ($preferredCollectionType && $preferredCollectionType->getBuiltinType() === Type::BUILTIN_TYPE_OBJECT) {
            if (is_subclass_of($preferredCollectionTypeClass, Model::class)) {
                $collection = $this->mapIntoModel($preferredCollectionTypeClass, $propertyKey, $collection);
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
