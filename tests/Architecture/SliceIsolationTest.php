<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;
use Tests\Architecture\SliceClasses;
use Tests\Architecture\SliceMap;

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
