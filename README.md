Laravel DTO [![required php version](https://img.shields.io/packagist/php-v/open-southeners/laravel-dto)](https://www.php.net/supported-versions.php) [![run-tests](https://github.com/open-southeners/laravel-dto/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/open-southeners/laravel-dto/actions/workflows/tests.yml) [![codecov](https://codecov.io/gh/open-southeners/laravel-dto/branch/main/graph/badge.svg?token=LjNbU4Sp2Z)](https://codecov.io/gh/open-southeners/laravel-dto) [![Edit on VSCode online](https://img.shields.io/badge/vscode-edit%20online-blue?logo=visualstudiocode)](https://vscode.dev/github/open-southeners/laravel-dto)
===

Integrate data transfer objects into Laravel, the easiest way

## Getting started

```
composer require open-southeners/laravel-dto
```

## Usage

### Create new data transfer object

Run the following command on your Laravel project:

```sh
php artisan make:dto CreatePostData
```

Now you should have a file with a path like `app/DataTransferObjects/CreatePostData.php` which looks like this:

```php
<?php

namespace App\DataTransferObjects;

use OpenSoutheners\LaravelDto\DataTransferObject;

class CreatePostData extends DataTransferObject
{
    public function __construct(
        // 
    ) {
        // 
    }

    /**
     * Add default data to data transfer object.
     */
    public function withDefaults(): void
    {
        if (empty($this->tags)) {
            $this->tags = ['generic', 'post'];
        }
    }
}
```

### Mapping data through

First lets edit the DTO we just generated on the step above:

```php
class CreatePostData extends DataTransferObject
{
    public function __construct(
        public string $title,
        public PostStatus $status,
        public array $tags,
    ) {
        // 
    }
}
```

Now at the controller level you may do something like the following:

```php
// PostController.php

public function store(CreatePostFormRequest $request)
{
    $post = $this->repository->create(
        CreatePostData::fromArray($request->validated())
    );

    // Response here...
}
```

### Mapping with defaults

In case you want some default data mapped whenever you use `fromArray` you can use `withDefaults` like:

```php
class CreatePostData extends DataTransferObject
{
    public function __construct(
        public string $title,
        public PostStatus $status,
        public string|null $description = null,
        public array $tags = [],
    ) {
        // 
    }

    /**
     * Add default data to data transfer object.
     */
    public function withDefaults(): void
    {
        // Filled will check wether description is on the request or property not null (depending on the context)
        if (! $this->filled('description')) {
            $this->description = 'Example of a description...';
        }

        if (empty($this->tags)) {
            $this->tags = ['generic', 'post'];
        }
    }
}
```
