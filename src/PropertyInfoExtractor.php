<?php

namespace OpenSoutheners\LaravelDataMapper;

use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor as Extractor;
use Symfony\Component\TypeInfo\Type;

final class PropertyInfoExtractor
{
    private Extractor $extractor;

    public function __construct()
    {
        $phpStanExtractor = new PhpStanExtractor;
        $reflectionExtractor = new ReflectionExtractor;

        $this->extractor = new Extractor(
            [$reflectionExtractor],
            [$phpStanExtractor, $reflectionExtractor],
        );
    }

    public function typeInfo(string $class, string $property, array $context = []): ?Type
    {
        return $this->extractor->getType($class, $property, $context);
    }

    /**
     * @return array<string, Type>
     */
    public function typeInfoFromClass(string $class, array $context = []): array
    {
        $classReflection = new ReflectionClass($class);

        $classProperties = $classReflection->getProperties(ReflectionProperty::IS_PUBLIC);

        $propertiesTypes = [];

        foreach ($classProperties as $property) {
            $propertiesTypes[$property->getName()] = $this->extractor->getType($class, $property->getName(), $context);
        }

        return $propertiesTypes;
    }

    public function unwrapType(Type $type): Type
    {
        $builtinType = $type;

        while (method_exists($builtinType, 'getWrappedType')) {
            $builtinType = $builtinType->getWrappedType();
        }

        return $builtinType;
    }
}
