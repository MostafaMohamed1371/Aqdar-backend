<?php

namespace App\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeModuleCommand extends Command
{
    protected $signature = 'make:module
                            {name : The module name (e.g. Product, Order)}
                            {--force : Overwrite existing module files}';

    protected $description = 'Create a new HMVC module with service, repository, controller, and routes';

    public function __construct(private Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $modulePath = app_path("Modules/{$name}");

        if ($this->files->isDirectory($modulePath) && ! $this->option('force')) {
            $this->components->error("Module [{$name}] already exists.");

            return self::FAILURE;
        }

        $replacements = [
            '{{ module }}' => $name,
            '{{ moduleLower }}' => Str::camel($name),
            '{{ moduleKebab }}' => Str::kebab($name),
            '{{ moduleSnake }}' => Str::snake($name),
        ];

        $structure = [
            'Providers/{{ module }}ModuleServiceProvider.php' => 'module-provider.stub',
            'Http/Controllers/{{ module }}Controller.php' => 'controller.stub',
            'Http/Requests/Store{{ module }}Request.php' => 'store-request.stub',
            'Http/Requests/Update{{ module }}Request.php' => 'update-request.stub',
            'Http/Resources/{{ module }}Resource.php' => 'resource.stub',
            'Models/{{ module }}.php' => 'model.stub',
            'Services/{{ module }}Service.php' => 'service.stub',
            'Repositories/{{ module }}Repository.php' => 'repository.stub',
            'Routes/api.php' => 'routes-api.stub',
            'Routes/web.php' => 'routes-web.stub',
            'Database/Migrations/.gitkeep' => null,
            'Database/Factories/.gitkeep' => null,
            'Database/Seeders/.gitkeep' => null,
            'Resources/views/.gitkeep' => null,
            'Tests/Feature/.gitkeep' => null,
            'Tests/Unit/.gitkeep' => null,
        ];

        foreach ($structure as $relativePath => $stub) {
            $path = $modulePath.'/'.str_replace('{{ module }}', $name, $relativePath);

            if ($stub === null) {
                $this->files->ensureDirectoryExists(dirname($path));
                if (! $this->files->exists($path)) {
                    $this->files->put($path, '');
                }

                continue;
            }

            $this->createFromStub($stub, $path, $replacements);
        }

        $this->components->info("Module [{$name}] created successfully.");
        $this->line('');
        $this->line('Next steps:');
        $this->line("  1. Edit app/Modules/{$name}/Models/{$name}.php");
        $this->line("  2. Add a migration in app/Modules/{$name}/Database/Migrations/");
        $this->line('  3. Run: php artisan migrate');

        return self::SUCCESS;
    }

    /** @param  array<string, string>  $replacements */
    private function createFromStub(string $stub, string $targetPath, array $replacements): void
    {
        $stubPath = app_path("Core/Console/Stubs/{$stub}");

        if (! $this->files->exists($stubPath)) {
            $this->components->error("Stub not found: {$stub}");

            return;
        }

        $content = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $this->files->get($stubPath),
        );

        $this->files->ensureDirectoryExists(dirname($targetPath));
        $this->files->put($targetPath, $content);
    }
}
