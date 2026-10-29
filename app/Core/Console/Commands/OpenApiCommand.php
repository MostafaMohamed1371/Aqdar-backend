<?php

namespace App\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

class OpenApiCommand extends Command
{
    protected $signature = 'aqdar:openapi {--path=docs/openapi.yaml}';

    protected $description = 'Generate OpenAPI 3 YAML from /api/v1 routes without a third-party package';

    public function handle(): int
    {
        $paths = [];
        foreach (Route::getRoutes() as $route) {
            $uri = '/'.$route->uri();
            if (! str_starts_with($uri, '/api/v1')) {
                continue;
            }
            $methods = array_values(array_filter(
                $route->methods(),
                fn (string $method) => ! in_array($method, ['HEAD', 'OPTIONS'], true),
            ));
            if ($methods === []) {
                continue;
            }
            $openapiPath = preg_replace('#^/api/v1#', '', $uri) ?: '/';
            $paths[$openapiPath] ??= [];
            foreach ($methods as $method) {
                $paths[$openapiPath][strtolower($method)] = [
                    'summary' => implode('.', $route->gatherMiddleware()),
                    'responses' => [
                        '200' => ['description' => 'OK'],
                    ],
                ];
            }
        }

        ksort($paths);

        $doc = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'AQDAR API',
                'version' => 'v1',
                'description' => 'Generated from Laravel routes. Staff desk is Blade, not this spec.',
            ],
            'servers' => [
                ['url' => '/api/v1'],
            ],
            'paths' => $paths,
        ];

        $yaml = $this->toYaml($doc);
        $target = base_path((string) $this->option('path'));
        File::ensureDirectoryExists(dirname($target));
        File::put($target, $yaml);
        $this->info('wrote '.$target.' ('.count($paths).' paths)');

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $data */
    private function toYaml(array $data, int $indent = 0): string
    {
        $out = '';
        $pad = str_repeat('  ', $indent);
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($value === []) {
                    $out .= $pad.$this->key($key).": {}\n";
                    continue;
                }
                if (array_is_list($value)) {
                    $out .= $pad.$this->key($key).":\n";
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $nested = $this->toYaml($item, $indent + 2);
                            $lines = explode("\n", rtrim($nested));
                            $first = array_shift($lines);
                            $out .= $pad.'  - '.ltrim($first)."\n";
                            foreach ($lines as $line) {
                                $out .= '  '.$line."\n";
                            }
                        } else {
                            $out .= $pad.'  - '.$this->scalar($item)."\n";
                        }
                    }
                    continue;
                }
                $out .= $pad.$this->key($key).":\n".$this->toYaml($value, $indent + 1);
                continue;
            }
            $out .= $pad.$this->key($key).': '.$this->scalar($value)."\n";
        }

        return $out;
    }

    private function key(int|string $key): string
    {
        $key = (string) $key;
        if ($key === '' || preg_match('/[:#{}[\],&*?]|^\d/', $key) || str_contains($key, '/')) {
            return json_encode($key, JSON_UNESCAPED_SLASHES);
        }

        return $key;
    }

    private function scalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_numeric($value)) {
            return (string) $value;
        }

        $text = (string) $value;
        if (preg_match('/^[A-Za-z0-9._-]+$/', $text) === 1) {
            return $text;
        }

        return json_encode($text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
