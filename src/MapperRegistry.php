<?php

namespace OpenSoutheners\LaravelDataMapper;

final class MapperRegistry
{
    /**
     * @var array<int, array{mapper: string|Mappers\DataMapper, priority: int}>
     */
    protected array $registrations = [];

    /**
     * @var array<class-string<Mappers\DataMapper>, Mappers\DataMapper>
     */
    protected array $instances = [];

    /**
     * Register a mapper with the given priority.
     *
     * Higher priority mappers are considered first when resolving, ties are
     * broken by registration order (earlier registrations win).
     */
    public function register(string|Mappers\DataMapper $mapper, int $priority = 0): void
    {
        $this->registrations[] = compact('mapper', 'priority');
    }

    /**
     * Resolve the first registered mapper that supports the given mapping value.
     */
    public function resolveFor(MappingValue $value): ?Mappers\DataMapper
    {
        foreach ($this->all() as $mapper) {
            if ($mapper->supports($value)) {
                return $mapper;
            }
        }

        return null;
    }

    /**
     * Get all registered mapper instances, ordered by priority (descending)
     * then registration order.
     *
     * @return array<int, Mappers\DataMapper>
     */
    public function all(): array
    {
        $registrations = $this->registrations;

        usort($registrations, fn (array $a, array $b) => $b['priority'] <=> $a['priority']);

        return array_map(
            fn (array $registration) => $this->resolveInstance($registration['mapper']),
            $registrations
        );
    }

    /**
     * Resolve (and cache) a mapper instance from its class string, or return it as-is.
     */
    protected function resolveInstance(string|Mappers\DataMapper $mapper): Mappers\DataMapper
    {
        if ($mapper instanceof Mappers\DataMapper) {
            return $mapper;
        }

        return $this->instances[$mapper] ??= app()->make($mapper);
    }
}
