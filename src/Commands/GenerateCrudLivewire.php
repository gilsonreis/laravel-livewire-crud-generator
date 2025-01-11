<?php

namespace Gilsonreis\LaravelLivewireCrudGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GenerateCrudLivewire extends Command
{
    protected $signature = 'make:crud-livewire
                            {--model= : Nome do Model para gerar o CRUD completo}
                            {--name= : Nome do componente Livewire em branco}
                            {--directory= : Diretório para salvar o componente em branco}';
    protected $description = 'Gera componentes Livewire com base no Model ou um componente em branco.';

    public function handle()
    {
        $model = $this->option('model');
        $name = $this->option('name');
        $directory = $this->option('directory');

        if ($model) {
            $this->info("Gerando CRUD completo para o Model: $model");
            $this->generateCrudFromModel($model);
        } elseif ($name) {
            if (!$directory) {
                $this->error('Você precisa informar o diretório para salvar o componente usando --directory');
                return;
            }

            $this->info("Gerando componente vazio: $directory/$name");
            $this->generateEmptyComponent($name, $directory);
        } else {
            $this->error('Você deve informar --model para o CRUD ou --name e --directory para um componente em branco.');
        }
    }

    protected function generateCrudFromModel(string $model)
    {
        $this->generateIndexPage($model);
        $this->info("CRUD para o Model $model gerado com sucesso!");
    }

    protected function generateEmptyComponent(string $componentName, string $directory)
    {
        // Lógica para gerar um componente vazio
        $this->info("Componente vazio $directory/$componentName gerado com sucesso!");
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
        $stub = file_get_contents($stubPath);

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








    protected function getFirstStringField(string $modelClass): ?string
    {
        $tableName = (new $modelClass())->getTable();
        $columns = Schema::getColumnListing($tableName);

        foreach ($columns as $column) {
            $type = Schema::getColumnType($tableName, $column);
            if ($type === 'string' || $type === 'varchar' || $type === 'text') {
                return $column;
            }
        }

        return null;
    }
}