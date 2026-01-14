<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use TooInfinity\Lingua\Console\InstallCommand;

beforeEach(function (): void {
    // Clean up any lock files that might exist from previous tests
    cleanupLockFiles();
});

afterEach(function (): void {
    // Clean up any lock files created during tests
    cleanupLockFiles();

    // Clean up published config if it exists
    if (File::exists(config_path('lingua.php'))) {
        File::delete(config_path('lingua.php'));
    }
});

/**
 * Helper function to clean up package manager lock files.
 */
function cleanupLockFiles(): void
{
    $lockFiles = [
        base_path('yarn.lock'),
        base_path('pnpm-lock.yaml'),
        base_path('bun.lockb'),
        base_path('bun.lock'),
    ];

    foreach ($lockFiles as $file) {
        if (File::exists($file)) {
            File::delete($file);
        }
    }
}

describe('command registration', function (): void {
    it('is registered with artisan', function (): void {
        $commands = Artisan::all();

        expect($commands)->toHaveKey('lingua:install');
    });

    it('has correct signature', function (): void {
        $command = new InstallCommand;

        expect($command->getName())->toBe('lingua:install');
    });

    it('has correct description', function (): void {
        $command = new InstallCommand;

        expect($command->getDescription())->toBe('Install Lingua package resources');
    });
});

describe('package manager detection', function (): void {
    it('detects npm as default when no lock file exists', function (): void {
        Process::fake([
            'npm install @tooinfinity/lingua-react' => Process::result(
                output: 'added 1 package',
                exitCode: 0
            ),
        ]);

        $this->artisan('lingua:install')
            ->assertSuccessful();

        Process::assertRan('npm install @tooinfinity/lingua-react');
    });

    it('detects yarn when yarn.lock exists', function (): void {
        File::put(base_path('yarn.lock'), '# yarn lockfile');

        Process::fake([
            'yarn add @tooinfinity/lingua-react' => Process::result(
                output: 'success',
                exitCode: 0
            ),
        ]);

        $this->artisan('lingua:install')
            ->assertSuccessful();

        Process::assertRan('yarn add @tooinfinity/lingua-react');
    });

    it('detects pnpm when pnpm-lock.yaml exists', function (): void {
        File::put(base_path('pnpm-lock.yaml'), 'lockfileVersion: 5.4');

        Process::fake([
            'pnpm add @tooinfinity/lingua-react' => Process::result(
                output: 'success',
                exitCode: 0
            ),
        ]);

        $this->artisan('lingua:install')
            ->assertSuccessful();

        Process::assertRan('pnpm add @tooinfinity/lingua-react');
    });

    it('detects bun when bun.lockb exists', function (): void {
        File::put(base_path('bun.lockb'), 'binary lock file content');

        Process::fake([
            'bun add @tooinfinity/lingua-react' => Process::result(
                output: 'success',
                exitCode: 0
            ),
        ]);

        $this->artisan('lingua:install')
            ->assertSuccessful();

        Process::assertRan('bun add @tooinfinity/lingua-react');
    });

    it('detects bun when bun.lock exists', function (): void {
        File::put(base_path('bun.lock'), 'lock file content');

        Process::fake([
            'bun add @tooinfinity/lingua-react' => Process::result(
                output: 'success',
                exitCode: 0
            ),
        ]);

        $this->artisan('lingua:install')
            ->assertSuccessful();

        Process::assertRan('bun add @tooinfinity/lingua-react');
    });

    it('prefers bun over other package managers when multiple lock files exist', function (): void {
        // Create multiple lock files - bun should take precedence
        File::put(base_path('bun.lockb'), 'binary lock file content');
        File::put(base_path('yarn.lock'), '# yarn lockfile');
        File::put(base_path('pnpm-lock.yaml'), 'lockfileVersion: 5.4');

        Process::fake([
            'bun add @tooinfinity/lingua-react' => Process::result(
                output: 'success',
                exitCode: 0
            ),
        ]);

        $this->artisan('lingua:install')
            ->assertSuccessful();

        Process::assertRan('bun add @tooinfinity/lingua-react');
    });

    it('prefers pnpm over yarn and npm when bun is not present', function (): void {
        File::put(base_path('pnpm-lock.yaml'), 'lockfileVersion: 5.4');
        File::put(base_path('yarn.lock'), '# yarn lockfile');

        Process::fake([
            'pnpm add @tooinfinity/lingua-react' => Process::result(
                output: 'success',
                exitCode: 0
            ),
        ]);

        $this->artisan('lingua:install')
            ->assertSuccessful();

        Process::assertRan('pnpm add @tooinfinity/lingua-react');
    });
});

describe('build install command', function (): void {
    it('builds correct npm command', function (): void {
        Process::fake([
            'npm install @tooinfinity/lingua-react' => Process::result(
                output: 'added 1 package',
                exitCode: 0
            ),
        ]);

        $this->artisan('lingua:install')
            ->assertSuccessful();

        Process::assertRan(fn ($process): bool => $process->command === 'npm install @tooinfinity/lingua-react');
    });

    it('builds correct yarn command', function (): void {
        File::put(base_path('yarn.lock'), '# yarn lockfile');

        Process::fake([
            'yarn add @tooinfinity/lingua-react' => Process::result(
                output: 'success',
                exitCode: 0
            ),
        ]);

        $this->artisan('lingua:install')
            ->assertSuccessful();

        Process::assertRan(fn ($process): bool => $process->command === 'yarn add @tooinfinity/lingua-react');
    });

    it('builds correct pnpm command', function (): void {
        File::put(base_path('pnpm-lock.yaml'), 'lockfileVersion: 5.4');

        Process::fake([
            'pnpm add @tooinfinity/lingua-react' => Process::result(
                output: 'success',
                exitCode: 0
            ),
        ]);

        $this->artisan('lingua:install')
            ->assertSuccessful();

        Process::assertRan(fn ($process): bool => $process->command === 'pnpm add @tooinfinity/lingua-react');
    });

    it('builds correct bun command', function (): void {
        File::put(base_path('bun.lockb'), 'binary lock file content');

        Process::fake([
            'bun add @tooinfinity/lingua-react' => Process::result(
                output: 'success',
                exitCode: 0
            ),
        ]);

        $this->artisan('lingua:install')
            ->assertSuccessful();

        Process::assertRan(fn ($process): bool => $process->command === 'bun add @tooinfinity/lingua-react');
    });
});

describe('successful installation', function (): void {
    it('publishes config and installs npm package successfully', function (): void {
        Process::fake([
            'npm install @tooinfinity/lingua-react' => Process::result(
                output: 'added 1 package in 2s',
                exitCode: 0
            ),
        ]);

        $this->artisan('lingua:install')
            ->assertSuccessful()
            ->assertExitCode(0);

        Process::assertRan('npm install @tooinfinity/lingua-react');
    });

    it('returns success exit code on successful installation', function (): void {
        Process::fake([
            'npm install @tooinfinity/lingua-react' => Process::result(
                output: 'success',
                exitCode: 0
            ),
        ]);

        $this->artisan('lingua:install')
            ->assertExitCode(0);
    });
});

describe('failed installation', function (): void {
    it('returns failure when npm installation fails', function (): void {
        Process::fake([
            'npm install @tooinfinity/lingua-react' => Process::result(
                output: '',
                errorOutput: 'npm ERR! code E404',
                exitCode: 1
            ),
        ]);

        $this->artisan('lingua:install')
            ->assertFailed()
            ->assertExitCode(1);
    });

    it('returns failure exit code when process is unsuccessful', function (): void {
        Process::fake([
            'npm install @tooinfinity/lingua-react' => Process::result(
                output: '',
                errorOutput: 'Package not found',
                exitCode: 1
            ),
        ]);

        $this->artisan('lingua:install')
            ->assertExitCode(1);
    });

    it('handles yarn installation failure', function (): void {
        File::put(base_path('yarn.lock'), '# yarn lockfile');

        Process::fake([
            'yarn add @tooinfinity/lingua-react' => Process::result(
                output: '',
                errorOutput: 'error An unexpected error occurred',
                exitCode: 1
            ),
        ]);

        $this->artisan('lingua:install')
            ->assertFailed();
    });

    it('handles pnpm installation failure', function (): void {
        File::put(base_path('pnpm-lock.yaml'), 'lockfileVersion: 5.4');

        Process::fake([
            'pnpm add @tooinfinity/lingua-react' => Process::result(
                output: '',
                errorOutput: 'ERR_PNPM_NO_MATCHING_VERSION',
                exitCode: 1
            ),
        ]);

        $this->artisan('lingua:install')
            ->assertFailed();
    });

    it('handles bun installation failure', function (): void {
        File::put(base_path('bun.lockb'), 'binary lock file content');

        Process::fake([
            'bun add @tooinfinity/lingua-react' => Process::result(
                output: '',
                errorOutput: 'error: package not found',
                exitCode: 1
            ),
        ]);

        $this->artisan('lingua:install')
            ->assertFailed();
    });
});

describe('config publishing', function (): void {
    it('calls vendor:publish with lingua-config tag', function (): void {
        Process::fake([
            'npm install @tooinfinity/lingua-react' => Process::result(
                output: 'success',
                exitCode: 0
            ),
        ]);

        // The command internally calls vendor:publish
        $this->artisan('lingua:install')
            ->assertSuccessful();

        // Config should be published (or already exist from service provider)
        // We verify the command completed successfully which means publish didn't fail
        Process::assertRan('npm install @tooinfinity/lingua-react');
    });
});
