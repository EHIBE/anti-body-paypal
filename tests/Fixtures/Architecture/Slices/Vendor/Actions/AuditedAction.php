<?php

declare(strict_types=1);

namespace Tests\Fixtures\Architecture\Slices\Vendor\Actions;

use Tests\Fixtures\Architecture\Slices\Audit\Actions\RecordThingAction;

final readonly class AuditedAction
{
    public function __construct(private RecordThingAction $recordThing) {}

    public function handle(): string
    {
        return $this->recordThing->handle('vendor.changed');
    }
}
