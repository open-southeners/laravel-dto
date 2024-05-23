<?php

namespace Workbench\App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Models;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'post' => Models\Post::class,
            'tag' => Models\Tag::class,
            'film' => Models\Film::class,
            'user' => Models\User::class,
        ]);
    }
}
