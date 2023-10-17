---
description: Installing Laravel DTO in your application.
---

# Getting started

Grab the dependency with Composer

```bash
composer require open-southeners/laravel-dto
```

### Create new data transfer object

To create a new DTO class run the following command on your project:

```bash
php artisan make:dto CreatePostDataNow
```

You should have a file with a path like `app/DataTransferObjects/CreatePostData.php` which looks like this:

```php
<?php
​
namespace App\DataTransferObjects;
​
use OpenSoutheners\LaravelDto\DataTransferObject;
​
final class CreatePostData extends DataTransferObject
{
    public function __construct(
        // 
    ) {
        // 
    }
}
```