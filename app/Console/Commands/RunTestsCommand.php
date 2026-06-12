<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class RunTestsCommand extends Command
{
    protected $signature = 'test
        {--filter= : Run only tests matching the given pattern}
        {--testsuite= : Run only the given PHPUnit test suite}
        {--stop-on-failure : Stop after the first failed test}';

    protected $description = 'Run the PHPUnit test suite.';

    public function handle(): int
    {
        $phpunit = base_path('vendor/phpunit/phpunit/phpunit');

        if (! file_exists($phpunit)) {
            $this->error('PHPUnit is not installed. Run composer install with dev dependencies.');

            return self::FAILURE;
        }

        $command = [PHP_BINARY, $phpunit];

        if ($filter = $this->option('filter')) {
            $command[] = '--filter';
            $command[] = $filter;
        }

        if ($suite = $this->option('testsuite')) {
            $command[] = '--testsuite';
            $command[] = $suite;
        }

        if ($this->option('stop-on-failure')) {
            $command[] = '--stop-on-failure';
        }

        $process = new Process($command, base_path());
        $process->setTimeout(null);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        return $process->getExitCode() ?? self::FAILURE;
    }
}
