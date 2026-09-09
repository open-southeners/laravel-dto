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

    /**
     * The raw input as given to map(), kept untouched by takeDataFrom()'s
     * destructuring so instance passthrough in to() can compare against it.
     */
    protected readonly mixed $originalData;

    protected ?string $dataClass = null;

    protected ?string $throughClass = null;

    protected ?ReflectionProperty $property = null;

    protected ?string $path = null;

    protected array $providedKeys = [];

    public function __construct(mixed $input)
    {
        $this->originalData = $input;

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

        // Instance passthrough: an input already an instance of the resolved
        // target wins over re-mapping it (v3 semantics), so hand it back as-is
        // instead of going through registry resolution. This also sidesteps
        // takeDataFrom()'s destructuring of generic objects (enums, Carbon,
        // plain DTOs) into arrays, which would otherwise corrupt an
        // already-correct value before any mapper gets to run. Collection
        // targets are excluded on purpose: `map($collection)->to(Collection::class)`
        // keeps going through the normal through/collect resolution below,
        // which is expected to hand back a fresh Collection rather than the
        // same instance.
        //
        // No MappingResolved event is dispatched for a passthrough: no mapper
        // actually resolved anything, the value is simply handed back unchanged.
        $isPassthrough = $output !== null
            && is_object($this->originalData)
            && $this->originalData instanceof $output
            && ! is_a($output, Collection::class, true);

        if (! $isPassthrough) {
            // Through-class inference is a default for bare array/Collection input;
            // it must never contradict an explicitly Collection-typed target.
            if (
                ! $this->throughClass
                && (is_array($this->data) || $this->data instanceof Collection)
                && ($output === null || ! is_a($output, Collection::class, true))
            ) {
                $this->throughClass = is_array($this->data)
                    ? (app('config')->get('data-mapper.map_arrays_through') ?? 'array')
                    : Collection::class;
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
        }

        return $isPassthrough ? $this->originalData : $mapper($mappingValue);
    }
}
