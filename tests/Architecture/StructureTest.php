<?php

declare(strict_types=1);

use Tests\Architecture\ClassRules;
use Tests\Architecture\SliceClasses;

function architectureFixtureRoot(): string
{
    return __DIR__.'/../Fixtures/Architecture/Features';
}

function architectureFixtureNamespace(): string
{
    return 'Tests\\Fixtures\\Architecture\\Features';
}

it('keeps every action final, named Action, with one public handle method', function (): void {
    expect(ClassRules::actions(SliceClasses::actions()))->toBe([]);
});

it('keeps every data object a final readonly class', function (): void {
    expect(ClassRules::dataObjects(SliceClasses::dataObjects()))->toBe([]);
});

it('keeps every slice enum string-backed', function (): void {
    expect(ClassRules::enums(SliceClasses::enums()))->toBe([]);
});

it('keeps every concrete slice class final', function (): void {
    expect(ClassRules::finalClasses(SliceClasses::everySliceClass()))->toBe([]);
});

it('keeps the horizontal layer folders free of code', function (): void {
    expect(ClassRules::horizontalFolders(dirname(__DIR__, 2).'/app'))->toBe([]);
});

it('reports an action that is not final, not named Action and has two public methods', function (): void {
    $actions = SliceClasses::actions(architectureFixtureRoot(), architectureFixtureNamespace());
    $broken = 'Tests\\Fixtures\\Architecture\\Features\\Broken\\Actions\\';

    expect(ClassRules::actions($actions))->toBe([
        "{$broken}ActionContract is not a concrete class and does not belong in Actions",
        "{$broken}MakeThing must end in Action",
        "{$broken}MakeThing must be final",
        "{$broken}MakeThing must have exactly one public method, handle",
    ]);
});

it('reports a data object that is not readonly and accepts a final readonly one', function (): void {
    $dataObjects = SliceClasses::dataObjects(architectureFixtureRoot(), architectureFixtureNamespace());

    expect(ClassRules::dataObjects($dataObjects))->toBe([
        'Tests\\Fixtures\\Architecture\\Features\\Broken\\Data\\LooseData must be a final readonly class',
    ]);
});

it('reports an int-backed and a pure enum and accepts a string-backed one', function (): void {
    $enums = SliceClasses::enums(architectureFixtureRoot(), architectureFixtureNamespace());

    expect(ClassRules::enums($enums))->toBe([
        'Tests\\Fixtures\\Architecture\\Features\\Broken\\Enums\\NumberStatus must be a string-backed enum',
        'Tests\\Fixtures\\Architecture\\Features\\Broken\\Enums\\PlainStatus must be a string-backed enum',
    ]);
});

it('reports a concrete class that is not final and skips abstract classes and slice tests', function (): void {
    $classes = SliceClasses::everySliceClass(architectureFixtureRoot(), architectureFixtureNamespace());

    expect(ClassRules::finalClasses($classes))->toBe([
        'Tests\\Fixtures\\Architecture\\Features\\Broken\\Actions\\MakeThing must be final',
        'Tests\\Fixtures\\Architecture\\Features\\Broken\\Support\\OpenHelper must be final',
    ]);
});

it('reports code in a horizontal layer folder and allows only the base controller and the user model', function (): void {
    expect(ClassRules::horizontalFolders(__DIR__.'/../Fixtures/Architecture/App'))->toBe([
        'app/Services must not hold code: LegacyService.php',
        'app/Actions must not hold code: PayAction.php',
        'app/Http/Controllers may only hold Controller.php: InvoiceController.php',
        'app/Models may only hold User.php: Invoice.php',
    ]);
});

it('lists slice folders and returns no classes for a missing root', function (): void {
    expect(SliceClasses::sliceFolders(architectureFixtureRoot()))->toBe(['Broken', 'Clean'])
        ->and(SliceClasses::everySliceClass(architectureFixtureRoot().'/Missing'))->toBe([])
        ->and(SliceClasses::sliceFolders(architectureFixtureRoot().'/Missing'))->toBe([]);
});
