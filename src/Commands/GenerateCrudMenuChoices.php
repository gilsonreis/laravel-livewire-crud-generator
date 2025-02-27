<?php

namespace Gilsonreis\LaravelLivewireCrudGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;


class GenerateCrudMenuChoices extends Command
{
    protected $signature = 'crud:menu';
    protected $description = 'Menu interativo para geração de CRUDs';

    public function handle()
    {
        while (true) {
            $choice = $this->choice(
                "\n======================= GERADOR DE CRUDS - LARAVEL COM LIVEWIRE =======================\n" .
                'Selecione uma opção abaixo:',
                [
                    'Gerar CRUD completo',
                    'Gerar Livewire Componente',
                    'Gerar UseCase',
                    'Gerar Repository',
                    'Gerar Model',
                    'Sobre',
                    'Sair',
                ]
            );

            switch ($choice) {
                case 'Gerar CRUD completo':
                    $this->generateFullLivewireCrud();
                    break;
                case 'Gerar Livewire Componente':
                    $this->generateLivewireComponent();
                    break;
                case 'Gerar UseCase':
                    $this->generateUseCase();
                    break;
                case 'Gerar Repository':
                    $this->generateRepository();
                    break;
                case 'Gerar Model':
                    $this->generateModel();
                    break;
//                case 'Gerar Login':
//                    $this->generateLogin();
//                    break;
                case 'Sobre':
                    $this->displayAbout();
                    break;
                case 'Sair':
                    $this->info('Saindo...');
                    return;
            }

            $this->waitForKeyPress();
            $this->clearScreen();
        }
    }

    private function makeCommandRun($command, $commandOptions): void
    {
        $command = $this->getApplication()->find($command);
        $input = new ArrayInput($commandOptions);
        $input->setInteractive(true);
        $command->run($input, $this->output);
    }

    private function generateModel(): void
    {
        $tableName = $this->ask('Informe o nome da tabela:');
        $label = $this->ask('Informe o label (singular) do Model:');
        $pluralLabel = $this->ask('Informe o label (plural) do Model:');
        $addObserver = $this->confirm('Deseja adicionar um Observer para o Model?', false);

        $commandOptions = [
            '--table' => $tableName,
            '--label' => $label,
            '--plural-label' => $pluralLabel,
        ];

        if ($addObserver) {
            $commandOptions['--observer'] = true;
        }

        $this->makeCommandRun('make:crud-model', $commandOptions);
        $this->info("Model {$label} gerado com sucesso!");
    }

    private function generateRepository(): void
    {
        $repositoryName = $this->ask('Informe o nome do Repository:');
        $model = $this->ask('Informe o nome do Model (opcional) para incluir operações CRUD:');

        $commandOptions = [
            'repositoryName' => $repositoryName,
        ];

        if (!empty($model)) {
            $commandOptions['--model'] = $model;
        }
        $this->makeCommandRun('make:crud-repository', $commandOptions);
        $this->info("Repository {$repositoryName} gerado com sucesso!");
    }

    private function generateUseCase(): void
    {
        $useCaseType = $this->choice('Deseja gerar um UseCase para um model específico (CRUD) ou um UseCase em branco?', [
            'CRUD para Model',
            'UseCase em Branco',
        ]);

        if ($useCaseType === 'CRUD para Model') {
            $model = $this->ask('Informe o nome do Model para o CRUD:');
            $this->makeCommandRun('make:crud-use-case', ['--model' => $model]);
            $this->info("UseCases para CRUD do model {$model} gerados com sucesso!");

        } else {
            $name = $this->ask('Informe o nome do UseCase em branco:');
            $directory = $this->ask('Informe o diretório para o UseCase em branco:');

            $this->makeCommandRun('make:crud-use-case', [
                '--name' => $name,
                '--directory' => $directory,
            ]);
           
            $this->info("UseCase em branco {$name} criado no diretório {$directory} com sucesso!");
        }
    }

    private function generateLivewireComponent(): void
    {
        $actionType = $this->choice('Deseja gerar Actions para um CRUD de model específico ou uma Action em branco?', [
            'CRUD para Model',
            'Action em Branco',
        ]);

        if ($actionType === 'CRUD para Model') {
            $model = $this->ask('Informe o nome do Model para o CRUD:');
            $this->makeCommandRun('make:crud-actions',[ '--model' => $model]);
            $this->info("Actions para CRUD do model {$model} geradas com sucesso!");

        } else {
            $name = $this->ask('Informe o nome da Action em branco:');
            $directory = $this->ask('Informe o diretório para a Action em branco:');
          

            $this->makeCommandRun('make:crud-actions',[
                '--name' => $name,
                '--directory' => $directory,
            ]);

            $this->info("Action em branco {$name} criada no diretório {$directory} com sucesso!");
        }
    }

    private function generateRoutes()
    {
        $routeType = $this->choice('Deseja gerar rotas para um CRUD de model específico ou um arquivo de rotas em branco?', [
            'CRUD para Model',
            'Arquivo de Rotas em Branco',
        ]);

        if ($routeType === 'CRUD para Model') {
            $model = $this->ask('Informe o nome do Model para gerar as rotas CRUD:');
            $this->makeCommandRun('make:crud-routes',[ '--model' => $model]);
            $this->info("Rotas CRUD para o Model {$model} geradas com sucesso!");
        } else {
            $name = $this->ask('Informe o nome do arquivo de rota em branco:');
            $this->makeCommandRun('make:crud-routes',[ '--name' => $name]);
            $this->info("Arquivo de rotas em branco {$name} gerado com sucesso!");
        }
    }

    private function generateFullLivewireCrud()
    {
        $model = $this->ask('Informe o nome do Model para o CRUD completo:');

        $modelPath = app_path("Models/{$model}.php");

        if (!file_exists($modelPath)) {
            $this->warn("Model {$model} não encontrado em app/Models.");
            return;
        }

        $this->makeCommandRun('make:crud-repository',[
            'repositoryName' => "{$model}Repository",
            '--model' => $model,
        ]);

        // Gerar UseCases
        $this->info('Gerando UseCases...');
        $this->makeCommandRun('make:crud-use-case', ['--model' => $model]);

        $this->info('Gerando Rotas...');

        // Gerar Actions
        $this->info('Gerando Livewire Componentes...');
        $this->makeCommandRun('make:crud-livewire', ['--model' => $model]);
        $this->info("CRUD completo para o model {$model} gerado com sucesso!");
    }

    protected function displayAbout(): void
    {
        $this->info("\n========================== SOBRE O GERADOR LIVEWIRE ==========================");
        $this->info('Este gerador automatiza a criação de CRUDs completos em Laravel utilizando Livewire.');
        $this->info('Ele gera os seguintes componentes automaticamente:');
        $this->info('');
        $this->info("✅  Actions (caso necessário)");
        $this->info('✅  UseCases');
        $this->info('✅  Repositories');
        $this->info('✅  Models');
        $this->info('✅  Componentes Livewire (IndexPage, CreatePage, EditPage e FormPartial)');
        $this->info('✅  Views associadas (Listagem, Formulário, Modais, etc.)');
        $this->info('✅  Rotas organizadas dinamicamente em arquivos separados');
        $this->info('');
        $this->info('🎨 Personalização via Stubs:');
        $this->info('   - Todos os arquivos gerados usam *stubs personalizáveis.');
        $this->info('   - Para substituir um stub, publique os arquivos padrão com:');
        $this->info('       php artisan vendor:publish --tag=livewire-stubs');
        $this->info('   - Após a publicação, edite os arquivos em stubs/livewire/ conforme necessário mantendo, as variáveis de substituição.');
        $this->info('');
        $this->info('🛠️ Basta informar o nome do Model e o gerador criará toda a estrutura necessária!');
        $this->info("================================================================================\n");
    }

    private function clearScreen()
    {
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            system('cls');
        } else {
            system('clear');
        }
    }
    private function waitForKeyPress()
    {
        $this->info("\nPressione enter tecla para continuar...");
        readline();
    }

    private function generateLogin()
    {
        Artisan::call('make:crud-auth');
    }

}
