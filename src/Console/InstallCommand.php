<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

/**
 * Artisan command to install Lingua package resources.
 */
final class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'lingua:install';

    /**
     * The console command description.
     */
    protected $description = 'Install Lingua package resources';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->publishConfig()) {
            return self::FAILURE;
        }

        if (! $this->installNpmPackage()) {
            return self::FAILURE;
        }

        $this->newLine();
        info('Lingua has been installed successfully!');

        return self::SUCCESS;
    }

    /**
     * Publish the Lingua configuration file.
     */
    private function publishConfig(): bool
    {
        info('Publishing Lingua configuration...');

        $result = $this->call('vendor:publish', [
            '--tag' => 'lingua-config',
        ]);

        if ($result !== self::SUCCESS) {
            error('Failed to publish configuration.');

            return false;
        }

        info('Configuration published successfully.');
        $this->newLine();

        return true;
    }

    /**
     * Install the @tooinfinity/lingua-react npm package.
     */
    private function installNpmPackage(): bool
    {
        info('Detecting package manager...');

        $packageManager = $this->detectPackageManager();

        info("Using {$packageManager} to install @tooinfinity/lingua-react...");

        $command = $this->buildInstallCommand($packageManager);

        $result = spin(
            callback: fn () => Process::run($command),
            message: 'Installing npm package...'
        );

        if (! $result->successful()) {
            error('Failed to install @tooinfinity/lingua-react.');
            error($result->errorOutput());

            return false;
        }

        info('Package installed successfully.');

        return true;
    }

    /**
     * Detect the package manager by checking for lock files.
     */
    private function detectPackageManager(): string
    {
        $basePath = base_path();

        // Check lock files in order of preference
        if (file_exists($basePath.'/bun.lockb') || file_exists($basePath.'/bun.lock')) {
            return 'bun';
        }

        if (file_exists($basePath.'/pnpm-lock.yaml')) {
            return 'pnpm';
        }

        if (file_exists($basePath.'/yarn.lock')) {
            return 'yarn';
        }

        // Default to npm (also covers package-lock.json)
        return 'npm';
    }

    /**
     * Build the install command for the detected package manager.
     */
    private function buildInstallCommand(string $packageManager): string
    {
        $package = '@tooinfinity/lingua-react';

        return match ($packageManager) {
            'bun' => "bun add {$package}",
            'pnpm' => "pnpm add {$package}",
            'yarn' => "yarn add {$package}",
            default => "npm install {$package}",
        };
    }
}
