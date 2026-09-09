---
description: >-
  Creating data transfer object classes and adding properties to them in a
  proper way so they can be mapped.
---

# Creating DTOs

A DTO is a plain PHP class — no base class, no interface required just to be mappable. `map()` reads the target class's constructor property types (plus docblock generics, where PHP's own type system isn't specific enough) to decide how to convert the input for each property.

```php
final class CreatePostData
{
    public function __construct(
        public string $title,
        public string $content,
        public array $tags = [],
    ) {
        //
    }
}
```

## Mapped data types

* Delimited lists (`1,3,7` or `hello-world,foo,bar`) convert to arrays or collections, including collections of a typed child (models, dates, enums, nested DTOs).
* Enum backed values convert to native PHP enums.
* Dates in string form (`2023-09-07 06:35:53`) convert to `Carbon`/`CarbonImmutable` instances.
* Eloquent models: typing a property as a model class queries that model by primary key (or `whereIn` for a collection/array of keys) when the instance isn't already passed through. Customising the lookup key, eager-loading relationships, and morph binding — all documented in the old v3 docs under "Models binding" — aren't wired up yet on v4; see [`CURRENT_ISSUES.md`](https://github.com/open-southeners/laravel-data-mapper/blob/v4-refactor/CURRENT_ISSUES.md) in the package repo before relying on `#[ResolveModel]`/`#[ModelWith]` for anything beyond a plain key lookup.
* An input that's already an instance of the target class (a `Model`, an enum, a `Carbon`, a hydrated DTO, …) is handed back as-is instead of being destructured and re-hydrated.
* The currently authenticated user (or any container binding) via contextual attributes — see below.

## Mapping collections

Arrays and Laravel collections go through the same item-by-item mapping. Given:

```json
{ "tags": "1,3,91" }
```

```php
final class CreatePostData
{
    public function __construct(
        public array $tags
    ) {
        //
    }
}
```

`$tags` becomes `['1', '3', '91']`. Add a docblock generic to type each item:

```php
final class CreatePostData
{
    /**
     * @param int[] $tags
     */
    public function __construct(
        public array $tags
    ) {
        //
    }
}
```

The same generic syntax works for typed properties and for `Collection`, including models — a comma-delimited list of IDs (or an array of IDs, or already-hydrated model instances) all resolve to a collection of that model:

```php
use Illuminate\Support\Collection;
use App\Models\Tag;

final class CreatePostData
{
    /**
     * @param Collection<int, Tag> $tags
     */
    public function __construct(
        public Collection $tags
    ) {
        //
    }
}
```

A plain `array`/`Collection` property with no docblock generic (nothing to resolve each item's type against) is kept as-is rather than mapped item by item — still wrapped into the declared collection type.

### Whether array input becomes an array or a `Collection`

With no docblock generic and no explicit `->through()` call at the mapping site, bare array input follows the `map_arrays_through` config option (`config/data-mapper.php`, default `'array'`); set it to `Illuminate\Support\Collection::class` to default to collections instead. An explicit `->through()` (see [Usage](usage.md)) always wins over this default.

### Nested DTOs

A DTO can be a property of another DTO — including collections of them:

```php
use Illuminate\Support\Collection;

final class CreateManyPostData
{
    /**
     * @param Collection<int, CreatePostData> $posts
     */
    public function __construct(
        public Collection $posts
    ) {
        //
    }
}
```

Each item in the array/collection input is mapped through `CreatePostData` individually.

## Default values

There's no `withDefaults()` hook or `WithDefaultValue` attribute anymore — a DTO is constructed with only the keys present in the input, so anything missing just falls back to the property's own PHP default:

```php
final class CreatePostData
{
    public function __construct(
        public string $title,
        public string|null $description = null,
        public array $tags = ['generic', 'post'],
    ) {
        //
    }
}
```

For the authenticated user specifically, use `#[Authenticated]` instead (see below) rather than a default value.

## Property name normalisation

Snake_case input keys (`user_id`, `is_published`) are normalised to their camelCase/plain property equivalent (`user`, `isPublished`) automatically — controlled by the `normalise_properties` config option (default `true`). Disable it globally in `config/data-mapper.php`, or force it on for one class regardless of the global config with `#[NormaliseProperties]`:

```php
use OpenSoutheners\LaravelDataMapper\Attributes\NormaliseProperties;

#[NormaliseProperties]
final class CreatePostData
{
    // ...
}
```

## Contextual attributes

Properties can be resolved straight from the container instead of from the input, taking precedence over any matching input key.

**Authenticated user:**

```php
use OpenSoutheners\LaravelDataMapper\Attributes\Authenticated;
use App\Models\User;

final class CreatePostData
{
    public function __construct(
        public string $title,
        #[Authenticated]
        public ?User $author = null,
    ) {
        //
    }
}
```

**Any other container binding**, via `#[Inject]`:

```php
use OpenSoutheners\LaravelDataMapper\Attributes\Inject;
use App\Services\Slugger;

final class CreatePostData
{
    public function __construct(
        public string $title,
        #[Inject(Slugger::class)]
        public Slugger $slugger,
    ) {
        //
    }
}
```

## Custom mapping logic

Implement `OpenSoutheners\LaravelDataMapper\Contracts\MappableObject` on your own class to take over how it's built from a mapping, instead of the default constructor-property mapping:

```php
use OpenSoutheners\LaravelDataMapper\Contracts\MappableObject;
use OpenSoutheners\LaravelDataMapper\MappingValue;

final class Money implements MappableObject
{
    public function __construct(public readonly int $cents, public readonly string $currency)
    {
        //
    }

    public function mappingFrom(MappingValue $mappingValue): mixed
    {
        [$amount, $currency] = explode(' ', $mappingValue->data);

        return new self((int) round($amount * 100), $currency);
    }
}
```

`map('12.50 GBP')->to(Money::class)` then calls `mappingFrom()` directly instead of going through the built-in mappers.

## Serialising DTOs for queued jobs

Queue-friendly serialisation is opt-in via the `SerializesMapping` trait — see [Usage in queued jobs](usage.md#usage-in-queued-jobs):

```php
use OpenSoutheners\LaravelDataMapper\Concerns\SerializesMapping;

final class SerializablePostData
{
    use SerializesMapping;

    public function __construct(
        public string $title,
        public ?Post $post,
        public Collection $reviewers,
    ) {
        //
    }
}
```

With the trait, a queue payload never embeds full Eloquent attribute data: models collapse to their primary key, collections/arrays of models to an array of keys, backed enums to their scalar value, `Carbon` to an ISO-8601 string, and nested DTOs using the same trait recurse into their own payload. Unserialising re-runs the collapsed payload back through `map()->to(static::class)`, so models are re-queried and dates/enums re-parsed rather than trusted as-is.
