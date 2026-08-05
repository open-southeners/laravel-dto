<?php

namespace OpenSoutheners\LaravelDataMapper\Mappers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as DatabaseCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use OpenSoutheners\LaravelDataMapper\Attributes\ResolveModel;
use OpenSoutheners\LaravelDataMapper\MappingValue;

use function OpenSoutheners\LaravelDataMapper\map;

final class ModelDataMapper extends DataMapper
{
    public function supports(MappingValue $mappingValue): bool
    {
        return is_a($mappingValue->objectClass, Model::class, true)
            && (is_array($mappingValue->data) || is_string($mappingValue->data) || is_int($mappingValue->data));
    }

    /**
     * Resolve mapper that runs once supports returns true.
     */
    public function resolve(MappingValue $mappingValue): mixed
    {
        if (is_array($mappingValue->data) && Arr::isAssoc($mappingValue->data)) {
            /** @var Model $modelInstance */
            $modelInstance = new $mappingValue->objectClass;

            foreach ($mappingValue->data as $key => $value) {
                if ($modelInstance->isRelation($key) && $modelInstance->$key() instanceof BelongsTo) {
                    $modelInstance->$key()->associate($value);

                    continue;
                }

                if ($modelInstance->isRelation($key) && $modelInstance->$key() instanceof HasMany) {
                    $modelInstance->setRelation($key, map($value)->to(get_class($modelInstance->$key()->getModel())));

                    continue;
                }

                $modelInstance->fill([$key => $value]);
            }

            return $modelInstance;
        }

        $data = $mappingValue->data;

        if (is_string($data) && str_contains($data, ',')) {
            $data = array_filter(explode(',', $data));
        }

        $data = $this->resolveIntoModelInstance($data, $mappingValue->objectClass);

        if ($mappingValue->collectClass === Collection::class) {
            $data = $data instanceof DatabaseCollection
                ? $data->toBase()
                : Collection::make($data);
        }

        if ($mappingValue->collectClass === 'array') {
            $data = $data->all();
        }

        return $data;

        // TODO: Move to ObjectDataMapper
        // if (count($mappingValue->types) <= 1) {
        //     $mappingValue->data = $this->resolveIntoModelInstance($mappingValue->data, $mappingValue->objectClass);
        // }

        // $resolveModelAttributeReflector = $mappingValue->property->getAttributes(ResolveModel::class);

        // /** @var \ReflectionAttribute<\OpenSoutheners\LaravelDataMapper\Attributes\ResolveModel>|null $resolveModelAttributeReflector */
        // $resolveModelAttributeReflector = reset($resolveModelAttributeReflector);

        // /** @var \OpenSoutheners\LaravelDataMapper\Attributes\ResolveModel|null $resolveModelAttribute */
        // $resolveModelAttribute = $resolveModelAttributeReflector
        //     ? $resolveModelAttributeReflector->newInstance()
        //     : new ResolveModel(morphTypeFrom: ResolveModel::getDefaultMorphKeyFrom($mappingValue->property->getName()));

        // $modelClass = Collection::make($mappingValue->types ?? [$mappingValue->preferredTypeClass])
        //     ->map(fn (Type $type): string => $type->getClassName())
        //     ->filter(fn (string $typeClass): bool => is_a($typeClass, Model::class, true))
        //     ->unique()
        //     ->values()
        //     ->toArray();

        // $modelType = count($modelClass) === 1 ? reset($modelClass) : $modelClass;
        // $valueClass = null;

        // /** @var array<string, string[]>|null $modelWithAttributes */
        // $modelWithAttributes = $mappingValue->attributes
        //     ->filter(fn (ReflectionAttribute $reflection) => $reflection->getName() === ModelWith::class)
        //     ->mapWithKeys(fn (ReflectionAttribute $reflection) => [$reflection->newInstance()->type ?? $modelType => $reflection->newInstance()->relations])
        //     ->toArray();

        // if (is_array($modelType) && $mappingValue->objectClass === Collection::class) {
        //     $valueClass = get_class($data);

        //     $modelType = $modelClass[array_search($valueClass, $modelClass)];
        // }

        // if (
        //     (! is_array($modelType) && $modelType === Model::class)
        //     || ($resolveModelAttribute && is_array($modelType))
        // ) {
        //     $modelType = $resolveModelAttribute->getMorphModel(
        //         $mappingValue->property->getName(),
        //         $mappingValue->allMappingData,
        //         $mappingValue->types === Model::class ? [] : (array) $mappingValue->types
        //     );
        // }

        // if (! is_countable($modelType) || count($modelType) === 1) {
        //     return $this->resolveIntoModelInstance(
        //         $data,
        //         ! is_countable($modelType) ? $modelType : $modelType[0],
        //         $mappingValue->property->getName(),
        //         $modelWithAttributes,
        //         $resolveModelAttribute
        //     );
        // }

        // return Collection::make(
        //     array_map(
        //         function (mixed $valueA, mixed $valueB) use (&$lastNonValue): array {
        //             if (! is_null($valueB)) {
        //                 $lastNonValue = $valueB;
        //             }

        //             return [$valueA, $valueB ?? $lastNonValue];
        //         },
        //         $data,
        //         (array) $modelType
        //     )
        // )
        // ->mapToGroups(fn (array $value) => [$value[1] => $value[0]])
        // ->flatMap(fn (Collection $keys, string $model) => $this->resolveIntoModelInstance($keys, $model, $mappingValue->property->getName(), $modelWithAttributes, $resolveModelAttribute));
    }

    /**
     * Get model instance(s) for model class and given IDs.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  string|int|array|\Illuminate\Database\Eloquent\Model  $id
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
     * Resolve model class strings and keys into instances.
     *
     * @param  array<string, string[]>  $withAttributes
     */
    protected function resolveIntoModelInstance(mixed $keys, string $modelClass, ?string $propertyKey = null, array $withAttributes = [], ?ResolveModel $bindingAttribute = null): mixed
    {
        $usingAttribute = null;
        $with = [];

        if ($bindingAttribute && $propertyKey) {
            $with = $withAttributes[$modelClass] ?? [];
            $usingAttribute = $bindingAttribute->getBindingAttribute($propertyKey, $modelClass, $with);
        }

        return $this->getModelInstance($modelClass, $keys, $usingAttribute, $with);
    }
}
