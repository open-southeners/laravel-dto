<?php

namespace OpenSoutheners\LaravelDto;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSoutheners\LaravelDto\Attributes\AsType;
use OpenSoutheners\LaravelDto\Attributes\NormaliseProperties;
use ReflectionClass;
use Symfony\Component\PropertyInfo\Type;

class TypeGenerator
{
    public const PHP_TO_TYPESCRIPT_VARIANT_TYPES = [
        'int' => 'number',
        'float' => 'number',
        'bool' => 'boolean',
        '\stdClass' => 'object',
    ];

    public function __construct(protected string $dataTransferObject, protected Collection $garbageCollection)
    {
        // 
    }

    public function generate(): void
    {
        $reflection = new ReflectionClass($this->dataTransferObject);

        $normalisesPropertiesKeys = config('data-transfer-objects.normalise_properties', true);
        
        if (! empty($reflection->getAttributes(NormaliseProperties::class))) {
            $normalisesPropertiesKeys = true;
        }

        /** @var array<\ReflectionProperty> $properties */
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        $propertyInfoExtractor = PropertiesMapper::propertyInfoExtractor();

        $exportedType = $this->getExportTypeName($reflection);
        $exportAsString = "export type {$exportedType} = {\n";
        
        foreach ($properties as $property) {
            /** @var array<\Symfony\Component\PropertyInfo\Type> $propertyTypes */
            $propertyTypes = $propertyInfoExtractor->getTypes($this->dataTransferObject, $property->getName());
            $propertyType = reset($propertyTypes);

            $propertyTypeClass = $propertyType->getClassName();

            if (is_a($propertyTypeClass, Authenticatable::class, true)) {
                continue;
            }

            $nullMark = $propertyType->isNullable() ? '?' : '';
            
            $propertyTypeAsString = $this->extractTypeFromPropertyType($propertyType);
            $propertyKeyAsString = $property->getName();

            if ($normalisesPropertiesKeys) {
                $propertyKeyAsString = Str::camel($propertyKeyAsString);
                $propertyKeyAsString .= is_subclass_of($propertyTypeClass, Model::class) ? '_id' : '';
            }

            $exportAsString .= "\t{$propertyKeyAsString}{$nullMark}: {$propertyTypeAsString};\n";
        }

        $exportAsString .= "};";

        $this->garbageCollection[$exportedType] = $exportAsString;
    }

    protected function getExportTypeName(ReflectionClass $reflection): string
    {
        /** @var array<\ReflectionAttribute<\OpenSoutheners\LaravelDto\Attributes\AsType>> $classAttributes */
        $classAttributes = $reflection->getAttributes(AsType::class);

        $classAttribute = reset($classAttributes);

        if (! $classAttribute) {
            return $reflection->getShortName();
        }

        return $classAttribute->newInstance()->typeName;
    }

    protected function extractTypeFromPropertyType(Type $propertyType): string
    {
        $propertyBuiltInType = $propertyType->getBuiltinType();
        $propertyTypeString = $propertyType->getClassName() ?? $propertyBuiltInType;

        return match (true) {
            $propertyType->isCollection() => $this->extractCollectionType($propertyTypeString, $propertyType->getCollectionValueTypes()),
            is_a($propertyTypeString, Model::class, true) => $this->extractModelType($propertyTypeString),
            is_a($propertyTypeString, \BackedEnum::class, true) => $this->extractEnumType($propertyTypeString),
            $propertyBuiltInType === 'object' && $propertyBuiltInType !== $propertyTypeString => $this->extractObjectType($propertyTypeString),
            default => $this->builtInTypeToTypeScript($propertyType->getBuiltinType()),
        };
    }

    protected function builtInTypeToTypeScript(string $identifier): string
    {
        return static::PHP_TO_TYPESCRIPT_VARIANT_TYPES[$identifier] ?? $identifier;
    }

    /**
     * Summary of extractObjectType
     */
    protected function extractObjectType(string $objectClass): string
    {
        (new self($objectClass, $this->garbageCollection))->generate();

        return class_basename($objectClass);
    }

    /**
     * Summary of extractEnumType
     * 
     * @see https://www.typescriptlang.org/docs/handbook/enums.html#objects-vs-enums
     */
    protected function extractEnumType(string $enumClass): string
    {
        $exportedType = class_basename($enumClass);

        if ($this->garbageCollection->has($exportedType)) {
            return $exportedType;
        }
        
        $exportsAsString = '';
        $exportsAsString .= "export const enum {$exportedType} {\n";
        
        foreach ($enumClass::cases() as $case) {
            $caseValueAsString = is_int($case->value) ? $case->value : "\"{$case->value}\"";
            $exportsAsString .= "\t{$case->name} = {$caseValueAsString},\n";
        }

        $exportsAsString .= "};";

        $this->garbageCollection[$exportedType] = $exportsAsString;

        return $exportedType;
    }

    /**
     * Summary of getCollectionType
     * 
     * @param array<\Symfony\Component\PropertyInfo\Type> $collectedTypes
     */
    protected function extractCollectionType(string $collection, array $collectedTypes): string
    {
        $collectedType = reset($collectedTypes);

        return $this->extractTypeFromPropertyType($collectedType);
    }

    protected function extractModelType(string $modelClass): string
    {
        // TODO: Check type from Model's property's attribute or getRouteKeyName as fallback
        // TODO: To be able to do the above need to generate types from models
        return 'string';
    }
}