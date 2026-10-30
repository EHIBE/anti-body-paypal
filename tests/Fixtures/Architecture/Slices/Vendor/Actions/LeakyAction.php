<?php

declare(strict_types=1);

namespace Tests\Fixtures\Architecture\Slices\Vendor\Actions;

use Tests\Fixtures\Architecture\Slices\Invoice\Models\Invoice;

final readonly class LeakyAction
{
    public function handle(Invoice $invoice): Invoice
    {
        return $invoice;
    }
}
