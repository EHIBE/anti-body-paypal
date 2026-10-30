<?php

declare(strict_types=1);

use Tests\Support\Repo;
use Tests\Support\Tree;

/**
 * @return array<string, mixed>
 */
function toolingPhpstan(): array
{
    return Repo::yaml('phpstan.neon');
}

/**
 * @return list<array{line: int, previous: string, body: string}>
 */
function toolingPhpstanEntries(): array
{
    $lines = Repo::lines('phpstan.neon');
    $start = array_search('    ignoreErrors:', $lines, true);

    if (! is_int($start)) {
        return [];
    }

    $starts = [];
    $previous = [];
    $bodies = [];
    $current = -1;

    foreach (array_slice($lines, $start + 1, null, true) as $number => $line) {
        if (trim($line) !== '' && ! str_starts_with($line, '        ')) {
            break;
        }

        if (trim($line) === '-' && str_starts_with($line, '        -')) {
            $current++;
            $starts[$current] = $number + 1;
            $previous[$current] = trim($lines[$number - 1]);
            $bodies[$current] = '';

            continue;
        }

        if ($current >= 0) {
            $bodies[$current] .= $line.'
';
        }
    }

    $entries = [];

    foreach ($starts as $index => $line) {
        $entries[] = ['line' => $line, 'previous' => $previous[$index], 'body' => $bodies[$index]];
    }

    return $entries;
}

function toolingPhpunit(): SimpleXMLElement
{
    $xml = simplexml_load_string(Repo::read('phpunit.xml'));

    if ($xml === false) {
        throw new RuntimeException('phpunit.xml is not valid XML.');
    }

    return $xml;
}

/**
 * @return array<string, list<string>>
 */
function toolingPhpunitSuites(): array
{
    $suites = [];

    foreach (toolingPhpunit()->testsuites->testsuite as $suite) {
        foreach ($suite->directory as $directory) {
            $suites[(string) $suite['name']][] = (string) $directory;
        }
    }

    return $suites;
}

/**
 * @return array<string, string>
 */
function toolingSliceTestDirectories(): array
{
    $directories = glob(Repo::root().'/app/Features/*/Tests', GLOB_ONLYDIR);
    $slices = [];

    foreach ($directories === false ? [] : $directories as $directory) {
        $slices[basename(dirname($directory))] = 'app/Features/'.basename(dirname($directory)).'/Tests';
    }

    ksort($slices);

    return $slices;
}

it('analyses at level max', function (): void {
    expect(Tree::at(toolingPhpstan(), 'parameters.level'))->toBe('max');
});

it('includes the four extension files and each of them exists under vendor', function (): void {
    $includes = Tree::strings(toolingPhpstan()['includes'] ?? null);

    expect($includes)->toBe([
        'vendor/larastan/larastan/extension.neon',
        'vendor/nesbot/carbon/extension.neon',
        'vendor/phpstan/phpstan-strict-rules/rules.neon',
        'vendor/pestphp/pest/extension.neon',
    ]);

    $missing = array_values(array_filter($includes, static fn (string $include): bool => ! Repo::exists($include)));

    expect($missing)->toBe([]);
});

it('uses no baseline', function (): void {
    expect(preg_match('/baseline/i', Repo::read('phpstan.neon')))->toBe(0)
        ->and(glob(Repo::root().'/phpstan-baseline*'))->toBe([]);
});

it('explains every ignored error with a Reason comment and names an identifier and a path', function (): void {
    $entries = toolingPhpstanEntries();

    expect($entries)->not->toBe([]);

    $problems = [];

    foreach ($entries as $entry) {
        if (! str_starts_with($entry['previous'], '# Reason:')) {
            $problems[] = "phpstan.neon line {$entry['line']}: the ignoreErrors entry needs a # Reason: comment directly above it";
        }

        if (preg_match('/^\s+identifiers?:/m', $entry['body']) !== 1) {
            $problems[] = "phpstan.neon line {$entry['line']}: the ignoreErrors entry must name an identifier";
        }

        if (preg_match('/^\s+paths?:/m', $entry['body']) !== 1) {
            $problems[] = "phpstan.neon line {$entry['line']}: the ignoreErrors entry must name a path";
        }
    }

    expect($problems)->toBe([]);
});

it('stores the PHPStan cache under storage/framework/cache', function (): void {
    expect(Tree::text(Tree::at(toolingPhpstan(), 'parameters.tmpDir')))->toStartWith('storage/framework/cache/');
});

it('stores the Rector cache under storage/framework/cache/rector and skips applied migrations', function (): void {
    $rector = Repo::read('rector.php');

    expect($rector)->toContain("->withCache(cacheDirectory: __DIR__.'/storage/framework/cache/rector')")
        ->and($rector)->toMatch("#withSkip\\(\\[[^\\]]*__DIR__\\.'/database/migrations'#s");
});

it('uses the laravel Pint preset with strict types enforced', function (): void {
    $pint = Repo::json('pint.json');

    expect($pint['preset'] ?? null)->toBe('laravel')
        ->and(Tree::at($pint, 'rules.declare_strict_types'))->toBeTrue();
});

it('exempts exactly the three framework migrations from Pint and they still lack strict types', function (): void {
    $exempt = Tree::strings(Repo::json('pint.json')['notPath'] ?? null);

    expect($exempt)->toBe([
        'database/migrations/0001_01_01_000000_create_users_table.php',
        'database/migrations/0001_01_01_000001_create_cache_table.php',
        'database/migrations/0001_01_01_000002_create_jobs_table.php',
    ]);

    foreach ($exempt as $migration) {
        expect(Repo::read($migration))->not->toContain('strict_types');
    }
});

it('gives every slice test directory a testsuite of the same name that points at it', function (): void {
    $suites = toolingPhpunitSuites();
    $slices = toolingSliceTestDirectories();

    expect($slices)->not->toBe([]);

    foreach ($slices as $slice => $directory) {
        expect($suites[$slice] ?? [])->toBe([$directory]);
    }
});

it('excludes every slice test directory from code coverage source', function (): void {
    $excluded = [];

    foreach (toolingPhpunit()->source->exclude->directory as $directory) {
        $excluded[] = (string) $directory;
    }

    foreach (toolingSliceTestDirectories() as $directory) {
        expect($excluded)->toContain($directory);
    }
});

it('defines the Architecture and AccessMatrix testsuites', function (): void {
    $suites = toolingPhpunitSuites();

    expect($suites['Architecture'] ?? [])->toBe(['tests/Architecture'])
        ->and($suites['AccessMatrix'] ?? [])->toBe(['tests/AccessMatrix']);

    $missing = [];

    foreach ($suites as $directories) {
        foreach ($directories as $directory) {
            if (! is_dir(Repo::path($directory))) {
                $missing[] = $directory;
            }
        }
    }

    expect($missing)->toBe([]);
});

it('runs the tests on SQLite in memory', function (): void {
    $environment = [];

    foreach (toolingPhpunit()->php->env as $variable) {
        $environment[(string) $variable['name']] = (string) $variable['value'];
    }

    expect($environment['DB_CONNECTION'] ?? null)->toBe('sqlite')
        ->and($environment['DB_DATABASE'] ?? null)->toBe(':memory:');
});

it('binds the AccessMatrix directory and every slice Feature directory to the application test case', function (): void {
    $pest = Repo::read('tests/Pest.php');

    expect($pest)->toContain("'AccessMatrix'")
        ->and($pest)->toContain("'../app/Features/*/Tests/Feature'")
        ->and($pest)->toContain('->extend(TestCase::class)')
        ->and($pest)->toContain('->use(RefreshDatabase::class)');
});
