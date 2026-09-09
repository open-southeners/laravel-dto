---
description: Data transfer objects that convert to TypeScript types for your own convenience.
---

# TypeScript generator

{% hint style="warning" %}
The `dto:typescript` artisan command is gone in v4 — there's no command that scans `app/DataTransferObjects` for you anymore. Generation now goes through the same `map()` pipeline as everything else, one class at a time, so you decide where the output goes.
{% endhint %}

`TypeScript` is itself a mappable target: mapping a class name onto it walks that class's constructor properties (or, for an Eloquent model, its database columns; for a backed enum, its cases) and builds the equivalent TypeScript declaration.

```php
use OpenSoutheners\LaravelDataMapper\Support\TypeScript;
use function OpenSoutheners\LaravelDataMapper\map;

$typeScript = (string) map(CreatePostData::class)->to(TypeScript::class);
```

`$typeScript` is a string like:

```typescript
export type CreatePostData = {
  title: string,
  content: string,
  tags: Array<unknown>,
};
```

Nested DTOs, enums and models referenced by a property are expanded into their own `export` alongside it. Write the result to a file yourself, e.g. under `resources/js`:

```php
file_put_contents(
    resource_path('js/types/post.ts'),
    (string) map(CreatePostData::class)->to(TypeScript::class)
);
```

## Customise exported type names

Add the `AsType` attribute to change the exported name for a class:

```php
use OpenSoutheners\LaravelDataMapper\Attributes\AsType;

#[AsType('FilmCreationForm')]
final class FilmCreateData
{
    // ...
}
```
