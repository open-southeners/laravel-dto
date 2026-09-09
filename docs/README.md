---
description: Installing Laravel Data Mapper in your application.
---

# Getting started

{% hint style="info" %}
This package was renamed from `laravel-dto` to `laravel-data-mapper` and rewritten in v4: there's no more base `DataTransferObject` class to extend, no `make:dto` command, and the namespace changed to `OpenSoutheners\LaravelDataMapper`. If you're coming from v3, read this page and [Creating DTOs](creating-dtos.md) before touching your existing DTOs.
{% endhint %}

Grab the dependency with Composer

```bash
composer require open-southeners/laravel-data-mapper
```

## What this package does

Laravel Data Mapper maps arbitrary input — arrays, JSON, HTTP requests, scalars — into typed objects: DTOs, plain PHP objects, Eloquent models, enums, `Carbon` dates and collections of any of those. Everything goes through one function:

```php
use function OpenSoutheners\LaravelDataMapper\map;

map($input)->to(Target::class);
```

### Create a data transfer object

There's no artisan command and no base class to extend — a DTO is just a plain class with typed constructor properties:

```php
<?php

namespace App\DataTransferObjects;

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

Mapping data onto it works the same way as any other target:

```php
$data = map([
    'title' => 'Hello world',
    'content' => 'hello world',
    'tags' => '1,3',
])->to(CreatePostData::class);
```

See [Creating DTOs](creating-dtos.md) for how property types drive the mapping, and [Usage](usage.md) for controllers, validation and queued jobs.
