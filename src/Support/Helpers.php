<?php

namespace Gilsonreis\LaravelLivewireCrudGenerator\Support;

use Illuminate\Support\Facades\File;

final class Helpers
{
    public static function isSanctumInstalled(): bool
    {
        return File::exists(base_path('vendor/laravel/sanctum'));
    }
}
