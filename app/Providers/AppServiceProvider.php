<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // API responses already use a flat shape (no wrapping in `data`).
        // Auth endpoints embed UserResource inside response()->json([...])
        // and never get wrapped; doing the same globally keeps every
        // resource response consistent regardless of how it's returned.
        JsonResource::withoutWrapping();
    }
}
