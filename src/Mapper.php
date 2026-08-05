<?php

namespace OpenSoutheners\LaravelDataMapper;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Conditionable;
use OpenSoutheners\LaravelDataMapper\Exceptions\NoMapperFoundException;
use ReflectionClass;
use ReflectionProperty;

final class Mapper
{
    use Conditionable;

    protected mixed $data;

    protected ?string $dataClass = null;

    protected ?string $throughClass = null;

    protected ?ReflectionProperty $property = null;

    protected ?string $path = null;

    protected array $providedKeys = [];

    public function __construct(mixed $input)
    {
        if (is_object($input)) {
            $this->dataClass = get_class($input);
        }

        $this->data = $this->takeDataFrom($input);
    }

    protected function extractProperties(object $input): array
    {
        $reflector = new ReflectionClass($input);
        $extraction = [];

        foreach ($reflector->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $property->isReadOnly();
            $extraction[$property->getName()] = $property->getValue($input);
        }

        return $extraction;
    }

    protected function takeDataFrom(mixed $input): mixed
    {
        return match (true) {
            $input instanceof Request => array_merge(
                is_object($input->route()) ? $input->route()->parameters() : [],
                $input instanceof FormRequest ? $input->validated() : $input->all()
            ),
            $input instanceof Collection => $input,
            $input instanceof Model => $input,
            is_object($input) => $this->extractProperties($input),
            default => $input,
        };
    }

    /**
     * Map values through class.
     */
    public function through(string $class): static
    {
        $this->throughClass = $class;

        return $this;
    }

    /**
     * Carry nested mapping context (property and path) into the resulting mapping value.
     */
    public function withContext(?ReflectionProperty $property = null, ?string $path = null): static
    {
        $this->property = $property;
        $this->path = $path;

        return $this;
    }

    /**
     * Set the normalised incoming keys provided for the current mapping.
     */
    public function withProvidedKeys(array $keys): static
    {
        $this->providedKeys = $keys;

        return $this;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $output
     * @return T
     */
    public function to(?string $output = null)
    {
        $output ??= $this->dataClass;

        if (! $this->throughClass && (is_array($this->data) || $this->data instanceof Collection)) {
            $this->throughClass = is_array($this->data) ? 'array' : Collection::class;
        }

        $mappingValue = new MappingValue(
            data: $this->data,
            objectClass: $output,
            collectClass: $this->throughClass,
            property: $this->property,
            path: $this->path,
            providedKeys: $this->providedKeys,
        );

        $mapper = app(MapperRegistry::class)->resolveFor($mappingValue);

        if (! $mapper) {
            throw NoMapperFoundException::forValue($mappingValue);
        }

        return $mapper($mappingValue);
    }
}
