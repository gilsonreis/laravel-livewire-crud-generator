<?php

namespace Gilsonreis\LaravelLivewireCrudGenerator\Providers;

use Gilsonreis\LaravelLivewireCrudGenerator\Commands\GenerateCrudMenuChoices;
use Gilsonreis\LaravelLivewireCrudGenerator\Commands\GenerateCrudModel;
use Gilsonreis\LaravelLivewireCrudGenerator\Commands\GenerateCrudRbac;
use Gilsonreis\LaravelLivewireCrudGenerator\Commands\GenerateCrudRepository;
use Gilsonreis\LaravelLivewireCrudGenerator\Commands\GenerateCrudSanctumLogin;
use Gilsonreis\LaravelLivewireCrudGenerator\Commands\GenerateCrudUseCase;
use Gilsonreis\LaravelLivewireCrudGenerator\Commands\GenerateFormRequest;
use Gilsonreis\LaravelLivewireCrudGenerator\Commands\GenerateCrudLivewire;
use Gilsonreis\LaravelLivewireCrudGenerator\Commands\GenerateLivewireComponent;
use Illuminate\Support\ServiceProvider;

class CrudLivewireGeneratorServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->commands([
            GenerateCrudModel::class,
            GenerateCrudRepository::class,
            GenerateCrudUseCase::class,
            GenerateFormRequest::class,
            GenerateCrudMenuChoices::class,
            GenerateCrudSanctumLogin::class,
            GenerateCrudRbac::class,
            GenerateCrudLivewire::class,
            GenerateLivewireComponent::class
        ]);
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../stubs' => base_path('stubs/livewire'),
        ], 'crud-livewire-stubs');
    }
}