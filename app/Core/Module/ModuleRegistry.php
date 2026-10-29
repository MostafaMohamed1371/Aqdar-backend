<?php

namespace App\Core\Module;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class ModuleRegistry
{
    /** @return Collection<int, class-string<ModuleServiceProvider>> */
    public function providers(): Collection
    {
        $modulesPath = app_path('Modules');

        if (! is_dir($modulesPath)) {
            return collect();
        }

        return collect(File::directories($modulesPath))
            ->map(fn (string $directory) => basename($directory))
            ->filter(fn (string $module) => $this->isEnabled($module))
            ->map(fn (string $module) => "App\\Modules\\{$module}\\Providers\\{$module}ModuleServiceProvider")
            ->filter(fn (string $provider) => class_exists($provider));
    }

    public function isEnabled(string $module): bool
    {
        $disabled = config('structer.disabled_modules', []);

        return ! in_array($module, $disabled, true);
    }
}
