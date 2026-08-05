<?php

namespace OpenSoutheners\LaravelDataMapper\Support;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use OpenSoutheners\LaravelDataMapper\Attributes\AsType;
use OpenSoutheners\LaravelDataMapper\Contracts\MappableObject;
use OpenSoutheners\LaravelDataMapper\MappingValue;
use OpenSoutheners\LaravelDataMapper\PropertyInfoExtractor;
use ReflectionClass;
use Stringable;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;

use function OpenSoutheners\ExtendedPhp\Enums\enum_is_backed;
use function OpenSoutheners\ExtendedPhp\Enums\is_enum;

final class TypeScript implements MappableObject, Stringable
{
    public function __construct(
        private array $script = [],
        private array $exportTypes = []
    ) {
        //
    }

    public function __toString(): string
    {
        $result = '';

        foreach ($this->script as $exportName => $types) {
            $exportType = $this->exportTypes[$exportName] ?? 'type';

            $result .= "export {$exportType} {$exportName} ";

            if ($exportType === 'type') {
                $result .= '= ';
            }

            $result .= is_string($types) ? $types : ("{\n".implode(",\n", $types).",\n};\n");
            $result .= "\n";
        }

        return $result;
    }

    public function mappingFrom(MappingValue $mappingValue): mixed
    {
        return $this->fromClass($mappingValue->data);
    }

    public function fromClass(string $class): self
    {
        if (is_a($class, Model::class, true)) {
            $this->fromModelObject($class);

            return $this;
        }

        if (is_enum($class)) {
            $this->fromEnum($class);

            return $this;
        }

        $properties = app(PropertyInfoExtractor::class)->typeInfoFromClass($class);

        $typeName = $this->typeName($class);
        $this->script[$typeName] = [];

        foreach ($properties as $propertyName => $type) {
            $this->script[$typeName][] = "{$propertyName}: {$this->fromType($type)}";
        }

        return $this;
    }

    private function typeName(string $class): string
    {
        $reflectionClass = new ReflectionClass($class);

        $attributes = $reflectionClass->getAttributes(AsType::class);

        $asTypeAttribute = reset($attributes);

        if ($asTypeAttribute) {
            return $asTypeAttribute->newInstance()->typeName;
        }

        return class_basename($class);
    }

    private function fromModelObject(string $class)
    {
        $columns = Schema::getColumns((new $class)->getTable());

        $typeName = $this->typeName($class);

        if (isset($this->script[$typeName])) {
            return $typeName;
        }

        $this->script[$typeName] = [];

        foreach ($columns as $column) {
            $type = match ($column['type_name']) {
                'int2' => 'number',
                'int4' => 'number',
                'int8' => 'number',
                'smallint' => 'number',
                'bigserial' => 'number',
                'serial' => 'number',
                'boolean' => 'bool',
                'float' => 'number',
                'double' => 'number',
                'decimal' => 'number',
                'numeric' => 'string',
                'varchar' => 'string',
                'text' => 'string',
                'date' => 'string',
                'timestamp' => 'string',
                'timestamptz' => 'string',
                'time' => 'string',
                'interval' => 'string',
                'json' => 'string',
                'jsonb' => 'string',
                'bytea' => 'string',
                'oid' => 'number',
                'cidr' => 'string',
                'inet' => 'string',
                'macaddr' => 'string',
                'uuid' => 'string',
                default => 'any',
            };

            $type .= $column['nullable'] ? ' | null' : '';

            $this->script[$typeName][] = "{$column['name']}: {$type}";
        }
    }

    private function fromType(Type $type): string
    {
        return match (true) {
            $type instanceof Type\UnionType => $this->fromUnionType($type),
            $type instanceof Type\CollectionType => $this->fromCollectionType($type),
            $type instanceof Type\ObjectType => $this->fromObjectType($type),
            $type instanceof Type\BuiltinType => $this->fromBuiltinType($type),
            $type instanceof Type\EnumType => $this->fromClass($type->getClassName()),
            default => 'any',
        };
    }

    private function fromCollectionType(Type\CollectionType $type): string
    {
        $collectionKeyType = $this->fromType($type->getCollectionKeyType());
        $collectionValueType = $type->getCollectionValueType();
        $collectionValueType = $this->fromType($collectionValueType instanceof Type\CollectionType ? $collectionValueType->getWrappedType() : $collectionValueType);

        if ($collectionKeyType === 'int | string') {
            return "Array<{$collectionValueType}>";
        }

        return "Record<{$collectionKeyType}, {$collectionValueType}>";
    }

    private function fromUnionType(Type\UnionType $type): string
    {
        $types = array_map(fn (Type $childrenType) => $this->fromType($childrenType), $type->getTypes());

        return implode(' | ', $types);
    }

    /**
     * @param  class-string<BackedEnum>  $class
     */
    private function fromEnum(string $class): void
    {
        if (! enum_is_backed($class)) {
            throw new \Exception('Non backed enums are not supported');
        }

        $typeName = $this->typeName($class);

        $this->script[$typeName] = [];
        $this->exportTypes[$typeName] = 'enum';

        foreach ($class::cases() as $case) {
            $this->script[$typeName][] = "{$case->name} = {$case->value}";
        }
    }

    private function fromObjectType(Type\ObjectType $type): string
    {
        $class = $type->getClassName();

        if (is_a($class, Collection::class, true)) {
            return 'Array<unknown>';
        }

        $this->fromClass($class);

        return array_key_last($this->script);
    }

    private function fromBuiltinType(Type\BuiltinType $type): string
    {
        return match ($type->getTypeIdentifier()) {
            TypeIdentifier::ARRAY => 'Array<unknown>',
            TypeIdentifier::BOOL => 'boolean',
            // TODO: Remove callable
            TypeIdentifier::CALLABLE => 'unknown',
            TypeIdentifier::FLOAT => 'number',
            TypeIdentifier::INT => 'number',
            TypeIdentifier::MIXED => 'unknown',
            TypeIdentifier::NULL => 'null',
            TypeIdentifier::OBJECT => 'object',
            TypeIdentifier::STRING => 'string',
            TypeIdentifier::VOID => 'undefined',
            TypeIdentifier::NEVER => 'never',
        };
    }
}
