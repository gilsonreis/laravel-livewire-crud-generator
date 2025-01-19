<?php

namespace Gilsonreis\LaravelLivewireCrudGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GenerateCrudLivewire extends Command
{
    protected $signature = 'make:crud-livewire
                            {--model= : Nome do Model para gerar o CRUD completo}';
    protected $description = 'Gera componentes Livewire com base no Model ou um componente em branco.';

    public function handle()
    {
        $model = $this->option('model');

        if ($model) {
            $this->info("Gerando CRUD completo para o Model: $model");
            $this->generateCrudFromModel($model);
        }
    }

    protected function generateCrudFromModel(string $model)
    {
        $this->generateIndexPage($model);
        $this->generateIndexViews($model);
        
        $this->addRoutes($model);
        $this->info("CRUD para o Model $model gerado com sucesso!");
    }

    protected function generateIndexPage(string $model)
    {
        $modelClass = "App\\Models\\$model";
        if (!class_exists($modelClass)) {
            $this->error("O Model $model não existe.");
            return;
        }

        $stringField = $this->getFirstStringField($modelClass);
        if (!$stringField) {
            $this->error("Nenhum campo do tipo string foi encontrado no Model $model.");
            return;
        }

        // Substituições
        $placeholders = [
            '{{ Model }}' => $model,
            '{{ ModelPlural }}' => Str::plural($model),
            '{{ modelSingular }}' => Str::camel($model), // Conversão para camelCase
            '{{ modelPlural }}' => Str::camel(Str::plural($model)),
            '{{ ModelReadable }}' => Str::title(str_replace('_', ' ', $model)),
            '{{ ModelPluralReadable }}' => Str::plural(Str::title(str_replace('_', ' ', $model))),
            '{{ stringField }}' => $stringField,
        ];

        $stubPath = __DIR__ . '/../stubs/pages/index-page.stub';
        $customStubPath = base_path('stubs/livewire/pages/index-page.stub');
        $stub = file_get_contents(file_exists($customStubPath) ? $customStubPath : $stubPath);

        // Substituir os placeholders no stub
        $content = str_replace(array_keys($placeholders), array_values($placeholders), $stub);

        // Diretório de destino
        $directory = app_path('Livewire/Pages/Cadastros/' . Str::plural($model));
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filePath = "$directory/IndexPage.php";
        file_put_contents($filePath, $content);

        $this->info("Arquivo gerado: $filePath");
    }

    protected function generateIndexViews(string $model): void
    {
        $stubPath = __DIR__ . '/../stubs/views/index-page.stub';
        $customStubPath = base_path('stubs/livewire/views/index-page.stub');
        $stub = file_exists($customStubPath) ? file_get_contents($customStubPath) : file_get_contents($stubPath);

        $modelClass = "App\\Models\\$model";

        if (!class_exists($modelClass)) {
            $this->error("O Model $model não existe.");
            return;
        }

        // Obter informações do Model
        $fields = (new $modelClass)->getFillable();
        $relations = $this->getRelations($modelClass);
        $firstTextField = $this->getFirstStringField($modelClass);

        // Caminho de destino da View
        $namespacePath = resource_path('views/livewire/pages/cadastros/' . Str::kebab(Str::pluralStudly($model)));
        $viewPath = "$namespacePath/index.blade.php";

        if (!is_dir($namespacePath)) {
            mkdir($namespacePath, 0755, true);
        }

        // Gerar cabeçalhos
        $headers = collect($fields)
            ->merge(collect($relations)->keys())
            ->map(fn($field) => '                                    <th>' . Str::headline($field) . '</th>')
            ->join("\n");

        // Gerar linhas
        $rows = collect($fields)
            ->map(fn($field) => '                                    <td>{{ $' . Str::camel($model) . '->' . $field . ' }}</td>')
            ->merge(collect($relations)->map(function ($relation) use ($model) {
                $rel = explode('\\', $relation);
                $relationClass = end($rel);
                $relationField = $this->getFirstStringField(Str::studly($relation));
                return $relationField
                    ? '                                    <td>{{ $'. Str::camel($model) .'->' . Str::camel($relationClass) . '->' . $relationField . ' ?? \'-\' }}</td>'
                    : '                                    <td>-</td>';
            }))
            ->join("\n");

        // Substituir placeholders no stub
        $content = str_replace(
            [
                '{{ modelPlural }}',
                '{{ modelSingular }}',
                '{{ upModelSingular }}',
                '{{ firstTextField }}',
                '{{ ModelReadable }}',
                '{{ ModelPluralReadable }}',
                '{{ headers }}',
                '{{ rows }}'
            ],
            [
                Str::camel(Str::pluralStudly($model)),  // modelPlural
                Str::camel($model),                    // modelSingular
                Str::studly($model),                   // upModelSingular
                $firstTextField,                       // firstTextField
                Str::headline($model),                 // ModelReadable
                Str::headline(Str::pluralStudly($model)), // ModelPluralReadable
                $headers,                              // headers
                $rows                                  // rows
            ],
            $stub
        );

        file_put_contents($viewPath, $content);
        $this->info("View de listagem gerada em: $viewPath");
    }

    protected function addRoutes(string $model): void
    {
        $routePath = base_path('routes/web.php');
        $modelPlural = Str::plural(Str::camel($model));
        $modelPluralStudly = Str::pluralStudly($model);
        $modelPluralKebab = Str::kebab($modelPlural);
        $modelNamespace = "App\\Livewire\\Pages\\Cadastros\\$modelPluralStudly";

        $routes = <<<PHP
    Route::prefix('$modelPluralKebab')->name('$modelPlural.')->group(function () {
        Route::get('/', $modelNamespace\\IndexPage::class)->name('index');
        //Route::get('/cadastro', $modelNamespace\\CreatePage::class)->name('create');
        //Route::get('/{id}/editar', $modelNamespace\\EditPage::class)->name('edit');
    });
PHP;

        $webRoutes = file_get_contents($routePath);

        if (!str_contains($webRoutes, "use $modelNamespace;")) {
            $webRoutes = preg_replace(
                '/^<\?php\s*/',
                "<?php\n\nuse $modelNamespace;\n",
                $webRoutes
            );
        }

        if (str_contains($webRoutes, "Route::middleware(['auth:sanctum'])")) {
            if (!str_contains($webRoutes, "Route::prefix('cadastros')->name('cadastros.')")) {
                $webRoutes = preg_replace(
                    "/Route::middleware\(\['auth:sanctum'\]\)->group\(function\s*\(\)\s*{/",
                    "Route::middleware(['auth:sanctum'])->group(function () {\n    Route::prefix('cadastros')->name('cadastros.')->group(function () {\n    });",
                    $webRoutes
                );
            }

            $webRoutes = preg_replace(
                "/Route::prefix\('cadastros'\)->name\('cadastros.'\)->group\(function\s*\(\)\s*{/",
                "Route::prefix('cadastros')->name('cadastros.')->group(function () {\n        $routes",
                $webRoutes
            );
        } else {
            if (!str_contains($webRoutes, "Route::prefix('cadastros')->name('cadastros.')")) {
                $webRoutes .= <<<PHP

Route::prefix('cadastros')->name('cadastros.')->group(function () {
    $routes
});
PHP;
            } else {
                $webRoutes = preg_replace(
                    "/Route::prefix\('cadastros'\)->name\('cadastros.'\)->group\(function\s*\(\)\s*{/",
                    "Route::prefix('cadastros')->name('cadastros.')->group(function () {\n        $routes",
                    $webRoutes
                );
            }
        }

        file_put_contents($routePath, $webRoutes);
        $this->info('Rotas adicionadas ao arquivo routes/web.php');
    }






    private function getFirstStringField(string $modelClass): string
    {
        $tableName = (new $modelClass())->getTable();
        $columns = Schema::getColumnListing($tableName);

        foreach ($columns as $column) {
            $type = Schema::getColumnType($tableName, $column);
            if (in_array($type, ['string', 'varchar', 'text', 'char'])) {
                return $column;
            }
        }

        return $columns[0];
    }

    private function getRelations(string $modelClass): array
    {
        $modelInstance = new $modelClass();
        $methods = get_class_methods($modelInstance);

        $relations = [];

        foreach ($methods as $method) {
            $reflection = new \ReflectionMethod($modelInstance, $method);

            // Ignorar métodos herdados ou protegidos
            if (!$reflection->isPublic() || $reflection->getDeclaringClass()->getName() !== $modelClass) {
                continue;
            }

            // Testar se o método retorna uma relação do Eloquent
            try {
                $returnValue = $reflection->invoke($modelInstance);

                if ($returnValue instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                    $relations[$method] = get_class($returnValue->getRelated());
                }
            } catch (\Throwable $e) {
                // Ignorar erros ao invocar métodos que não são relações
                continue;
            }
        }

        return $relations;
    }
}