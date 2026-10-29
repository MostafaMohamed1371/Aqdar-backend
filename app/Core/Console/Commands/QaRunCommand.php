<?php

namespace App\Core\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class QaRunCommand extends Command
{
    protected $signature = 'structer:qa
                            {--unit : Run PHPUnit unit tests only}
                            {--integration : Run PHPUnit integration tests only}
                            {--python : Run Python QA (integration + BDD + performance)}
                            {--e2e : Run Selenium E2E tests (requires Chrome + running app)}
                            {--jmeter : Run Apache JMeter load test}
                            {--skip-python : Skip Python tests}
                            {--all : Run everything including E2E and JMeter}';

    protected $description = 'Run Structer QA: Unit, Integration, BDD, E2E, Performance';

    public function handle(): int
    {
        $onlyUnit = $this->option('unit') && ! $this->option('integration');
        $onlyIntegration = $this->option('integration') && ! $this->option('unit');
        $runAll = $this->option('all');
        $failed = false;

        $this->components->info('Structer Quality Assurance (QA)');

        if ($onlyUnit) {
            $failed = $this->runPhpSuite('Unit') || $failed;
        } elseif ($onlyIntegration) {
            $failed = $this->runPhpSuite('Integration') || $failed;
        } else {
            $failed = $this->runPhpSuite('Unit') || $failed;
            $failed = $this->runPhpSuite('Integration') || $failed;
        }

        $runPython = $this->option('python')
            || ($runAll && ! $this->option('skip-python'))
            || (! $onlyUnit && ! $onlyIntegration && ! $this->option('skip-python') && ! $this->option('e2e') && ! $this->option('jmeter'));

        if ($runPython && ! $this->option('skip-python')) {
            $this->newLine();
            $this->components->twoColumnDetail('Python QA', 'integration + BDD + performance');
            $failed = $this->runPythonTests(['integration', 'bdd/step_defs', 'performance', '-m', 'not e2e']) || $failed;
        }

        if ($this->option('e2e') || $runAll) {
            $this->newLine();
            $this->components->twoColumnDetail('Selenium E2E', '');
            $failed = $this->runPythonTests(['e2e', '-m', 'e2e']) || $failed;
        }

        if ($this->option('jmeter') || $runAll) {
            $this->newLine();
            $this->components->twoColumnDetail('JMeter', '');
            $failed = $this->runJmeter() || $failed;
        }

        if ($failed) {
            $this->components->error('QA run finished with failures.');

            return self::FAILURE;
        }

        $this->components->info('QA run passed.');

        return self::SUCCESS;
    }

    private function runPhpSuite(string $suite): bool
    {
        $this->newLine();
        $this->components->twoColumnDetail("PHPUnit {$suite}", '');

        return $this->runProcess(['php', 'artisan', 'test', "--testsuite={$suite}"]);
    }

    /**
     * @param  array<int, string>  $targets
     */
    private function runPythonTests(array $targets): bool
    {
        $pythonDir = base_path('qa/python');

        if (! $this->pythonBinary()) {
            $this->components->warn('Python 3 not found — install Python or use --skip-python');

            return false;
        }

        $this->ensurePythonDeps($pythonDir);

        return $this->runProcess(
            array_merge([$this->pythonBinary(), '-m', 'pytest'], $targets),
            $pythonDir,
        );
    }

    private function runJmeter(): bool
    {
        $jmx = base_path('qa/jmeter/structer-load-test.jmx');

        if (! is_file($jmx)) {
            $this->components->warn('JMeter plan not found at qa/jmeter/structer-load-test.jmx');

            return false;
        }

        $jmeter = trim((string) shell_exec('which jmeter 2>/dev/null'));

        if ($jmeter === '') {
            $this->components->warn('JMeter not installed. See qa/jmeter/README.md');

            return false;
        }

        $reportDir = storage_path('app/qa/jmeter-'.date('Y-m-d-His'));
        @mkdir($reportDir, 0755, true);

        return $this->runProcess([
            $jmeter,
            '-n',
            '-t',
            $jmx,
            '-JBASE_URL='.rtrim(config('app.url', 'http://127.0.0.1:8000'), '/'),
            '-JTHREADS=10',
            '-JLOOPS=20',
            '-l',
            $reportDir.'/results.jtl',
            '-e',
            '-o',
            $reportDir.'/report',
        ]);
    }

    private function pythonBinary(): ?string
    {
        foreach (['python3', 'python'] as $bin) {
            $path = trim((string) shell_exec("which {$bin} 2>/dev/null"));
            if ($path !== '') {
                return $path;
            }
        }

        return null;
    }

    private function ensurePythonDeps(string $pythonDir): void
    {
        $marker = $pythonDir.'/.qa-deps-installed';

        if (is_file($marker)) {
            return;
        }

        $python = $this->pythonBinary();
        if ($python === null) {
            return;
        }

        $this->components->warn('Installing Python QA dependencies (first run)...');

        $process = new Process(
            [$python, '-m', 'pip', 'install', '-r', 'requirements.txt'],
            $pythonDir,
        );
        $process->setTimeout(300);
        $process->run();

        if ($process->isSuccessful()) {
            file_put_contents($marker, date('c'));
        }
    }

    /**
     * @param  array<int, string>  $command
     */
    private function runProcess(array $command, ?string $cwd = null): bool
    {
        $process = new Process($command, $cwd ?? base_path());
        $process->setTimeout(600);
        $process->run(function ($type, $buffer): void {
            $this->output->write($buffer);
        });

        return ! $process->isSuccessful();
    }
}
