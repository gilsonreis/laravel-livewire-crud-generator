<?php

namespace Gilsonreis\LaravelLivewireCrudGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateLivewireComponent extends Command
{
    protected $signature = 'make:livewire-component {name : Nome do componente Livewire no formato "pages.cadastros.user-report"}';
    protected $description = 'Gera um componente Livewire vazio com uma view padrão e adiciona uma rota.';

    public function handle()
    {
        $name = $this->argument('name');
        $namespacePath = $this->getNamespacePath($name);
        $viewPath = $this->getViewPath($name);
        $className = $this->getClassName($name);
        $readableName = $this->getReadableName($name);

        $this->generateComponent($namespacePath, $className, $readableName);
        $this->generateView($viewPath, $readableName);
        $this->addRoute($name);

        $this->info("Componente Livewire '$readableName' criado com sucesso!");
    }

    protected function getNamespace(string $name): string
    {
        $baseNamespace = 'App\\Livewire';

        if (Str::contains($name, '.')) {
            $subNamespace = Str::of($name)
                ->beforeLast('.')
                ->explode('.')
                ->map(fn($segment) => Str::studly(Str::replace('-', ' ', $segment)))
                ->map(fn($segment) => Str::replace(' ', '', $segment))
                ->join('\\');
            return $baseNamespace . '\\' . $subNamespace;
        }

        return $baseNamespace;
    }

    protected function getNamespacePath(string $name): string
    {
        $basePath = app_path('Livewire');
        return $basePath . '/' . Str::of($name)
                ->explode('.')
                ->map(fn($segment) => Str::studly(Str::replace('-', ' ', $segment)))
                ->map(fn($segment) => Str::replace(' ', '', $segment))
                ->join('/');
    }

    protected function getViewPath(string $name): string
    {
        $basePath = resource_path('views/livewire');
        return $basePath . '/' . Str::of($name)->replace('.', '/')->kebab() . '.blade.php';
    }

    protected function getClassName(string $name): string
    {
        return Str::of($name)->afterLast('.')->studly();
    }

    protected function generateComponent(string $namespacePath, string $className, string $readableName): void
    {
        if (!is_dir(dirname($namespacePath))) {
            mkdir(dirname($namespacePath), 0755, true);
        }

        $defaultStubPath = __DIR__ . '/../stubs/pages/single-component.stub';
        $customStubPath = base_path('stubs/livewire/pages/single-component.stub');
        $stubPath = file_exists($customStubPath) ? $customStubPath : $defaultStubPath;

        $stub = file_get_contents($stubPath);

        $viewName = Str::contains($this->argument('name'), '.')
            ? 'livewire.' . $this->argument('name')
            : 'livewire.' . Str::kebab($this->argument('name'));

        $content = str_replace(
            ['{{ namespace }}', '{{ className }}', '{{ readableName }}', '{{ viewName }}'],
            [$this->getNamespace($this->argument('name')), $className, $readableName, $viewName],
            $stub
        );

        file_put_contents($namespacePath . '.php', $content);
    }

    protected function generateView(string $viewPath, string $readableName): void
    {
        if (!is_dir(dirname($viewPath))) {
            mkdir(dirname($viewPath), 0755, true);
        }

        $defaultStubPath = __DIR__ . '/../stubs/views/single-component.stub';
        $customStubPath = base_path('stubs/livewire/views/single-component.stub');
        $stubPath = file_exists($customStubPath) ? $customStubPath : $defaultStubPath;

        $stub = file_get_contents($stubPath);
        $viewContent = str_replace('{{ title }}', $readableName, $stub);

        file_put_contents($viewPath, $viewContent);
    }

    protected function addRoute(string $name): void
    {
        $routePath = base_path('routes/web.php');
        $hifenizedName = Str::of($name)->replace('.', '/')->kebab();
        $componentClass = $this->getNamespace($name) . '\\' . $this->getClassName($name);

        $route = <<<PHP
Route::get('$hifenizedName', \\$componentClass::class)->name('$hifenizedName');
PHP;

        $webRoutes = file_get_contents($routePath);

        if (str_contains($webRoutes, "Route::middleware(['auth:sanctum'])")) {
            $webRoutes = preg_replace(
                "/Route::middleware\(\['auth:sanctum'\]\)->group\(function\s*\(\)\s*{/",
                "Route::middleware(['auth:sanctum'])->group(function () {\n    $route",
                $webRoutes
            );
        } else {
            $webRoutes .= "\n\n$route;";
        }

        file_put_contents($routePath, $webRoutes);
        $this->info("Rota adicionada ao arquivo routes/web.php: $route");
    }

    protected function getReadableName(string $name): string
    {
        return Str::of($name)
            ->afterLast('.') // Pega apenas o último segmento do nome
            ->snake()        // Converte para snake_case
            ->replace('_', ' ') // Substitui underscores por espaços
            ->replace('-', ' ')
            ->title();       // Converte para "Title Case"
    }
}