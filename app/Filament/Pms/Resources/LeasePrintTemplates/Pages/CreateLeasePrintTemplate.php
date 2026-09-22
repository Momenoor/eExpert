<?php

namespace App\Filament\Pms\Resources\LeasePrintTemplates\Pages;

use App\Enums\PMS\PrintDocumentType;
use App\Filament\Pms\Resources\LeasePrintTemplates\LeasePrintTemplateResource;
use App\Models\LeasePrintTemplate;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateLeasePrintTemplate extends CreateRecord
{
    protected static string $resource = LeasePrintTemplateResource::class;

    /**
     * An owner-group-scoped template (Tax Invoice/Receivable Receipt) has
     * no real "contract format" of its own — `contract_format` stays
     * unique/not-null regardless, so it gets a synthetic value instead of
     * a schema change to accommodate a second, differently-scoped kind of
     * template in the same table.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $documentType = $data['document_type'] instanceof PrintDocumentType
            ? $data['document_type']
            : PrintDocumentType::from($data['document_type']);

        if (! $documentType->isOwnerGroupScoped()) {
            return $data;
        }

        $alreadyExists = LeasePrintTemplate::where('owner_group_id', $data['owner_group_id'])
            ->where('document_type', $documentType->value)
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'data.owner_group_id' => __('This group already has a :type template.', ['type' => $documentType->getLabel()]),
            ]);
        }

        $data['contract_format'] = LeasePrintTemplate::syntheticFormatFor((int) $data['owner_group_id'], $documentType);

        return $data;
    }
}
