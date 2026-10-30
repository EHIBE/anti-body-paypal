<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;
use Tests\Architecture\SliceClasses;
use Tests\Architecture\SliceMap;
use Tests\Support\Repo;

foreach (SliceMap::slices() as $slice) {
    arch("{$slice} only depends on the slices the map allows")
        ->expect('App\\Features\\'.$slice)
        ->not->toUse(SliceMap::forbiddenNamespaces($slice));
}

it('maps exactly the planned slices', function (): void {
    $mapped = SliceMap::slices();
    $planned = SliceMap::PLANNED_SLICES;

    sort($mapped);
    sort($planned);

    expect($mapped)->toBe($planned);
});

it('maps every slice folder under app/Features', function (): void {
    expect(array_values(array_diff(SliceClasses::sliceFolders(), SliceMap::slices())))->toBe([]);
});

it('declares dependencies only on other planned slices', function (string $slice): void {
    $dependencies = SliceMap::allowedDependencies($slice);

    expect(array_values(array_diff($dependencies, SliceMap::PLANNED_SLICES)))->toBe([])
        ->and($dependencies)->not->toContain($slice);
})->with(SliceMap::PLANNED_SLICES);

it('forbids the internal namespaces of a declared dependency and every namespace of an undeclared one', function (): void {
    $forbidden = SliceMap::forbiddenNamespaces('Guard');

    expect($forbidden)->toContain(
        'App\\Features\\Vendor\\Models',
        'App\\Features\\Vendor\\Http',
        'App\\Features\\Invoice\\Actions',
        'App\\Features\\Invoice\\Models',
    );

    expect(array_values(array_intersect($forbidden, [
        'App\\Features\\Vendor\\Actions',
        'App\\Features\\Audit\\Actions',
        'App\\Features\\Guard\\Models',
    ])))->toBe([]);
});

it('never forbids the Shared slice and forbids Shared from every other slice', function (): void {
    foreach (SliceMap::slices() as $slice) {
        foreach (SliceMap::forbiddenNamespaces($slice) as $namespace) {
            expect(str_starts_with($namespace, 'App\\Features\\Shared'))->toBeFalse();
        }
    }

    $sharedForbidden = SliceMap::forbiddenNamespaces('Shared');

    expect($sharedForbidden)->toHaveCount(count(SliceMap::PLANNED_SLICES) - 1)
        ->toContain('App\\Features\\Payment')
        ->toContain('App\\Features\\Audit');
});

it('reports a slice class that imports the model of another slice', function (): void {
    $base = 'Tests\\Fixtures\\Architecture\\Slices';

    expect(function () use ($base): void {
        $expectation = expect($base.'\\Vendor\\Actions\\LeakyAction')->not->toUse(SliceMap::forbiddenNamespaces('Vendor', $base));

        unset($expectation);
    })->toThrow(ExpectationFailedException::class, "not to use '{$base}\\Invoice\\Models'");
});

it('accepts a slice class that calls an action of a declared dependency', function (): void {
    $base = 'Tests\\Fixtures\\Architecture\\Slices';

    expect(function () use ($base): void {
        $expectation = expect($base.'\\Vendor\\Actions\\AuditedAction')->not->toUse(SliceMap::forbiddenNamespaces('Vendor', $base));

        unset($expectation);
    })->not->toThrow(ExpectationFailedException::class);
});

it('classifies every folder under every slice as public or internal so that no folder escapes the isolation rules', function (): void {
    expect(SliceMap::unclassifiedFolders())->toBe([]);
});

it('reports a slice folder that is neither public nor internal', function (): void {
    $root = __DIR__.'/../Fixtures/Architecture/Features';

    expect(SliceMap::unclassifiedFolders($root, ['Actions']))->toBe(['Broken/Data', 'Broken/Enums', 'Broken/Support', 'Broken/Tests', 'Clean/Data', 'Clean/Enums', 'Clean/Support'])
        ->and(SliceMap::unclassifiedFolders($root))->toBe([]);
});

it('keeps the public and internal namespace lists disjoint', function (): void {
    expect(array_values(array_intersect(SliceMap::PUBLIC_NAMESPACES, SliceMap::INTERNAL_NAMESPACES)))->toBe([]);
});

it('matches the dependency edges in the slice catalog', function (): void {
    $catalog = Repo::read('docs/architecture/slice-catalog.md');
    $sections = preg_split('/^### 3\.\d+ /m', substr($catalog, (int) strpos($catalog, '## 3. Slices in detail')));
    $documented = [];

    foreach (array_slice($sections === false ? [] : $sections, 1) as $section) {
        $slice = trim((string) strtok($section, PHP_EOL));

        if (preg_match('/^\| Depends on \| (.+) \|$/m', $section, $row) !== 1) {
            continue;
        }

        $names = array_map(trim(...), explode(',', (string) preg_replace('/\s*\([^)]*\)/', '', $row[1])));
        $documented[$slice] = array_values(array_diff($names, ['Shared', 'nothing']));
        sort($documented[$slice]);
    }

    $mapped = [];

    foreach (SliceMap::slices() as $slice) {
        $mapped[$slice] = SliceMap::allowedDependencies($slice);
        sort($mapped[$slice]);
    }

    ksort($documented);
    ksort($mapped);

    expect($documented)->toHaveCount(count(SliceMap::PLANNED_SLICES))
        ->and($documented)->toBe($mapped);
});
