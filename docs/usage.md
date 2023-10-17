---
description: Data transfer objects are useful to pass data, this package also brings some binding power to these properties.
---

# Usage

## Types of data

You have as many types as Laravel provides out of the box, excluding native ones from PHP:

- Collections from delimited lists (which can have nested types that also binds to these collections).
- Arrays (called collections as well) from delimited lists, these also can have nested typed items to bind models, etc.
- Models binding from **[simple binding](#mapping-models-binding)** to **[morph binding](#mapping-morph-multiple-model-type-binding)** using custom attributes for the binding and loading relationships.
- Enums, backed (they need to have values) native enums from PHP.

## Mapping models (binding)

::: tip
This model binding feature is also fully compatible with mapping collections. Check below (next section) to more information about this.
:::

When typing a variable with an Eloquent model class it will be mapped to this model querying the database when the instance is not passed through (using fromArray DTO method).

### Mapping models with relationships loaded

Sometimes is hard to have models loaded with some relationships when they are bound from requests bodies or routes parameters. This isn't a problem with DTOs.

```php
use App\Models\Tag;
use OpenSoutheners\LaravelDto\Attributes\BindModel;
​final class CreatePostData extends DataTransferObject
{
    public function __construct(
        public string $title,
        public string $content,
        #[BindModel(with: ['author', 'author.role'])]
        public Tag $tag,
    ) {
      //
    }
}
```

### Mapping morph (multiple model type binding)

In an example case when you have a relationship on your tags called taggable (a morph relationship) which can attach posts and films models, this is the way to deal with these on DTOs:

```php
use App\Models\Post;
use App\Models\Film;

final class CreateTagData extends DataTransferObject
{
    public function __construct(
        public string $name,
        public Post|Film $taggable,
        public string $taggableType,
    ) {
        //
    }
}
```

#### Mapping morph models with relationships

You can still use `BindModel` attribute on morph (multiple model types binding) with a little difference, you need to add keys which are the model classes so DTO mapping will know which relationship or attribute to use each time:

```php
use App\Models\Post;
use App\Models\Film;
use OpenSoutheners\LaravelDto\Attributes\BindModel;

final class CreateTagData extends DataTransferObject
{
    public function __construct(
        public string $name,
        #[BindModel(using: [Post::class => 'slug'], with: [
            Post::class => ['author', 'author.role'],
            Film::class => 'reviews',
        ])]
        public Post|Film $taggable,
        public string $taggableType,
    ) {
        //
    }
}
```

This way we are binding a `taggable` entity that when is a post will be using slug on this property, while films will use their defaults (`id`).

When loading a post will be getting its `author` and author's `role`, if otherwise is a film it will only load its reviews.

### Mapping collections

We determine as collections arrays and Laravel's collections because of some particular mapping process we do to them, lets imagine we send this to our backend:

```json
{
    "tags": "1,3,91"
}
```

Using the following DTO:

```php
final class CreatePostData extends DataTransferObject
{
    public function __construct(
        public array $tags
    ) {
        //
    }
}
```

We should get a array from this delimited list, now let's say we wanted to have integers, we could just use a docblock to help us.

```php
final class CreatePostData extends DataTransferObject
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

Now we've an array of integers and so our IDE can also help us when using this property. **But we're not limited to only native types, we can also use models! Sending a comma-delimited list of IDs and typing this properly.**

Same will go for **Laravel collections**, just typing it properly like so:

```php
use Illuminate\Support\Collection;

class CreatePostData extends DataTransferObject
{
  /**
   * @param \Illuminate\Support\Collection<\App\Models\Tag> $tags
   */
  public function __construct(
    public Collection $tags
  ) {
    //
  }
}
```

The example at the top will bind tag IDs or an array of IDs or tags model instances to a collection of tags instances.

### Mapping with default values

In case you want some default data mapped whenever you use `fromArray` you can use `withDefaults` like:

```php
final class CreatePostData extends DataTransferObject
{
  public function __construct(
    public string $title,
    public PostStatus $status,
    public string|null $description = null,
    public array $tags = []
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

#### Default value using attribute

You can use PHP attributes instead, which simplifies it even more as you **only need to send the raw value without it being mapped**:

```php
use OpenSoutheners\LaravelDto\Attributes\WithDefaultValue;

final class CreatePostData extends DataTransferObject
{
  public function __construct(
    public string $title,
    public PostStatus $status,
    #[WithDefaultValue('Example of a description...')]
    public string|null $description = null,
    #[WithDefaultValue(['generic', 'post'])]
    public array $tags = []
  ) {
    //
  }
}
```

## Usage in controllers

Now at the controller level you may do something like the following:

```php
// PostController.php

public function store(CreatePostFormRequest $request)
{
  $post = $this->repository->create(
    CreatePostData::fromRequest($request)
  );
  
  // Response here...
}
```

Or in case you're outside of a request context (HTTP call) you can use `fromArray`:

```php
// CreatePostCommand.php

$data = CreatePostData::fromArray([
  'title' => 'Hello world',
  'status' => PostStatus::Published->value,
  'tags' => 'hello,world'
]);

$data->title; // Hello world
```

### Controller binding resolution

::: tip
This way the data transfer object can also get route binding stuff like models. Check this whole section for further understanding on this feature.
:::

You can also save code by directly typing an parameter on your controller as a DTO class.

To do so, you might need to create a DTO with the `ValidatedDataTransferObject` interface, for that you have the command:

```bash
php artisan make:dto PostCreateData --request
```

::: tip
It can also convert validation rules from a `FormRequest` file to a DTO by sending the class path to the `--request` option.
:::

Then you need to fill the `request` method with the class string (full qualified class path):

```php
<?php

namespace App\DataTransferObjects;

use App\Http\Requests\PostCreateFormRequest;
use App\Models\Country;
use Illuminate\Contracts\Auth\Authenticatable;
use OpenSoutheners\LaravelDto\Contracts\ValidatedDataTransferObject;
use OpenSoutheners\LaravelDto\DataTransferObject;

final class PostCreateData extends DataTransferObject implements ValidatedDataTransferObject
{
  public function __construct(
    public string $title,
    public string $content,
    public Country $country,
    public ?Authenticatable $author = null
  ) {
    //
  }
  
  public static function request(): string
  {
    // This needs to be filled with the form request class
    return PostCreateFormRequest::class;
  }
}
```

Then you can use this same DTO in your controller, approaching a particular feature which is injecting the route binding of models directly to the data transfer object (check before and after on the controller logic to see the difference).
