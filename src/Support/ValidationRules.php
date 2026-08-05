<?php

namespace OpenSoutheners\LaravelDataMapper\Support;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use OpenSoutheners\LaravelDataMapper\Attributes\Authenticated;
use OpenSoutheners\LaravelDataMapper\Attributes\Inject;
use OpenSoutheners\LaravelDataMapper\Contracts\MappableObject;
use OpenSoutheners\LaravelDataMapper\MappingValue;
use OpenSoutheners\LaravelDataMapper\PropertyInfoExtractor;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;

class ValidationRules implements Arrayable, ArrayAccess, MappableObject
{
    private array $rules = [];

    private ?ReflectionClass $class = null;

    public function fromClass(string $class): self
    {
        $this->class = new ReflectionClass($class);

        $properties = app(PropertyInfoExtractor::class)->typeInfoFromClass($class);

        foreach ($properties as $name => $type) {
            $this->rules[$name] = $this->getRulesForProperty($name, $type);
        }

        return $this;
    }

    public function mappingFrom(MappingValue $mappingValue): mixed
    {
        return $this->fromClass($mappingValue->data);
    }

    public function toArray(): array
    {
        return $this->rules;
    }

    public function getRulesForProperty(string $name, Type $type): array
    {
        $rules = $this->fromType($type);

        $reflectionProperty = $this->class->getProperty($name);

        $attributes = $reflectionProperty->getAttributes();

        $containerAttributes = array_filter($attributes, fn (ReflectionAttribute $attribute) => in_array($attribute->getName(), [Authenticated::class, Inject::class]));

        if ($reflectionProperty->hasDefaultValue() || count($containerAttributes) > 0) {
            $rules[] = 'nullable';
        }

        return array_unique($rules);
    }

    private function fromType(Type $type): array
    {
        return match (true) {
            $type instanceof Type\BuiltinType => $this->fromBuiltinType($type),
            $type instanceof Type\UnionType => $this->fromUnionType($type),
            $type instanceof Type\CollectionType => $this->fromCollectionType($type),
            $type instanceof Type\EnumType => $this->fromEnumType($type),
            $type instanceof Type\ObjectType => $this->fromObjectType($type),
            default => [],
        };
    }

    private function fromEnumType(Type\EnumType $type): array
    {
        return [Rule::enum($type->getClassName())];
    }

    private function fromObjectType(Type\ObjectType $type): array
    {
        $typeClass = $type->getClassName();

        return match (true) {
            is_a($typeClass, Model::class, true) => ['string', 'numeric'],
            default => [],
        };
    }

    private function fromCollectionType(Type\CollectionType $type): array
    {
        $valueType = $type->getCollectionValueType();

        if ($valueType instanceof Type\CollectionType) {
            return $this->fromType($valueType->getWrappedType());
        }

        return [];
    }

    private function fromUnionType(Type\UnionType $type): array
    {
        $rules = ['nullable'];

        return array_merge($rules, $this->fromType($type->getTypes()[0]));
    }

    private function fromBuiltinType(Type\BuiltinType $type): array
    {
        return match ($type->getTypeIdentifier()) {
            TypeIdentifier::STRING => ['string'],
            TypeIdentifier::INT => ['integer'],
            TypeIdentifier::FLOAT => ['numeric'],
            TypeIdentifier::BOOL => ['boolean'],
            default => [],
        };
    }

    public function offsetExists($offset): bool
    {
        return isset($this->rules[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->rules[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        $this->rules[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->rules[$offset]);
    }
}
