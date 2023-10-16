<?php

namespace OpenSoutheners\LaravelDto\Attributes;

use Attribute;
use Exception;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

#[Attribute(Attribute::TARGET_PROPERTY)]
class BindModel
{
    public function __construct(
        public string|array|null $using = null, 
        public string|array $with = [],
        public string|null $morphTypeKey = null
    ) {
        //
    }

    public static function getDefaultMorphKeyFrom(string $key): string
    {
        return "{$key}_type";
    }

    /**
     * L.
     */
    public function getBindingAttribute(string $key, string $type): string|array|null
    {
        $usingAttribute = $this->using;
        
        if (is_array($usingAttribute)) {
            $typeModel = array_flip(Relation::morphMap())[$type];

            $usingAttribute = $this->using[$typeModel] ?? null;
        }

        /** @var \Illuminate\Http\Request|null $request */
        $request = app(Request::class);

        if (! $usingAttribute && $request && $request->route($key)) {
            return $request->route()->bindingFieldFor($key);
        }

        return $usingAttribute;
    }

    public function getRelationshipsFor(string $type): array
    {
        $withRelations = (array) $this->with;

        $withRelations = $withRelations[$type] ?? $withRelations;

        return (array) $withRelations;
    }

    public function getMorphPropertyTypeKey(string $fromPropertyKey): string
    {
        return $this->morphTypeKey ?? static::getDefaultMorphKeyFrom($fromPropertyKey);
    }

    public function getMorphModel(string $fromPropertyKey, array $properties, array $propertyTypeClasses): string
    {
        $morphTypePropertyKey = $this->getMorphPropertyTypeKey($fromPropertyKey);

        $type = $properties[$morphTypePropertyKey] ?? null;

        if (! $type) {
            throw new Exception('Morph type must be specified to be able to bind a model from a morph.');
        }

        $morphMap = Relation::morphMap();
        $modelModelClass = $morphMap[$type] ?? null;

        if (! $modelModelClass && count($propertyTypeClasses) > 0) {
            $modelModelClass = array_filter($propertyTypeClasses, fn (string $class) => (new $class)->getMorphClass() === $type);
            
            $modelModelClass = reset($modelModelClass);
        }
        
        if (! $modelModelClass) {
            throw new Exception('Morph type not found on relation map or within types.');
        }

        return $modelModelClass;
    }
}
