<?php

namespace Gilsonreis\LaravelLivewireCrudGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateCrudUseCase extends Command
{
    protected $signature = 'make:crud-use-case
                            {--model= : Nome do Model para o CRUD}
                            {--name= : Nome do UseCase em branco}
                            {--directory= : Diretório para o UseCase em branco}';
    protected $description = 'Gera UseCases para ações CRUD ou um UseCase em branco';

    public function handle()
    {
        $modelName = $this->option('model');
        $name = $this->option('name');
        $directory = $this->option('directory');

        // Verifica se estamos gerando o CRUD para um Model específico
        if ($modelName) {
            $modelName = Str::studly($modelName);

            if (!class_exists("App\\Models\\{$modelName}")) {
                $this->error("O Model {$modelName} não foi encontrado.");
                return;
            }

            $repositoryInterface = "{$modelName}RepositoryInterface";
            $directory = $modelName;

            // Gerar UseCases CRUD individuais
            $this->generateGetAllUseCase($modelName, $repositoryInterface, $directory);
            $this->generateCreateUseCase($modelName, $repositoryInterface, $directory);
            $this->generateShowUseCase($modelName, $repositoryInterface, $directory);
            $this->generateUpdateUseCase($modelName, $repositoryInterface, $directory);
            $this->generateDeleteUseCase($modelName, $repositoryInterface, $directory);

        } elseif ($name) {
            // Geração de UseCase em branco
            if (!$directory) {
                $this->error('O parâmetro --directory é obrigatório para UseCases em branco.');
                return;
            }

            $this->generateEmptyUseCase($name, $directory);
        } else {
            $this->error('É necessário fornecer --model ou --name e --directory.');
        }
    }

    private function generateEmptyUseCase($name, $directory)
    {
        $useCaseName = Str::studly($name);
        $useCasePath = app_path("UseCases/{$directory}/{$useCaseName}.php");

        File::ensureDirectoryExists(app_path("UseCases/{$directory}"));

        $useCaseContent = "<?php

namespace App\UseCases\\{$directory};

class {$useCaseName}
{
    public function __construct()
    { }

    public function handle()
    {
        // Implementação do UseCase
    }
}";

        File::put($useCasePath, $useCaseContent);
        $this->info("UseCase em branco {$useCaseName} criado com sucesso.");
    }

    private function generateGetAllUseCase($modelName, $repositoryInterface, $directory)
    {
        $useCaseName = "GetAll{$modelName}UseCase";
        $useCasePath = app_path("UseCases/{$directory}/{$useCaseName}.php");

        File::ensureDirectoryExists(app_path("UseCases/{$directory}"));

        $useCaseContent = "<?php

namespace App\UseCases\\{$directory};

use Gilsonreis\LaravelLivewireCrudGenerator\Support\Filter;
use App\Repositories\\{$directory}\\{$repositoryInterface};

readonly class {$useCaseName}
{
    public function __construct(
        private readonly {$repositoryInterface} \$repository
    ) {}

    public function handle(Filter \$filter)
    {
        return \$this->repository->getAll(\$filter);
    }
}";

        File::put($useCasePath, $useCaseContent);
        $this->info("UseCase {$useCaseName} criado com sucesso.");
    }

    private function generateCreateUseCase($modelName, $repositoryInterface, $directory)
    {
        $useCaseName = "Create{$modelName}UseCase";
        $useCasePath = app_path("UseCases/{$directory}/{$useCaseName}.php");

        File::ensureDirectoryExists(app_path("UseCases/{$directory}"));

        $useCaseContent = "<?php

namespace App\UseCases\\{$directory};

use App\Repositories\\{$directory}\\{$repositoryInterface};

class {$useCaseName}
{
    public function __construct(
        private readonly {$repositoryInterface} \$repository
    ) {}

    public function handle(array \$data)
    {
        return \$this->repository->create(\$data);
    }
}";

        File::put($useCasePath, $useCaseContent);
        $this->info("UseCase {$useCaseName} criado com sucesso.");
    }

    private function generateShowUseCase($modelName, $repositoryInterface, $directory)
    {
        $useCaseName = "Show{$modelName}UseCase";
        $useCasePath = app_path("UseCases/{$directory}/{$useCaseName}.php");

        File::ensureDirectoryExists(app_path("UseCases/{$directory}"));

        $useCaseContent = "<?php

namespace App\UseCases\\{$directory};

use App\Repositories\\{$directory}\\{$repositoryInterface};

class {$useCaseName}
{
    public function __construct(
        private readonly {$repositoryInterface} \$repository
    ) {}

    public function handle(int \$id)
    {
        return \$this->repository->find(\$id);
    }
}";

        File::put($useCasePath, $useCaseContent);
        $this->info("UseCase {$useCaseName} criado com sucesso.");
    }

    private function generateUpdateUseCase($modelName, $repositoryInterface, $directory)
    {
        $useCaseName = "Update{$modelName}UseCase";
        $useCasePath = app_path("UseCases/{$directory}/{$useCaseName}.php");

        File::ensureDirectoryExists(app_path("UseCases/{$directory}"));

        $useCaseContent = "<?php

namespace App\UseCases\\{$directory};

use App\Repositories\\{$directory}\\{$repositoryInterface};

class {$useCaseName}
{
    public function __construct(
        private readonly {$repositoryInterface} \$repository
    ) {}

    public function handle(int \$id, array \$data)
    {
        return \$this->repository->update(\$id, \$data);
    }
}";

        File::put($useCasePath, $useCaseContent);
        $this->info("UseCase {$useCaseName} criado com sucesso.");
    }

    private function generateDeleteUseCase($modelName, $repositoryInterface, $directory)
    {
        $useCaseName = "Delete{$modelName}UseCase";
        $useCasePath = app_path("UseCases/{$directory}/{$useCaseName}.php");

        File::ensureDirectoryExists(app_path("UseCases/{$directory}"));

        $useCaseContent = "<?php

namespace App\UseCases\\{$directory};

use App\Repositories\\{$directory}\\{$repositoryInterface};

class {$useCaseName}
{
    public function __construct(
        private readonly {$repositoryInterface} \$repository
    ) {}

    public function handle(int \$id)
    {
        return \$this->repository->delete(\$id);
    }
}";

        File::put($useCasePath, $useCaseContent);
        $this->info("UseCase {$useCaseName} criado com sucesso.");
    }
}
