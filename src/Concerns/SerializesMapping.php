<?php

namespace OpenSoutheners\LaravelDataMapper\Concerns;

use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionProperty;
use Stringable;

use function OpenSoutheners\LaravelDataMapper\map;

/**
 * Opt-in queue-friendly serialisation for mapped DTOs/POPOs, mirroring what
 * `Illuminate\Queue\SerializesModels` does for job properties but reusing the
 * package's own mapper pipeline to rebuild values on the way back in.
 *
 * `__serialize()` collapses every PUBLIC property down to plain scalars/arrays
 * so a queue payload never embeds full Eloquent attribute data: Models become
 * their primary key, Collections/arrays are walked item by item (a
 * `Collection<Model>` therefore naturally becomes an array of keys), backed
 * enums become their scalar `value`, Carbon instances become an ISO-8601
 * string, and nested objects using this same trait recurse into their own
 * `__serialize()` payload.
 *
 * `__unserialize()` rebuilds the instance by re-running the collapsed array
 * back through `map()->to(static::class)` - the same mapper pipeline that
 * built the instance in the first place - which re-queries Models by key,
 * re-parses Carbon strings and re-resolves enums, then copies the resulting
 * instance's public properties onto `$this`.
 *
 * @phpstan-ignore trait.unused (opt-in trait, only ever consumed by DTOs
 *     outside `phpstan.neon`'s `paths: src` analysis scope, e.g. under
 *     `workbench/`)
 */
trait SerializesMapping
{
    /**
     * Collapse this instance's public properties into a queue-safe payload.
     */
    public function __serialize(): array
    {
        $serialised = [];

        foreach ($this->serialisableProperties() as $property) {
            $serialised[$property->getName()] = $this->serialiseValue($property->getValue($this));
        }

        return $serialised;
    }

    /**
     * Rebuild the instance from a payload previously produced by `__serialize()`
     * by re-running it through the mapper pipeline (which re-queries Models,
     * re-parses dates and re-resolves enums) and copying the result's public
     * properties onto `$this`.
     *
     * Readonly properties are settable here because `$this` is a fresh
     * instance produced by PHP's unserialisation machinery: none of its typed
     * properties (readonly or otherwise) have been initialised yet, and this
     * method runs in the declaring class's scope (traits are compiled into
     * the using class), which is exactly the scope readonly properties allow
     * a first write from.
     */
    public function __unserialize(array $data): void
    {
        $rebuilt = map($data)->to(static::class);

        $reflection = new ReflectionClass($rebuilt);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->hasType() && ! $property->isInitialized($rebuilt)) {
                continue;
            }

            $this->{$property->getName()} = $property->getValue($rebuilt);
        }
    }

    /**
     * This instance's public, initialised properties - readonly/typed
     * properties that were never set (e.g. left at their uninitialised
     * default) are skipped rather than raising an "must not be accessed
     * before initialization" error.
     *
     * @return array<ReflectionProperty>
     */
    protected function serialisableProperties(): array
    {
        $reflection = new ReflectionClass($this);

        return array_values(array_filter(
            $reflection->getProperties(ReflectionProperty::IS_PUBLIC),
            fn (ReflectionProperty $property) => ! $property->hasType() || $property->isInitialized($this)
        ));
    }

    /**
     * Collapse a single property value into its serialisable form, recursing
     * into arrays/collections/nested mapped objects.
     */
    protected function serialiseValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof Model => $value->getKey(),
            $value instanceof Collection => $value->map(fn ($item) => $this->serialiseValue($item))->all(),
            $value instanceof BackedEnum => $value->value,
            $value instanceof CarbonInterface => $value->toIso8601String(),
            is_array($value) => array_map(fn ($item) => $this->serialiseValue($item), $value),
            is_object($value) => $this->serialiseObject($value),
            default => $value,
        };
    }

    /**
     * Collapse an object value that isn't one of the well-known mapped types
     * (Model, Collection, BackedEnum, Carbon).
     *
     * A nested object also using this trait recurses into its own
     * `__serialize()` payload - `__unserialize()` rebuilds it the same way it
     * rebuilds `$this`, through `map($value)->to($propertyType)`, since
     * `ObjectDataMapper` already knows from the property's type how to turn a
     * plain associative array back into that nested object.
     *
     * Anything else (including `MappableObject` implementations, which don't
     * expose a canonical scalar to collapse to) falls back to its
     * `__toString()` representation if it has one, otherwise its own public
     * properties are extracted and recursed into - the same "collapse public
     * properties" rule this trait applies to `$this`, just without the
     * readonly/uninitialised-property bookkeeping that's only needed when
     * writing back onto a fresh unserialised instance.
     */
    protected function serialiseObject(object $value): mixed
    {
        return match (true) {
            in_array(SerializesMapping::class, class_uses_recursive($value), true) => $value->__serialize(),
            $value instanceof Stringable => (string) $value,
            default => array_map(fn ($item) => $this->serialiseValue($item), get_object_vars($value)),
        };
    }
}
