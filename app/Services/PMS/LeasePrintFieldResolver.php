<?php

namespace App\Services\PMS;

use App\Enums\PMS\ConditionSection;
use App\Enums\PMS\LeasePartyRole;
use App\Models\ConditionTemplateItem;
use App\Models\Lease;
use App\Models\OwnerProfile;
use App\Models\Party;
use App\Models\Property;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Every data value a print template's fields can be positioned to show —
 * the single place this logic lives, instead of duplicated per contract
 * format the way the old full-HTML print views each had their own copy.
 * `availableFields()` drives the click-to-place builder's field picker;
 * `resolve()` renders the actual value at print time.
 */
class LeasePrintFieldResolver
{
    /**
     * @return array<string, array<string, string>> grouped as [group label => [field_key => field label]]
     */
    public static function availableFields(): array
    {
        return [
            'Contract' => [
                'government_contract_number' => 'Contract No.',
                'issue_date' => 'Issue Date',
                'start_date' => 'Start Date',
                'end_date' => 'End Date',
                'rent_duration' => 'Rent Duration',
                'contract_category' => 'Contract Category',
                'grace_period' => 'Grace Period',
                'total_base_rent' => 'Total Rent Amount / Contract Value',
                'annual_rent' => 'Annual Rent',
                'multiple_rent_amount' => 'Multiple Rent Amount',
                'security_deposit_amount' => 'Security Deposit Amount',
                'contract_type' => 'Contract Type',
                'payment_method' => 'Payment Method / Mode of Payment',
                'number_of_payments' => 'No. of Payments',
                'allow_multiple_licenses' => 'Allow Multiple Licenses',
                'designated_use' => 'Designated Use',
                'number_of_occupants' => 'No. of Occupants',
            ],
            'Lessor / Owner' => [
                'owner_name' => 'Owner Name',
                'landlord_name' => 'Landlord Name',
                'lessor_identification_number' => 'Lessor EID No. / Trade License No.',
                'lessor_unified_number' => 'Lessor Unified No.',
                'lessor_nationality' => 'Lessor Nationality',
                'lessor_mobile' => 'Lessor Mobile No.',
                'lessor_email' => 'Lessor Email',
                'number_of_lessors' => 'No. of Lessors',
            ],
            'Tenant' => [
                'tenant_name' => 'Tenant Name / Trade Name',
                'tenant_identification_number' => 'Tenant EID No. / Trade License No.',
                'tenant_unified_number' => 'Tenant Unified No.',
                'tenant_nationality' => 'Tenant Nationality',
                'tenant_mobile' => 'Tenant Mobile No.',
                'tenant_email' => 'Tenant Email',
            ],
            'Power of Attorney' => [
                'poa_authority_number' => 'Authority No.',
                'poa_identification_number' => 'EID No. / Trade License No.',
                'poa_unified_number' => 'Unified No.',
                'poa_name' => 'Name',
            ],
            'Property' => [
                'property_name' => 'Building / Property Name',
                'property_municipality' => 'Municipality',
                'property_emirate' => 'Emirate / City',
                'property_suburb' => 'Suburb',
                'property_area' => 'Area / Location',
                'property_title_deed_number' => 'Title Deed No.',
                'property_title_deed_date' => 'Title Deed Date',
                'property_plot_number' => 'Government No. / Plot No.',
                'property_type' => 'Property Type',
                'property_number' => 'Property No.',
            ],
            'Unit' => [
                'unit_number' => 'Unit No.',
                'unit_type' => 'Unit Type',
                'unit_area_sqm' => 'Area (Square Meter)',
                'unit_premise_number' => 'Premise No. (DEWA/SEWA)',
                'unit_number_of_rooms' => 'No. of Rooms',
            ],
            'Special Conditions' => [
                'special_conditions_en' => 'Special Conditions (English)',
                'special_conditions_ar' => 'Special Conditions (Arabic)',
            ],
        ];
    }

    public static function label(string $fieldKey): string
    {
        foreach (self::availableFields() as $fields) {
            if (isset($fields[$fieldKey])) {
                return __($fields[$fieldKey]);
            }
        }

        return $fieldKey;
    }

    public static function groupLabel(string $group): string
    {
        return __($group);
    }

    public function resolve(Lease $lease, string $fieldKey): ?string
    {
        $property = $lease->units->first()?->property;
        $unit = $lease->units->first();

        return match ($fieldKey) {
            'government_contract_number' => $lease->getAttribute('government_contract_number'),
            'issue_date' => $this->formatDate($lease->getAttribute('issue_date')),
            'start_date' => $this->formatDate($lease->getAttribute('start_date')),
            'end_date' => $this->formatDate($lease->getAttribute('end_date')),
            'rent_duration' => $lease->rentDuration(),
            'contract_category' => $lease->getAttribute('contract_category')?->getLabel(),
            'grace_period' => $this->formatGracePeriod($lease),
            'total_base_rent' => $this->formatMoney($lease->getAttribute('total_base_rent')),
            'annual_rent' => $this->formatMoney($lease->getAttribute('annual_rent')),
            'multiple_rent_amount' => $this->formatMoney($lease->getAttribute('multiple_rent_amount')),
            'security_deposit_amount' => $this->formatMoney($lease->getAttribute('security_deposit_amount')),
            'contract_type' => $lease->getAttribute('contract_type')?->getLabel(),
            'payment_method' => $lease->getAttribute('payment_method')?->getLabel(),
            'number_of_payments' => (string) $lease->getAttribute('number_of_payments'),
            'allow_multiple_licenses' => $lease->getAttribute('allow_multiple_licenses') ? 'Yes' : 'No',
            'designated_use' => $lease->getAttribute('designated_use')?->getLabel(),
            'number_of_occupants' => (string) $lease->getAttribute('number_of_occupants'),

            'owner_name' => $property?->landlordName(),
            'landlord_name' => $lease->getAttribute('poa_name') ?: $property?->landlordName(),
            'lessor_identification_number' => $this->ownerProfile($property)?->identification_number,
            'lessor_unified_number' => $this->ownerProfile($property)?->unified_number,
            'lessor_nationality' => $this->ownerProfile($property)?->nationality,
            'lessor_mobile' => $this->firstContact($this->lessorParty($property), 'phone'),
            'lessor_email' => $this->firstContact($this->lessorParty($property), 'email'),
            'number_of_lessors' => (string) $lease->numberOfLessors(),

            'tenant_name' => $this->tenantParty($lease)?->name,
            'tenant_identification_number' => $this->tenantProfile($lease)?->identification_number,
            'tenant_unified_number' => $this->tenantProfile($lease)?->unified_number,
            'tenant_nationality' => $this->tenantProfile($lease)?->nationality,
            'tenant_mobile' => $this->firstContact($this->tenantParty($lease), 'phone'),
            'tenant_email' => $this->firstContact($this->tenantParty($lease), 'email'),

            'poa_authority_number' => $lease->getAttribute('poa_authority_number'),
            'poa_identification_number' => $lease->getAttribute('poa_identification_number'),
            'poa_unified_number' => $lease->getAttribute('poa_unified_number'),
            'poa_name' => $lease->getAttribute('poa_name'),

            'property_name' => $property?->name,
            'property_municipality' => $property?->municipality,
            'property_emirate' => $property?->emirate?->getLabel(),
            'property_suburb' => $property?->suburb,
            'property_area' => $property?->area,
            'property_title_deed_number' => $property?->title_deed_number,
            'property_title_deed_date' => $this->formatDate($property?->title_deed_date),
            'property_plot_number' => $property?->plot_number,
            'property_type' => $property?->property_type?->getLabel(),
            'property_number' => $property?->property_number,

            'unit_number' => $unit?->unit_number,
            'unit_type' => $unit?->unit_type?->getLabel(),
            'unit_area_sqm' => $unit?->area_sqm ? number_format((float) $unit->area_sqm, 2) : null,
            'unit_premise_number' => $unit?->premise_number,
            'unit_number_of_rooms' => $unit?->number_of_rooms !== null ? (string) $unit->number_of_rooms : null,

            'special_conditions_en' => $this->specialConditions($lease)->pluck('text_en')->implode("\n"),
            'special_conditions_ar' => $this->specialConditions($lease)->pluck('text_ar')->implode("\n"),

            default => null,
        };
    }

    private function ownerProfile(?Property $property): ?OwnerProfile
    {
        return $property?->owners->first()?->getAttribute('ownerProfile');
    }

    private function lessorParty(?Property $property): ?Party
    {
        $firstOwner = $property?->owners->first();
        $ownerGroup = $this->ownerProfile($property)?->getAttribute('ownerGroup');

        return $ownerGroup?->party ?? $firstOwner;
    }

    private function tenantParty(Lease $lease): ?Party
    {
        return $lease->leaseParties->firstWhere('role', LeasePartyRole::PRIMARY_TENANT)?->party;
    }

    private function tenantProfile(Lease $lease): ?Tenant
    {
        return $this->tenantParty($lease)?->getAttribute('tenant');
    }

    private function firstContact(?Party $party, string $attribute): ?string
    {
        return ((array) ($party?->{$attribute} ?? []))[0] ?? null;
    }

    /**
     * @return Collection<int, ConditionTemplateItem>
     */
    private function specialConditions(Lease $lease): Collection
    {
        return $lease->conditionTemplate?->items->where('section', ConditionSection::SPECIAL) ?? collect();
    }

    private function formatDate(mixed $date): ?string
    {
        return $date?->format('d/m/Y');
    }

    private function formatMoney(mixed $amount): ?string
    {
        $amount = (float) $amount;

        return $amount > 0.0 ? number_format($amount, 2).' AED' : null;
    }

    private function formatGracePeriod(Lease $lease): ?string
    {
        $days = (int) $lease->getAttribute('grace_period_days');

        return $days > 0 ? __(':days days', ['days' => $days]) : null;
    }
}
