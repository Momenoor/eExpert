<?php

namespace App\Http\Controllers;

use App\Enums\PMS\PrintDocumentType;
use App\Models\Lease;
use App\Models\LeasePrintTemplate;
use App\Models\OwnerGroup;
use App\Models\Property;
use App\Services\PMS\LeasePrintFieldResolver;
use Illuminate\Contracts\View\View;

/**
 * The Tax Invoice and Receivable Receipt handed to the tenant once a lease
 * exists — a renewal is just another `Lease` with its own instalments, so
 * printing these needs no extra step of its own. Each document renders on
 * the owning property's owner group's own letterhead template — see
 * `LeasePrintTemplate::forOwnerGroup()`.
 */
class LeaseFinancialDocumentsController extends Controller
{
    public function taxInvoices(Lease $lease): View
    {
        abort_unless(auth()->user()?->can('view', $lease), 403);

        $lease->load(['installments', 'units.property.owners.ownerProfile.ownerGroup', 'leaseParties.party.tenant']);

        return view('filament.pms.leases.tax-invoices', [
            'lease' => $lease,
            'template' => $this->groupTemplate($lease, PrintDocumentType::TAX_INVOICE),
            'resolver' => app(LeasePrintFieldResolver::class),
        ]);
    }

    public function receivableReceipt(Lease $lease): View
    {
        abort_unless(auth()->user()?->can('view', $lease), 403);

        $lease->load(['installments', 'units.property.owners.ownerProfile.ownerGroup', 'leaseParties.party.tenant']);

        return view('filament.pms.leases.receivable-receipt', [
            'lease' => $lease,
            'template' => $this->groupTemplate($lease, PrintDocumentType::RECEIVABLE_RECEIPT),
            'resolver' => app(LeasePrintFieldResolver::class),
        ]);
    }

    private function groupTemplate(Lease $lease, PrintDocumentType $documentType): ?LeasePrintTemplate
    {
        $property = $lease->units->first()?->property;
        $group = $this->ownerGroup($property);

        if ($group === null) {
            return null;
        }

        return LeasePrintTemplate::with('pages.fields')
            ->where('owner_group_id', $group->getKey())
            ->where('document_type', $documentType->value)
            ->first();
    }

    private function ownerGroup(?Property $property): ?OwnerGroup
    {
        return $property?->getAttribute('ownerGroup') ?? $property?->commonOwnerGroup();
    }
}
