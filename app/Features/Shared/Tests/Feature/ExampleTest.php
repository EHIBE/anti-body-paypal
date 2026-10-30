<?php

declare(strict_types=1);

use function Pest\Laravel\get;

test('the application returns a successful response', function (): void {
    get('/')->assertOk();
});
