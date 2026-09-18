<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\LeasePrintTemplate;
use App\Services\PMS\LeasePrintFieldResolver;
use Illuminate\Contracts\View\View;

class LeasePrintController extends Controller
{
    public function __invoke(Lease $lease, string $format): View
    {
        abort_unless(auth()->user()?->can('view', $lease), 403);

        $template = LeasePrintTemplate::with('pages.fields')
            ->where('contract_format', $format)
            ->first();

        return view('filament.pms.leases.print', [
            'lease' => $lease->load([
                'units.property.owners.ownerProfile.ownerGroup',
                'leaseParties.party.tenant',
                'conditionTemplate.items',
            ]),
            'template' => $template,
            'resolver' => app(LeasePrintFieldResolver::class),
        ]);
    }
}
