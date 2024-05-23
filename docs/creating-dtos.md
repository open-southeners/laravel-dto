---
description: >-
  Creating data transfer object classes and adding properties to them in a
  proper way so they can be mapped.
---

# Creating DTOs

## Mapped data types

Apart from being capable of passing native typed PHP properties it can also cast to a tiny range of content that the framework as well as PHP provides:

* Delimited lists (`1,3,7` or `hello-world,foo,bar`) can be converted to arrays or collections with typed child properties like models, dates, etc.
* Models binding from [**simple binding**](creating-dtos.md#models-binding) to [**morph binding**](creating-dtos.md#morph-binding) using custom attributes for the binding and loading relationships.
* Enums backed values can be converted to native PHP enums.
* Dates in string (`2023-09-07 06:35:53`) can be converted to `Carbon` instances.
* Authenticated user using `WithDefaultValue` PHP attribute with the Laravel's interface `Illuminate\Contracts\Auth\Authenticatable`.

### Models binding

When typing a variable with an Eloquent model class it will be mapped to this model querying the database when the instance is not passed through to the DTO.

```php
use OpenSoutheners\LaravelDto\DataTransferObject;
use App\Models\Tag;

​final class CreatePostData extends DataTransferObject
{
    public function __construct(
        public string $title,
        public string $content,
        public Tag $tag,
    ) {
      //
    }
}
```

So sending a `tag` to this to be able to map (do a model binding) you should send the following:

```php
CreatePostData::fromArray(['tag' => 1]);
```

This will query the tag with the id = 1.

#### Customise binding attribute

Now in case we have a `Post` entity with an unique `slug` column that we can use to simplify its binding:

```php
use OpenSoutheners\LaravelDto\DataTransferObject;
use OpenSoutheners\LaravelDto\Attributes\BindModel;
use App\Models\Post;
use App\Models\Tag;

​final class UpdatePostData extends DataTransferObject
{
    public function __construct(
        #[BindModel(using: 'slug')]
        public Post $post,
        public ?string $title = null,
        public ?string $content = null,
        public ?Tag $tag = null,
    ) {
      //
    }
}
```

This way you can send a post by using the slug:

```php
UpdatePostData::fromArray(['post' => 'hello-world']);
```

#### Binding with relationships loaded

Ever dreamed about loading relationships from route bindings [**using DTOs directly in your controllers**](usage.md#usage-in-controllers)? Now is the time to show you **an unique feature from this package**:

```php
use OpenSoutheners\LaravelDto\DataTransferObject;
use OpenSoutheners\LaravelDto\Attributes\BindModel;
use App\Models\Post;
use App\Models\Tag;

​final class UpdatePostData extends DataTransferObject
{
    public function __construct(
        #[BindModel(using: 'slug', with: ['author', 'author.roles'])]
        public Post $post,
        public ?string $title = null,
        public ?string $content = null,
        public ?Tag $tag = null,
    ) {
      //
    }
}
```

### Morph binding

In an example case when you have a relationship on your tags called _taggable_ (a morph relationship) which can attach posts and films models, this is the way to deal with these on DTOs:

```php
use OpenSoutheners\LaravelDto\DataTransferObject;
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

Now we can send something like this from the frontend or API:

```json
{
    "name": "Traveling",
    "taggable_id": "1, 2, 3",
    "taggable_type": "post"
}
```

{% hint style="info" %}
The following feature is available since v3.5 so make sure you use the latest version of this package.
{% endhint %}

In case you want to mix taggable types you can do so **changing the property type of `taggableType` from string to array** like so:

<pre class="language-php"><code class="lang-php">use OpenSoutheners\LaravelDto\DataTransferObject;
use App\Models\Post;
use App\Models\Film;

final class CreateTagData extends DataTransferObject
{
    public function __construct(
        public string $name,
        public Post|Film $taggable,
        public <a data-footnote-ref href="#user-content-fn-1">array</a> $taggableType,
    ) {
        //
    }
}
</code></pre>

Having the one above we can do plenty combinations from the frontend or API:&#x20;

```json
{
    "taggable_id": "1, 2, 3",
    "taggable_type": "post, film, post"
}
```

{% hint style="warning" %}
In case we send more IDs than types the last type will be taken for all the IDs that are left alone.
{% endhint %}

#### Customise binding attribute

In an example when you have slugs only in posts but not films entities you can add the following to determine which morph type will have its binding customised:

```php
use OpenSoutheners\LaravelDto\DataTransferObject;
use OpenSoutheners\LaravelDto\Attributes\BindModel;
use App\Models\Post;
use App\Models\Film;

final class CreateTagData extends DataTransferObject
{
    public function __construct(
        public string $name,
        #[BindModel(using: [Post::class => 'slug'])]
        public Post|Film $taggable,
        public string $taggableType,
    ) {
        //
    }
}
```

#### Binding with relationships loaded

You may need to add model classes as array keys so DTO mapping will know **which relationship to load on each morph type**:

```php
use OpenSoutheners\LaravelDto\DataTransferObject;
use OpenSoutheners\LaravelDto\Attributes\BindModel;
use App\Models\Post;
use App\Models\Film;

final class CreateTagData extends DataTransferObject
{
    public function __construct(
        public string $name,
        #[BindModel(with: [
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
use OpenSoutheners\LaravelDto\DataTransferObject;

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
use OpenSoutheners\LaravelDto\DataTransferObject;

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
use OpenSoutheners\LaravelDto\DataTransferObject;
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
use OpenSoutheners\LaravelDto\DataTransferObject;
use App\Enums\PostStatus;

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
    // Filled will check whether description is on the request or property not null (depending on the context)
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
use OpenSoutheners\LaravelDto\DataTransferObject;
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

Also this attribute has special usages like the ones following below.

#### Default value as authenticated user

{% hint style="info" %}
This was created after using `Authenticatable` interface as a property type because this interface is not completed with all the types that a `User` model has (or any model used in the authentication of your Laravel application).
{% endhint %}

If the right contract is sent it will grab the authenticated user:

```php
use OpenSoutheners\LaravelDto\DataTransferObject;
use OpenSoutheners\LaravelDto\Attributes\WithDefaultValue;
use Illuminate\Contracts\Auth\Authenticatable;

final class CreatePostData extends DataTransferObject
{
  public function __construct(
    public string $title,
    public PostStatus $status,
    #[WithDefaultValue(Authenticatable::class)]
    public User $author,
    public string|null $description = null,
    public array $tags = []
  ) {
    //
  }
}
```

[^1]: This must be changed to be able to receive multiple types and map them to an array
