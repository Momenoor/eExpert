<?php

namespace App\Services\PMS;

use App\Enums\PMS\ConditionSection;
use App\Enums\PMS\LeasePartyRole;
use App\Enums\PMS\PrintDocumentType;
use App\Enums\PMS\PropertyClassification;
use App\Models\ConditionTemplateItem;
use App\Models\Installment;
use App\Models\Lease;
use App\Models\OwnerGroup;
use App\Models\OwnerProfile;
use App\Models\Party;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
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
                'number_of_occupants' => 'No. of Occupants',
                'contract_type_mark_residential' => 'Contract Type Mark — Residential',
                'contract_type_mark_commercial' => 'Contract Type Mark — Commercial',
                'contract_type_mark_industrial' => 'Contract Type Mark — Industrial',
            ],
            'Lessor / Owner' => [
                'owner_name' => 'Owner Name',
                'landlord_name' => 'Landlord Name',
                'lessor_primary_owner_name' => 'Lessor Name (Primary Owner)',
                'lessor_group_name' => 'Lessor Name (Owner Group)',
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
            'Tax Invoice' => [
                'invoice_number' => 'Invoice No.',
                'invoice_due_date' => 'Due Date',
                'invoice_date_of_supply' => 'Date of Supply',
                'invoice_net_amount' => 'Net Amount',
                'invoice_vat_rate' => 'VAT Rate',
                'invoice_vat_amount' => 'VAT Amount',
                'invoice_total_amount' => 'Total Amount',
                'invoice_landlord_trn' => 'Landlord TRN',
                'invoice_tenant_trn' => 'Tenant TRN',
                'invoice_payment_status' => 'Payment Status',
                'payment_description' => 'Payment Description',
            ],
            'Receivable Receipt' => [
                'receipt_number' => 'Receipt No.',
                'receipt_date' => 'Receipt Date',
                'total_receivable_amount' => 'Total Receivable Amount',
                'number_of_installments' => 'No. of Instalments',
                'installments_table' => 'Instalments Table',
                'payment_description' => 'Payment Description',
            ],
        ];
    }

    /**
     * `installments_table` renders as an actual table (see
     * `partials/template-pages.blade.php`), not a plain string value —
     * the only field the builder's box size controls the height of a
     * whole table, not a run of text.
     */
    public static function isTable(string $fieldKey): bool
    {
        return $fieldKey === 'installments_table';
    }

    /**
     * The `installments_table` field's own columns, in print order — the
     * builder lets the office set each one's width as a percentage of the
     * table's own box width.
     *
     * @return array<string, string> [column key => column label]
     */
    public static function installmentsTableColumns(): array
    {
        return [
            'due_date' => __('Due Date'),
            'net_amount' => __('Net'),
            'vat_amount' => __('VAT'),
            'total_due_amount' => __('Total'),
            'payment_status' => __('Status'),
        ];
    }

    /**
     * Turns the field's saved (possibly partial) column widths into a
     * complete set summing to 100%: any column the office left blank
     * shares the width left over from the ones it did set, split evenly.
     * A hidden column is dropped entirely — it gets no width and no cell.
     *
     * @param  array<string, float|int|string|null>|null  $columnWidths
     * @param  list<string>|null  $hiddenColumns
     * @return array<string, float> [column key => width percent]
     */
    public static function resolveInstallmentsTableColumnWidths(?array $columnWidths, ?array $hiddenColumns = null): array
    {
        $keys = array_diff(array_keys(self::installmentsTableColumns()), $hiddenColumns ?? []);
        $explicit = array_filter(
            $columnWidths ?? [],
            fn (mixed $value, string $key): bool => in_array($key, $keys, true) && filled($value),
            ARRAY_FILTER_USE_BOTH,
        );

        $explicitSum = array_sum($explicit);
        $remainingKeys = array_diff($keys, array_keys($explicit));
        $remainingShare = $remainingKeys === [] ? 0.0 : max(0, 100 - $explicitSum) / count($remainingKeys);

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = array_key_exists($key, $explicit) ? (float) $explicit[$key] : $remainingShare;
        }

        return $result;
    }

    /**
     * The columns actually printed, in order, with their labels resolved
     * in the field's own placed language exactly like any other
     * localizable field — not always the app's current language — so a
     * copy of the table placed as Arabic prints Arabic headers regardless
     * of what locale the office happens to be browsing in.
     *
     * @param  list<string>|null  $hiddenColumns
     * @param  'ar'|'en'|null  $language
     * @return array<string, string> [column key => column label]
     */
    public static function visibleInstallmentsTableColumns(?array $hiddenColumns, ?string $language = null): array
    {
        $visible = fn (): array => array_diff_key(self::installmentsTableColumns(), array_flip($hiddenColumns ?? []));

        if (! in_array($language, ['ar', 'en'], true)) {
            return $visible();
        }

        $previous = app()->getLocale();
        app()->setLocale($language);

        try {
            return $visible();
        } finally {
            app()->setLocale($previous);
        }
    }

    /**
     * One instalment's own value for one of the table's columns.
     */
    public static function installmentColumnValue(Installment $installment, string $column): string
    {
        return match ($column) {
            'due_date' => $installment->getAttribute('due_date')?->format('d/m/Y') ?? '',
            'net_amount' => number_format((float) $installment->getAttribute('net_amount'), 2),
            'vat_amount' => number_format((float) $installment->getAttribute('vat_amount'), 2),
            'total_due_amount' => number_format((float) $installment->getAttribute('total_due_amount'), 2),
            'payment_status' => $installment->getAttribute('payment_status')?->getLabel() ?? '',
            default => '',
        };
    }

    /**
     * The field picker offered by the builder, filtered to what actually
     * makes sense for the template's own document type — a Tax Invoice
     * has no use for Special Conditions, and a lease contract has no use
     * for an invoice number.
     *
     * @return array<string, array<string, string>>
     */
    public static function availableFieldsFor(PrintDocumentType $documentType): array
    {
        $all = self::availableFields();
        $contractNumberOnly = ['Contract' => ['government_contract_number' => $all['Contract']['government_contract_number']]];

        return match ($documentType) {
            PrintDocumentType::LEASE_CONTRACT => array_diff_key($all, array_flip(['Tax Invoice', 'Receivable Receipt'])),
            PrintDocumentType::TAX_INVOICE => array_intersect_key($all, array_flip([
                'Tax Invoice', 'Lessor / Owner', 'Tenant', 'Property', 'Unit',
            ])) + $contractNumberOnly,
            PrintDocumentType::RECEIVABLE_RECEIPT => array_intersect_key($all, array_flip([
                'Receivable Receipt', 'Lessor / Owner', 'Tenant', 'Property', 'Unit',
            ])) + $contractNumberOnly,
        };
    }

    /**
     * Fields whose printed value is a translatable word or phrase (an enum
     * label, Yes/No, "1 Year") rather than a name, number or date. Only
     * these carry a per-field language in the builder — placing the same
     * field twice, once as Arabic and once as English, is how a bilingual
     * form gets both.
     *
     * @var list<string>
     */
    private const LOCALIZABLE_FIELDS = [
        'contract_category',
        'contract_type',
        'payment_method',
        'multiple_rent_amount',
        'allow_multiple_licenses',
        'rent_duration',
        'grace_period',
        'property_emirate',
        'property_type',
        'unit_type',
        'lessor_primary_owner_name',
        'invoice_payment_status',
        'installments_table',
        'payment_description',
    ];

    public static function isLocalizable(string $fieldKey): bool
    {
        return in_array($fieldKey, self::LOCALIZABLE_FIELDS, true);
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

    /**
     * The builder's marker/list text: the field's label, tagged with its
     * language when it prints in a specific one — that tag is what tells
     * two placements of the same field (Arabic and English) apart.
     */
    public static function labelWithLanguage(string $fieldKey, ?string $language): string
    {
        $label = self::label($fieldKey);

        if (! self::isLocalizable($fieldKey) || ! in_array($language, ['ar', 'en'], true)) {
            return $label;
        }

        return $label.' ('.strtoupper($language).')';
    }

    public static function groupLabel(string $group): string
    {
        return __($group);
    }

    /**
     * @param  'ar'|'en'|null  $language  null keeps the app's current language
     * @param  Installment|null  $installment  required to resolve a "Tax Invoice" field — a
     *                                         receipt/contract field ignores it and reads from `$lease` directly.
     */
    public function resolve(Lease $lease, string $fieldKey, ?string $language = null, ?Installment $installment = null): ?string
    {
        if ($language === null || ! self::isLocalizable($fieldKey) || ! in_array($language, ['ar', 'en'], true)) {
            return $this->resolveValue($lease, $fieldKey, $installment);
        }

        $previous = app()->getLocale();
        app()->setLocale($language);

        try {
            return $this->resolveValue($lease, $fieldKey, $installment);
        } finally {
            app()->setLocale($previous);
        }
    }

    private function resolveValue(Lease $lease, string $fieldKey, ?Installment $installment = null): ?string
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
            'multiple_rent_amount' => $lease->getAttribute('multiple_rent_amount')?->getLabel(),
            'security_deposit_amount' => $this->formatMoney($lease->getAttribute('security_deposit_amount')),
            'contract_type' => $lease->getAttribute('contract_type')?->getLabel(),
            'payment_method' => $lease->getAttribute('payment_method')?->getLabel(),
            'number_of_payments' => (string) $lease->getAttribute('number_of_payments'),
            'allow_multiple_licenses' => $lease->getAttribute('allow_multiple_licenses') ? __('Yes') : __('No'),
            'number_of_occupants' => (string) $lease->getAttribute('number_of_occupants'),
            'contract_type_mark_residential' => $this->contractTypeMark($unit, [PropertyClassification::RESIDENTIAL, PropertyClassification::MIXED_USE]),
            'contract_type_mark_commercial' => $this->contractTypeMark($unit, [PropertyClassification::COMMERCIAL, PropertyClassification::MIXED_USE]),
            'contract_type_mark_industrial' => $this->contractTypeMark($unit, [PropertyClassification::INDUSTRIAL]),

            'owner_name' => $property?->landlordName(),
            'landlord_name' => $lease->getAttribute('poa_name') ?: $property?->landlordName(),
            'lessor_primary_owner_name' => $this->lessorPrimaryOwnerName($property),
            'lessor_group_name' => $this->ownerGroup($property)?->name,
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

            'invoice_number' => $installment?->getAttribute('tax_invoice_serial'),
            'invoice_due_date' => $this->formatDate($installment?->getAttribute('due_date')),
            'invoice_date_of_supply' => $this->formatDate($installment?->getAttribute('date_of_supply')),
            'invoice_net_amount' => $this->formatMoney($installment?->getAttribute('net_amount')),
            'invoice_vat_rate' => $installment !== null ? number_format((float) $installment->getAttribute('vat_rate') * 100, 2).'%' : null,
            'invoice_vat_amount' => $this->formatMoney($installment?->getAttribute('vat_amount')),
            'invoice_total_amount' => $this->formatMoney($installment?->getAttribute('total_due_amount')),
            'invoice_landlord_trn' => $installment?->getAttribute('landlord_trn'),
            'invoice_tenant_trn' => $installment?->getAttribute('tenant_trn'),
            'invoice_payment_status' => $installment?->getAttribute('payment_status')?->getLabel(),

            'receipt_number' => 'RCP-'.$lease->getKey(),
            'receipt_date' => $this->formatDate(now()),
            'total_receivable_amount' => $this->formatMoney($lease->installments->sum('total_due_amount')),
            'number_of_installments' => (string) $lease->installments->count(),
            'payment_description' => $this->paymentDescription($lease, $unit, $property),

            default => null,
        };
    }

    /**
     * The group every owner on this property shares, if any — the
     * property's own declared group (used for its bank account) takes
     * precedence; falls back to deriving it from the owners themselves for
     * a property never migrated onto that direct link.
     */
    private function ownerGroup(?Property $property): ?OwnerGroup
    {
        return $property?->getAttribute('ownerGroup') ?? $property?->commonOwnerGroup();
    }

    /**
     * The profile whose EID/unified-number/nationality print for this
     * property's lessor — the group's designated primary owner when one
     * exists, otherwise whichever owner happens to be first (an ungrouped
     * property has no "primary" concept of its own to pick from).
     */
    private function ownerProfile(?Property $property): ?OwnerProfile
    {
        return $this->ownerGroup($property)?->primaryProfile()
            ?? $property?->owners->first()?->getAttribute('ownerProfile');
    }

    private function lessorParty(?Property $property): ?Party
    {
        $firstOwner = $property?->owners->first();
        $ownerGroup = $this->ownerGroup($property);

        return $ownerGroup?->party ?? $firstOwner;
    }

    /**
     * The primary owner's own personal name, tagged "(Heirs/Partners)" when
     * the estate/co-ownership has more than one member — an explicit
     * alternative to the group's own name (`lessor_group_name`), for a
     * government form that expects a person's name on the "Lessor" line
     * rather than an estate's collective name.
     */
    private function lessorPrimaryOwnerName(?Property $property): ?string
    {
        if ($property === null) {
            return null;
        }

        $group = $this->ownerGroup($property);
        $memberCount = $group?->ownerProfiles()->count() ?? $property->owners->count();

        $name = $group?->primaryProfile()?->getAttribute('party')?->name
            ?? $property->owners->first()?->name
            ?? $group?->name;

        if ($name === null) {
            return null;
        }

        return $memberCount > 1 ? $name.' ('.__('Heirs/Partners').')' : $name;
    }

    private function contractTypeMark(?Unit $unit, array $classifications): ?string
    {
        return $unit !== null && in_array($unit->getAttribute('property_classification'), $classifications, true)
            ? 'X'
            : null;
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

    /**
     * The fixed sentence printed on both the Tax Invoice and Receivable
     * Receipt identifying what the payment is for — the unit, its
     * building, and the lease's own rental period.
     */
    private function paymentDescription(Lease $lease, ?Unit $unit, ?Property $property): ?string
    {
        if ($unit === null || $property === null) {
            return null;
        }

        return __('Rental Payment for unit :unit - Building :building for period from :start until :end', [
            'unit' => $unit->getAttribute('unit_number'),
            'building' => $property->getAttribute('name'),
            'start' => $this->formatDate($lease->getAttribute('start_date')),
            'end' => $this->formatDate($lease->getAttribute('end_date')),
        ]);
    }

    private function formatGracePeriod(Lease $lease): ?string
    {
        $days = (int) $lease->getAttribute('grace_period_days');

        return $days > 0 ? __(':days days', ['days' => $days]) : null;
    }
}
