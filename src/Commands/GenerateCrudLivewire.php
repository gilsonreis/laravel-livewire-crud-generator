<?php

namespace Gilsonreis\LaravelLivewireCrudGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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

        $this->generateModelForm($model);

        $this->generateFormPartial($model);
        $this->generateFormPartialView($model);

        $this->generateCreatePage($model);
        $this->generateCreatePageView($model);

        $this->generateEditPage($model);
        $this->generateEditPageView($model);

        $this->addRoutes($model);

        $this->insertCrudPermissions($model);

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
                    ? '                                    <td>{{ $' . Str::camel($model) . '->' . Str::camel($relationClass) . '->' . $relationField . ' ?? \'-\' }}</td>'
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
        $modelName = Str::studly($model);
        $modelPluralName = Str::pluralStudly($model);
        $modelKebab = Str::kebab($model);
        $modelPluralKebab = Str::plural($modelKebab);

        $routeFilePath = app_path("Routes/{$modelName}Routes.php");

        File::ensureDirectoryExists(app_path('Routes'));

        $useAuthMiddleware = $this->confirm("Deseja adicionar o middleware 'auth:sanctum' às rotas do model {$modelName}?", true);
        $middlewareString = $useAuthMiddleware ? "->middleware('auth:sanctum')" : '';

        $routeContent = "<?php

use Illuminate\\Support\\Facades\\Route;
use App\\Http\\Middleware\\CheckPermission;
use App\\Livewire\\Pages\\Cadastros\\{$modelPluralName};

Route::prefix('$modelPluralKebab')
    ->name('{$modelPluralKebab}.')$middlewareString
    ->group(function () {
        Route::get('/', {$modelPluralName}\\IndexPage::class)
            ->name('index')
            ->middleware(CheckPermission::class . ':{$modelKebab}/index');

        Route::get('/create', {$modelPluralName}\\CreatePage::class)
            ->name('create')
            ->middleware(CheckPermission::class . ':{$modelKebab}/create');

        Route::get('/{id}/edit', {$modelPluralName}\\EditPage::class)
            ->name('edit')
            ->middleware(CheckPermission::class . ':{$modelKebab}/edit');
    });
";
        // Verificar se o arquivo já existe
        if (!File::exists($routeFilePath)) {
            File::put($routeFilePath, $routeContent);
            $this->info("Arquivo de rotas CRUD para {$modelName} criado com sucesso em app/Routes.");
        } else {
            $this->info("O arquivo de rotas CRUD para {$modelName} já existe em app/Routes.");
        }

        $this->ensureRoutesLoader();
        $this->ensureCheckPermissionMiddlewareExists();
        $this->ensureBladeDirectives();
    }

    protected function generateCreatePage(string $model): void
    {
        $stubPath = __DIR__ . '/../stubs/pages/create-page.stub';
        $customStubPath = base_path('stubs/livewire/pages/create-page.stub');
        $stub = file_exists($customStubPath) ? file_get_contents($customStubPath) : file_get_contents($stubPath);

        $modelPluralStudly = Str::pluralStudly($model);
        $modelPlural = Str::camel(Str::pluralStudly($model));
        $className = 'CreatePage';
        $readableName = Str::headline($model);

        $namespacePath = app_path("Livewire/Pages/Cadastros/{$modelPluralStudly}");
        $filePath = "$namespacePath/$className.php";

        File::ensureDirectoryExists($namespacePath);

        $content = str_replace(
            ['{{ ModelPlural }}', '{{ modelPlural }}', '{{ readableName }}', '{{ className }}'],
            [$modelPluralStudly, $modelPlural, $readableName, $className],
            $stub
        );

        file_put_contents($filePath, $content);
        $this->info("CreatePage gerado com sucesso: $filePath");
    }

    protected function generateCreatePageView(string $model): void
    {
        $stubPath = __DIR__ . '/../stubs/views/create-page.stub';
        $customStubPath = base_path('stubs/livewire/views/create-page.stub');
        $stub = file_exists($customStubPath) ? file_get_contents($customStubPath) : file_get_contents($stubPath);

        $modelPlural = Str::pluralStudly($model);
        $modelPluralKebab = Str::kebab($modelPlural);
        $modelReadable = Str::headline($model);

        $namespacePath = resource_path("views/livewire/pages/cadastros/$modelPluralKebab");
        $viewPath = "$namespacePath/create-page.blade.php";

        File::ensureDirectoryExists($namespacePath);

        $content = str_replace(
            ['{{ readableName }}', '{{ modelPlural }}'],
            [$modelReadable, $modelPluralKebab],
            $stub
        );

        file_put_contents($viewPath, $content);
        $this->info("View de Create Page gerada com sucesso: $viewPath");
    }

    protected function generateFormPartial(string $model): void
    {
        $stubPath = __DIR__ . '/../stubs/pages/form-partial.stub';
        $customStubPath = base_path('stubs/livewire/pages/form-partial.stub');
        $stub = file_exists($customStubPath) ? file_get_contents($customStubPath) : file_get_contents($stubPath);

        $modelClass = "App\\Models\\$model";

        if (!class_exists($modelClass)) {
            $this->error("O Model $model não existe.");
            return;
        }

        $relations = $this->getRelations($modelClass);

        unset($relations['notifications'], $relations['readNotifications'], $relations['unreadNotifications']);

        $mountProperties = collect($relations)->map(function ($relation) {
            $model = Str::studly(class_basename($relation));
            $cModel = Str::camel($model);
            return "public array \$$cModel = [];";
        })->join("\n        ");
        $mountAssignments = collect($relations)->map(function ($relation) {
            $model = Str::studly(class_basename($relation));
            $cModel = Str::camel($model);
            return "\$this->$cModel = app(GetAll" . $model . 'UseCase::class)->handle();';
        }
        )->join("\n        ");


        $content = str_replace(
            [
                '{{ Model }}',
                '{{ ModelPlural }}',
                '{{ modelSingular }}',
                '{{ modelPlural }}',
                '{{ ModelReadable }}',
                '{{ modelSingularReadable }}',
                '{{ mountProperties }}',
                '{{ mountAssignments }}',
                '{{ useGetAllUseCase }}'
            ],
            [
                Str::studly($model),
                Str::pluralStudly($model),
                Str::camel($model),
                Str::camel(Str::pluralStudly($model)),
                Str::headline($model),
                Str::headline(Str::camel($model)),
                $mountProperties,
                $mountAssignments,
                collect($relations)->map(function ($relation) {
                    $model = Str::studly(class_basename($relation));
                    return "use App\\UseCases\\" . $model . "\\GetAll" . $model . 'UseCase;';
                }
                )->join("\n")
            ],
            $stub
        );

        $namespacePath = app_path('Livewire/Pages/Cadastros/' . Str::pluralStudly($model));
        $filePath = "$namespacePath/FormPartial.php";

        if (!is_dir($namespacePath)) {
            mkdir($namespacePath, 0755, true);
        }

        file_put_contents($filePath, $content);
        $this->info("FormPartial gerado com sucesso: $filePath");
    }

    protected function generateFormPartialView(string $model): void
    {
        $stubPath = __DIR__ . '/../stubs/views/form-partial.stub';
        $customStubPath = base_path('stubs/livewire/views/form-partial.stub');
        $stub = file_exists($customStubPath) ? file_get_contents($customStubPath) : file_get_contents($stubPath);

        $modelClass = "App\\Models\\$model";
        $table = (new $modelClass())->getTable();

        if (!class_exists($modelClass)) {
            $this->error("O Model $model não existe.");
            return;
        }

        // Obter todos os campos na ordem do banco
        $fields = Schema::getColumnListing($table);
        $fields = $this->filterFields($fields);

        // Obter relações e mapear os campos de chave estrangeira
        $relations = $this->getRelations($modelClass);
        $relationFields = collect($relations)
            ->mapWithKeys(fn($relation, $relationName) => [Str::snake($relationName) . '_id' => $relationName])
            ->toArray();

        // Processar os campos, substituindo chaves estrangeiras por campos de relação
        $allFields = collect($fields)
            ->map(function ($field) use ($relations, $relationFields, $table, $modelClass) {
                // Verificar se o campo atual é uma chave estrangeira
                if (isset($relationFields[$field])) {
                    $relationName = $relationFields[$field];
                    return $this->generateRelationFieldHtml($relationName, $modelClass);
                }

                return $this->generateFieldHtml($field, $table);
            })
            ->join("\n");

        $content = str_replace(
            ['{{ formFields }}', '{{ modelSingular }}', '{{ routePrefix }}'],
            [$allFields, Str::camel($model), 'cadastros.' . Str::kebab(Str::pluralStudly($model))],
            $stub
        );

        $namespacePath = resource_path('views/livewire/pages/cadastros/' . Str::kebab(Str::pluralStudly($model)));
        $viewPath = "$namespacePath/form-partial.blade.php";

        if (!is_dir($namespacePath)) {
            mkdir($namespacePath, 0755, true);
        }

        file_put_contents($viewPath, $content);
        $this->info("View form-partial gerada em: $viewPath");
    }

    protected function generateModelForm(string $model): void
    {
        $stubPath = __DIR__ . '/../stubs/pages/form-validador.stub';
        $customStubPath = base_path('stubs/livewire/pages/form-validador.stub');
        $stub = file_exists($customStubPath) ? file_get_contents($customStubPath) : file_get_contents($stubPath);

        $modelClass = "App\\Models\\$model";

        if (!class_exists($modelClass)) {
            $this->error("O Model $model não existe.");
            return;
        }

        $table = (new $modelClass)->getTable();
        $columns = Schema::getColumnListing($table);
        $connection = Schema::getConnection();
        $isSQLite = $connection->getDriverName() === 'sqlite';

        $fields = [];

        foreach ($columns as $field) {
            if (in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            $type = Schema::getColumnType($table, $field);
            $rules = [];

            if (!$isSQLite) {
                // Obter informações da estrutura da tabela com SHOW COLUMNS
                $columnsInfo = DB::select("SHOW COLUMNS FROM $table");

                $isRequired = false;
                $maxLength = null;

                foreach ($columnsInfo as $column) {
                    if ($column->Field === $field) {
                        $isRequired = $column->Null === 'NO';

                        if (preg_match('/\((\d+)\)/', $column->Type, $matches)) {
                            $maxLength = (int)$matches[1];
                        }
                        break;
                    }
                }
            } else {
                $isRequired = false;
                $maxLength = null;
                $notNull = DB::select("PRAGMA table_info($table)");

                foreach ($notNull as $column) {
                    if ($column->name === $field && $column->notnull) {
                        $isRequired = true;
                    }
                }
            }

            if ($isRequired) {
                $rules[] = 'required';
            }

            if ($type === 'text' && $isRequired) {
                $rules[] = 'string';
                $rules[] = 'max:65535';
            } elseif ($type === 'string' && $maxLength) {
                $rules[] = "max:$maxLength";
            } elseif ($type === 'integer') {
                $rules[] = 'integer';
            } elseif (in_array($type, ['decimal', 'float', 'double'])) {
                $rules[] = 'numeric';
            } elseif (in_array($type, ['date', 'datetime'])) {
                $rules[] = 'date';
            } elseif ($field === 'email') {
                $rules[] = 'email';
            }

            // Verificação de UNIQUE
            if (!$isSQLite) {
                $indexes = DB::select("SHOW INDEX FROM $table");

                foreach ($indexes as $index) {
                    if ($index->Non_unique == 0 && $index->Column_name === $field) {
                        $rules[] = "unique:$table,$field";
                        break;
                    }
                }
            }

            // Se não há regras, omite a validação
            $validateRule = !empty($rules) ? "#[Validate('" . implode('|', $rules) . "')]\n    " : '';

            // Gerar os campos do formulário
            $fields[] = sprintf("%spublic %s \$%s;", $validateRule, $this->getFieldType($type), $field);
        }

        $namespacePath = app_path('Livewire/Forms/' . Str::pluralStudly($model));
        $filePath = "$namespacePath/{$model}Form.php";

        File::ensureDirectoryExists($namespacePath);

        $content = str_replace(
            ['{{ Model }}', '{{ ModelPlural }}', '{{ properties }}'],
            [
                Str::studly($model),
                Str::pluralStudly($model),
                implode("\n\n    ", $fields) // Ajustando espaçamento correto
            ],
            $stub
        );

        file_put_contents($filePath, $content);
        $this->info('Form de validação gerado com sucesso.');
    }

    protected function generateEditPage(string $model): void
    {
        $stubPath = __DIR__ . '/../stubs/pages/edit-page.stub';
        $customStubPath = base_path('stubs/livewire/pages/edit-page.stub');
        $stub = file_exists($customStubPath) ? file_get_contents($customStubPath) : file_get_contents($stubPath);

        $modelClass = "App\\Models\\$model";

        if (!class_exists($modelClass)) {
            $this->error("O Model $model não existe.");
            return;
        }

        $firstTextField = $this->getFirstStringField($modelClass);
        $content = str_replace(
            ['{{ Model }}', '{{ modelSingular }}', '{{ modelPlural }}', '{{ ModelPlural }}', '{{ readableName }}', '{{ firstTextField }}'],
            [
                Str::studly($model),
                Str::camel($model),
                Str::camel(Str::pluralStudly($model)),
                Str::pluralStudly($model),
                Str::headline($model),
                $firstTextField
            ],
            $stub
        );

        $namespacePath = app_path('Livewire/Pages/Cadastros/' . Str::pluralStudly($model));
        $filePath = "$namespacePath/EditPage.php";

        if (!is_dir($namespacePath)) {
            mkdir($namespacePath, 0755, true);
        }

        file_put_contents($filePath, $content);
        $this->info("EditPage gerado com sucesso: $filePath");
    }

    protected function generateEditPageView(string $model): void
    {
        $stubPath = __DIR__ . '/../stubs/views/edit-page.stub';
        $customStubPath = base_path('stubs/livewire/views/edit-page.stub');
        $stub = file_exists($customStubPath) ? file_get_contents($customStubPath) : file_get_contents($stubPath);

        $modelClass = "App\\Models\\$model";

        if (!class_exists($modelClass)) {
            $this->error("O Model $model não existe.");
            return;
        }

        $firstTextField = $this->getFirstStringField($modelClass);
        $content = str_replace(
            ['{{ modelSingular }}', '{{ modelPlural }}', '{{ readableName }}', '{{ firstTextField }}'],
            [
                Str::camel($model),
                Str::camel(Str::pluralStudly($model)),
                Str::headline($model),
                $firstTextField
            ],
            $stub
        );

        $namespacePath = resource_path('views/livewire/pages/cadastros/' . Str::kebab(Str::pluralStudly($model)));
        $filePath = "$namespacePath/edit-page.blade.php";

        if (!is_dir($namespacePath)) {
            mkdir($namespacePath, 0755, true);
        }

        file_put_contents($filePath, $content);
        $this->info("View edit-page gerada com sucesso: $filePath");
    }

    protected function getFieldType(string $type): string
    {
        return match (true) {
            str_contains($type, 'int') => 'int',
            str_contains($type, 'decimal') || str_contains($type, 'float') || str_contains($type, 'double') => 'float',
            str_contains($type, 'date') => 'string', // Livewire trata datas como strings
            default => 'string',
        };
    }


    private function ensureRoutesLoader(): void
    {
        $webRoutesPath = base_path('routes/web.php');
        $globCode = <<<PHP
// Carregar automaticamente os arquivos de rotas do diretório app/Routes
foreach (glob(app_path('Routes/*.php')) as \$routeFile) {
    require \$routeFile;
}
PHP;

        // Ler o conteúdo atual do arquivo web.php ou inicializar com PHP tags
        $webRoutesContent = file_exists($webRoutesPath) ? file_get_contents($webRoutesPath) : "<?php\n\n";

        // Verificar se o glob já existe e só faz se não existe.
        if (!str_contains($webRoutesContent, "glob(app_path('Routes/*.php'))")) {

            // Adicionar o glob ao local correto
            if (str_contains($webRoutesContent, "Route::middleware(['auth:sanctum'])")) {
                // Dentro do middleware auth:sanctum
                if (str_contains($webRoutesContent, "Route::prefix('cadastros')")) {
                    // Dentro do prefix cadastros
                    $webRoutesContent = preg_replace(
                        "/Route::prefix\('cadastros'\)->name\('cadastros.'\)->group\(function\s*\(\)\s*{/",
                        "Route::prefix('cadastros')->name('cadastros.')->group(function () {\n    $globCode",
                        $webRoutesContent
                    );
                } else {
                    // Dentro de auth:sanctum, mas fora de cadastros
                    $webRoutesContent = preg_replace(
                        "/Route::middleware\(\['auth:sanctum'\]\)->group\(function\s*\(\)\s*{/",
                        "Route::middleware(['auth:sanctum'])->group(function () {\n    $globCode",
                        $webRoutesContent
                    );
                }
            } elseif (str_contains($webRoutesContent, "Route::prefix('cadastros')")) {
                // Fora de auth:sanctum, mas dentro do prefix cadastros
                $webRoutesContent = preg_replace(
                    "/Route::prefix\('cadastros'\)->name\('cadastros.'\)->group\(function\s*\(\)\s*{/",
                    "Route::prefix('cadastros')->name('cadastros.')->group(function () {\n    $globCode",
                    $webRoutesContent
                );
            } else {
                // Caso não tenha auth:sanctum ou prefix cadastros, adiciona no final
                $webRoutesContent .= "\n\n$globCode\n";
            }

            // Salvar o conteúdo atualizado
            file_put_contents($webRoutesPath, $webRoutesContent);
            $this->info('Código de carregamento dinâmico de rotas adicionado ao routes/web.php no local correto.');
        }
    }

    private function ensureCheckPermissionMiddlewareExists(): void
    {
        $middlewarePath = app_path('Http/Middleware/CheckPermission.php');

        // Verificar se o middleware já existe
        if (file_exists($middlewarePath)) {
            $this->info('O middleware CheckPermission já existe.');
            return;
        }

        // Conteúdo do middleware
        $middlewareContent = <<<PHP
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  \$next
     */
    public function handle(Request \$request, Closure \$next, string \$permission): Response
    {
        if (!\$request->user() || !\$request->user()->hasPermission(\$permission)) {
            abort(403, 'Você não tem permissão para acessar esta página.');
        }

        return \$next(\$request);
    }
}
PHP;

        // Criar o diretório Middleware se não existir
        if (!is_dir(dirname($middlewarePath))) {
            mkdir(dirname($middlewarePath), 0755, true);
        }

        // Criar o arquivo do middleware
        file_put_contents($middlewarePath, $middlewareContent);
        $this->info("Middleware CheckPermission criado com sucesso em $middlewarePath.");
    }

    private function ensureBladeDirectives(): void
    {
        $appServiceProviderPath = app_path('Providers/AppServiceProvider.php');

        // Verificar se o arquivo existe
        if (!file_exists($appServiceProviderPath)) {
            $this->error('O arquivo AppServiceProvider não existe.');
            return;
        }

        // Ler o conteúdo atual do AppServiceProvider
        $providerContent = file_get_contents($appServiceProviderPath);

        // Diretivas para adicionar
        $hasPermissionDirective = <<<PHP
Blade::directive('hasPermission', function (\$permission) {
            return "<?php if(auth()->check() && auth()->user()->hasPermission(\$permission)): ?>";
        });

Blade::directive('endHasPermission', function () {
            return '<?php endif; ?>';
        });
PHP;

        // Verificar se as diretivas já existem
        if (!str_contains($providerContent, "Blade::directive('hasPermission'")) {
            $providerContent = preg_replace(
                '/public function boot(\): void\s*{/',
                "public function boot(): void\n    {\n        $hasPermissionDirective",
                $providerContent
            );

            // Salvar as alterações no AppServiceProvider
            file_put_contents($appServiceProviderPath, $providerContent);
        }

    }


    private function getFirstStringField(string $modelClass): string
    {
        dump('ANTES -> ', $modelClass);
        $modelClass = str_starts_with($modelClass, 'App') ? $modelClass : 'App\\Models\\' . $modelClass;

        dump('DEPOiS -> ', $modelClass);

        if (!$this->ignoreUndesiredModels($modelClass)) {

            $tableName = (new $modelClass())->getTable();
            $columns = Schema::getColumnListing($tableName);

            foreach ($columns as $column) {
                $type = Schema::getColumnType($tableName, $column);
                if (in_array($type, ['string', 'varchar', 'text', 'char'])) {
                    return $column;
                }
            }
        }

        return $columns[0] ?? '';
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

            if (!$this->ignoreUndesiredModels($modelClass)) {
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
        }

        return $relations;
    }

    private function generateFieldHtml(string $field, string $table): string
    {
        $type = Schema::getColumnType($table, $field);
        $label = Str::headline($field);
        $wireModel = "form.$field";
        $errorClass = "@error('$wireModel') group-error @enderror";
        $errorMessage = "@error('$wireModel') <span class=\"text-danger\">{{ \$message }}</span> @enderror";
        $disabledAttr = "{{ \$readOnly ? 'disabled' : '' }}";

        return match ($type) {
            'string' => <<<HTML
            <div class="form-group col-md-6 $errorClass">
                <label for="$field" class="form-label">$label</label>
                <input type="text" maxlength="255" class="form-control" id="$field" wire:model.lazy="$wireModel" $disabledAttr>
                $errorMessage
            </div>
        HTML,
            'text' => <<<HTML
            <div class="form-group col-md-12 $errorClass">
                <label for="$field" class="form-label">$label</label>
                <textarea class="form-control" id="$field" wire:model.lazy="$wireModel" $disabledAttr></textarea>
                $errorMessage
            </div>
        HTML,
            'date' => <<<HTML
            <div class="form-group col-md-6 $errorClass">
                <label for="$field" class="form-label">$label</label>
                <input type="date" class="form-control" id="$field" wire:model.lazy="$wireModel" $disabledAttr>
                $errorMessage
            </div>
        HTML,
            'datetime' => <<<HTML
            <div class="form-group col-md-6 $errorClass">
                <label for="$field" class="form-label">$label</label>
                <input type="datetime-local" class="form-control" id="$field" wire:model.lazy="$wireModel" $disabledAttr>
                $errorMessage
            </div>
        HTML,
            'boolean' => <<<HTML
            <div class="form-check $errorClass">
                <input type="checkbox" class="form-check-input" id="$field" wire:model.lazy="$wireModel" $disabledAttr>
                <label for="$field" class="form-check-label">$label</label>
                $errorMessage
            </div>
        HTML,
            'integer' => <<<HTML
            <div class="form-group col-md-6 $errorClass">
                <label for="$field" class="form-label">$label</label>
                <input type="number" class="form-control" id="$field" wire:model.lazy="$wireModel" $disabledAttr>
                $errorMessage
            </div>
        HTML,
            'float', 'double', 'decimal' => <<<HTML
            <div class="form-group col-md-6 $errorClass">
                <label for="$field" class="form-label">$label</label>
                <input type="text" class="form-control money" id="$field" wire:model.lazy="$wireModel" $disabledAttr>
                $errorMessage
            </div>
        HTML,
            default => <<<HTML
            <div class="form-group col-md-6 $errorClass">
                <label for="$field" class="form-label">$label</label>
                <input type="text" class="form-control" id="$field" wire:model.lazy="$wireModel" $disabledAttr>
                $errorMessage
            </div>
        HTML,
        };
    }

    private function generateRelationFieldHtml(string $relation): string
    {
        $relationLabelField = $this->getFirstStringField($relation);
        $relationModel = Str::camel(class_basename($relation));
        $disabledAttr = "{{ \$readOnly ? 'disabled' : '' }}";

        $wireModel = "{$relation}_id";
        $errorClass = "@error('$wireModel') group-error @enderror";

        $errorMessage = "@error('$wireModel') <span class=\"text-danger\">{{ \$message }}</span> @enderror";

        return <<<HTML
        <div class="form-group col-md-6 {$errorClass}">
            <label for="{$wireModel}" class="form-label">{$relationModel}</label>
            <select class="form-select" id="{$wireModel}" wire:model="{$wireModel}" $disabledAttr>
                <option value="">Selecione</option>
                @foreach(\$$relationModel as \$item)
                    <option value="{{ \$item['id'] }}">{{ \$item['$relationLabelField'] }}</option>
                @endforeach
            </select>
            {$errorMessage}
        </div>
    HTML;
    }

    private function filterFields(array $fields): array
    {
        $excludedFields = ['id', 'created_at', 'updated_at', 'deleted_at'];
        return array_filter($fields, fn($field) => !in_array($field, $excludedFields));
    }

    private function ignoreUndesiredModels(string $modelClass): bool
    {
        $modelClass = str_starts_with($modelClass, 'App') ? $modelClass : 'App\\Models\\' . $modelClass;
        return str_contains($modelClass, 'Illuminate\\') || str_contains($modelClass, 'DatabaseNotification\\');
    }

    protected function insertCrudPermissions(string $model): void
    {
        // Verifica se as tabelas necessárias existem
        if (!Schema::hasTable('permissions') || !Schema::hasTable('permission_role')) {
            return;
        }

        // Pergunta ao usuário se deseja inserir as permissões
        if (!$this->confirm("Deseja inserir permissões para o CRUD do model {$model}?")) {
            return;
        }

        // Obtém o primeiro usuário para associar as permissões
        $firstUser = DB::table('users')->first();
        if (!$firstUser || !isset($firstUser->role_id)) {
            $this->warn("Nenhum usuário encontrado ou sem 'role_id'. Permissões não foram associadas.");
            return;
        }

        $roleId = $firstUser->role_id;

        // Converte o nome do model para kebab-case
        $modelKebab = Str::kebab($model);

        // Define as permissões padrão do CRUD
        $permissions = [
            "{$modelKebab}/index",
            "{$modelKebab}/create",
            "{$modelKebab}/edit",
            "{$modelKebab}/delete",
        ];

        foreach ($permissions as $permissionName) {
            // Verifica se a permissão já existe
            $permission = DB::table('permissions')->where('name', $permissionName)->first();

            if (!$permission) {
                // Insere a permissão na tabela
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => $permissionName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Associa a permissão ao role_id do usuário
                DB::table('permission_role')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);

                $this->info("Permissão '{$permissionName}' inserida.");
            }
        }

        $this->info("Todas as permissões foram processadas para o CRUD do model {$model}.");
    }
}
