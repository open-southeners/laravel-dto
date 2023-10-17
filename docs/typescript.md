---
description: Data transfer objects that converts to TypeScript types for your own convenience.
---

# TypeScript generator

Typing all the backend can be a tough task, but even synchronizing these types into your frontend layer if you are using a different technology stack (not Livewire).

Therefore we got you cover, you can use the following command to generate types under your resources/js folder (can be configurable):

```bash
php artisan dto:typescript
```

This command will take all DTO classes from your app/DataTransferObjects folder and convert them into TypeScript types.

## Customise exported type names

Let say you have a `FilmCreateData` DTO and you want to change the exported name from TypeScript generated types file, you just need to add the `AsType` PHP attribute:

```php
use OpenSoutheners\LaravelDto\Attributes\AsType;
​
#[AsType('FilmCreationForm')]
final class FilmCreateData extends DataTransferObject
{
    // ...
}
```