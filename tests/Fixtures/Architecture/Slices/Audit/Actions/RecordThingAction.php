<?php

declare(strict_types=1);

namespace Tests\Fixtures\Architecture\Slices\Audit\Actions;

final readonly class RecordThingAction
{
    public function handle(string $event): string
    {
        return $event;
    }
}
