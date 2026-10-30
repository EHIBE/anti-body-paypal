<?php

declare(strict_types=1);

namespace Tests\Fixtures\Architecture\Features\Broken\Actions;

class MakeThing
{
    public function handle(): void {}

    public function undo(): void {}
}
