<?php

namespace OpenSoutheners\LaravelDataMapper\Attributes;

use Attribute;
use Exception;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ResolveModel
{
    public function __construct(
        public string|array|null $keyFromRouteParam = null,
        public ?string $morphTypeFrom = null
    ) {
        //
    }

    public static function getDefaultMorphKeyFrom(string $key): string
    {
        return "{$key}_type";
    }

    public function getBindingAttribute(string $key, string $type, array $with)
    {
        $usingAttribute = $this->keyFromRouteParam;

        if (is_array($usingAttribute)) {
            $typeModel = array_flip(Relation::morphMap())[$type];

            $usingAttribute = $this->keyFromRouteParam[$typeModel] ?? null;
        }

        /** @var Request|null $request */
        $request = app(Request::class);

        if ($request && $request->route($key) && is_string($request->route($key))) {
            $resolvedInstance = $this->resolveBinding(
                $type,
                $request->route($key),
                $usingAttribute ?? $request->route()->bindingFieldFor($key),
                $with
            )->firstOrFail();

            $request->route()->setParameter($key, $resolvedInstance);

            return $resolvedInstance;
        }

        return $usingAttribute;
    }

    protected function resolveBinding(string $model, mixed $value, mixed $field = null, array $with = [])
    {
        $modelInstance = new $model;

        return $modelInstance->resolveRouteBindingQuery($modelInstance, $value, $field)
            ->with($with);
    }

    public function getMorphPropertyTypeKey(string $fromPropertyKey): string
    {
        if ($this->morphTypeFrom) {
            return Str::snake($this->morphTypeFrom);
        }

        return self::getDefaultMorphKeyFrom($fromPropertyKey);
    }

    public function getMorphModel(string $fromPropertyKey, array $properties, array $propertyTypeClasses = []): array
    {
        $morphTypePropertyKey = $this->getMorphPropertyTypeKey($fromPropertyKey);

        $types = array_filter(array_map('trim', explode(',', $properties[$morphTypePropertyKey] ?? '')));

        if (count($types) === 0) {
            throw new Exception('Morph type must be specified to be able to bind a model from a morph.');
        }

        $morphMap = Relation::morphMap();

        $modelModelClass = array_filter(
            array_map(
                fn (string $morphType) => $morphMap[$morphType] ?? null,
                $types
            )
        );

        if (count($modelModelClass) === 0 && count($propertyTypeClasses) > 0) {
            $modelModelClass = array_filter(
                $propertyTypeClasses,
                fn (string $class) => in_array((new $class)->getMorphClass(), $types)
            );

            $modelModelClass = reset($modelModelClass);
        }

        if (! $modelModelClass || count($modelModelClass) === 0) {
            throw new Exception('Morph type not found on relation map or within types.');
        }

        return $modelModelClass;
    }
}
