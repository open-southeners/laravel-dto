---
description: >-
  Data transfer objects are useful to pass data, they can be used everywhere but
  has some special uses in multiple places like controllers (including their
  route bindings) and queued jobs.
---

# Usage

## Usage as standalone

{% hint style="warning" %}
Remember that using the constructor like `new CreatePostData` is also a valid option but it will not map all the data.
{% endhint %}

You can use DTOs on every place you want as the following:

```php
$data = CreatePostData::fromArray([
    'title' => 'Hello world',
    'content' => 'hello world',
    'tags' => '1,3'
]);
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

This can be also be shorter by injecting directly the DTO like the following:

```php
// PostController.php

public function store(CreatePostData $data)
{
    $post = $this->repository->create($data);
    
    // Response here...
}
```

But then you might also think that your data must be validated, then you should read the following section.

### Validating request data

To be able to send a `FormRequest` that will also run validation inside the DTO you may need to create a `ValidatedDataTransferObject`.&#x20;

The best way to do so is by running the following command:

```bash
php artisan make:dto PostCreateData --request
```

You can also specify the `FormRequest` class path so it will be injected directly:

{% hint style="info" %}
This way will try reading validation rules array from the `FormRequest` then add them as properties to the DTO class with their types and, if nullable, adding default value as null.
{% endhint %}

```bash
php artisan make:dto PostCreateData --request="App\\Http\\Requests\\PostCreateFormRequest"
```

This way you will have something like the following:

```php
<?php

namespace App\DataTransferObjects;

use OpenSoutheners\LaravelDto\DataTransferObject;
use OpenSoutheners\LaravelDto\Contracts\ValidatedDataTransferObject;
use App\Http\Requests\PostCreateFormRequest;

final class PostCreateData extends DataTransferObject implements ValidatedDataTransferObject
{
    public function __construct(
        // You may have some properties here if your FormRequest rules contains anything...
    ) {
        //
    }
    
    /**
     * Get form request that this data transfer object is based from.
     */
    public static function request(): string
    {
        return PostCreateFormRequest::class;
    }
}
```

Now whenever you use this DTO on your controllers sending your `FormRequest` instance or [injecting it directly in your controller methods](usage.md#usage-in-controllers) will run validation on the data provided.

## Usage in queued jobs

The usage on queued jobs is automatically performed by the package itself, it will serialise and deserialise all the data from the DTO when the queued job enters to the queues processor.

Just adding an example to clarify its usage, having the following queued job:

```php
<?php
 
namespace App\Jobs;

use App\DataTransferObjects\PostCreateData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
 
class ProcessPostCreation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected PostCreateData $data)
    {
        //
    }
 
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->data->post; // This will get the post model instance
    }    
}
```

Then sending this job to the queue with the data transfer object already created using `fromArray` or `fromRequest` methods or via controller binding:

```php
// Somewhere in your application...
use App\DataTransferObjects\PostCreateData;
use App\Jobs\ProcessPostCreation;

dispatch(
    new ProcessPostCreation(
        PostCreateData::fromArray([
            'title' => 'Hello World',
            'content' => 'Lorem ipsum...',
            'tags' => '1,5,8',
        ])
    )
);
```
