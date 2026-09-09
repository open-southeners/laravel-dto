---
description: >-
  Data transfer objects are useful to pass data, they can be used everywhere
  but have some special uses in multiple places like controllers (including
  their route bindings) and queued jobs.
---

# Usage

## Usage as standalone

You can map onto a DTO anywhere with `map()`:

```php
use function OpenSoutheners\LaravelDataMapper\map;

$data = map([
    'title' => 'Hello world',
    'content' => 'hello world',
    'tags' => '1,3',
])->to(CreatePostData::class);
```

Mapping a bare array or `Collection` input into a collection target (rather than inferring it from `map_arrays_through`, see [Creating DTOs](creating-dtos.md#whether-array-input-becomes-an-array-or-a-collection)) uses `->through()`:

```php
use Illuminate\Support\Collection;

map($request->input('tags'))->through(Collection::class)->to(Tag::class);
```

## Usage in controllers

Implement the `RouteTransferableObject` marker interface on a DTO to auto-map it from the current request — including route parameters — when it's type-hinted in a controller method:

```php
use OpenSoutheners\LaravelDataMapper\Contracts\RouteTransferableObject;

final class CreatePostData implements RouteTransferableObject
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

```php
// PostController.php

public function store(CreatePostData $data)
{
    $post = $this->repository->create($data);

    // Response here...
}
```

You can still build one manually from a request or form request when you don't want the automatic controller binding:

```php
public function store(CreatePostFormRequest $request)
{
    $post = $this->repository->create(
        map($request)->to(CreatePostData::class)
    );
}
```

### Validating request data

Add `#[Validate(SomeFormRequest::class)]` to the DTO class to have the controller binding resolve (and validate through) that `FormRequest` instead of the plain `Request`:

```php
use OpenSoutheners\LaravelDataMapper\Attributes\Validate;
use OpenSoutheners\LaravelDataMapper\Contracts\RouteTransferableObject;
use App\Http\Requests\PostCreateFormRequest;

#[Validate(PostCreateFormRequest::class)]
final class PostCreateData implements RouteTransferableObject
{
    public function __construct(
        public string $title,
        public string $content,
    ) {
        //
    }
}
```

Laravel validates the `FormRequest` as usual when the container resolves it, before its data ever reaches the mapper — so an invalid request never reaches your controller method.

## Usage in queued jobs

Serialisation for the queue isn't automatic in v4 — add `SerializesMapping` (see [Creating DTOs](creating-dtos.md#serialising-dtos-for-queued-jobs)) to any DTO you plan to pass into a queued job:

```php
use OpenSoutheners\LaravelDataMapper\Concerns\SerializesMapping;

final class PostCreateData
{
    use SerializesMapping;

    public function __construct(
        public string $title,
        public string $content,
        public ?Post $post,
    ) {
        //
    }
}
```

```php
<?php

namespace App\Jobs;

use App\DataTransferObjects\PostCreateData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class ProcessPostCreation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(protected PostCreateData $data)
    {
        //
    }

    public function handle(): void
    {
        $this->data->post; // Re-queried by primary key when the job runs
    }
}
```

```php
dispatch(new ProcessPostCreation(
    map($request)->to(PostCreateData::class)
));
```

A DTO without `SerializesMapping` still queues fine as long as PHP's own serialisation can handle its properties — the trait exists specifically to avoid embedding full Eloquent attribute data (or re-serialising anything non-trivial) in the queue payload.

## Registering custom mappers

Mapping is resolved by a `MapperRegistry` container singleton: the first registered mapper (highest priority first, ties by registration order) whose `supports()` check passes handles the value. Register your own to override or extend built-in behaviour without touching package code:

```php
use OpenSoutheners\LaravelDataMapper\MapperRegistry;
use OpenSoutheners\LaravelDataMapper\Mappers\DataMapper;
use OpenSoutheners\LaravelDataMapper\MappingValue;

class MoneyStringMapper extends DataMapper
{
    public function supports(MappingValue $mappingValue): bool
    {
        return $mappingValue->objectClass === Money::class && is_string($mappingValue->data);
    }

    public function resolve(MappingValue $mappingValue): mixed
    {
        [$amount, $currency] = explode(' ', $mappingValue->data);

        return new Money((int) round($amount * 100), $currency);
    }
}
```

```php
app(MapperRegistry::class)->register(MoneyStringMapper::class, priority: 100);
```

All built-in mappers are open to extension (none are `final`), so a subclass registered at a higher priority can override or extend one instead of reimplementing it from scratch. If your class implements `MappableObject` directly (see [Creating DTOs](creating-dtos.md#custom-mapping-logic)) you don't need a separate registered mapper at all — the built-in `MappableObjectMapper` (priority 100) picks it up automatically.

### Events and exceptions

`OpenSoutheners\LaravelDataMapper\Events\MappingResolved` dispatches on every successful mapping resolution, carrying the winning mapper class and the `MappingValue` — useful for logging or debugging which mapper handled a given value. It doesn't fire for instance passthrough, since no mapper actually ran.

`OpenSoutheners\LaravelDataMapper\Exceptions\NoMapperFoundException` is thrown when nothing in the registry supports a given input/target combination — the message includes the input type, the target class, and, for a nested property, its dot-notation path (e.g. `tags.2`).
