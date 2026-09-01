<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Matters\MatterResource;
use App\Models\MatterParty;
use Filament\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class AssistantMatterCountTableWidget extends TableWidget
{
    public ?int $selectedPartyId = null; // Store the ID

    public ?string $selectedAssistantName = null; // Still keep the name for the UI heading

    #[On('filterTableByAssistant')]
    public function filterByAssistant(int $partyId, string $assistantName): void
    {
        // Force set the properties before the re-render cycle
        $this->selectedPartyId = $partyId;
        $this->selectedAssistantName = $assistantName;

        // resetPage ensures we don't stay on page 5 of a 2-page filtered result
        $this->resetPage();
    }

    // Reset the filter
    public function resetFilter(): void
    {
        $this->reset(['selectedPartyId', 'selectedAssistantName']);
        $this->resetPage();
    }

    protected function getTableQuery(): Builder
    {
        // Start with the base relationship
        return MatterParty::query()
            ->where('matter_party.role', 'expert')
            ->where('matter_party.type', 'assistant')
            ->whereHas('party', fn ($q) => $q->whereJsonContains('role', ['role' => 'expert', 'type' => 'assistant'])
            )
            ->whereHas('matter', fn ($q) => $q->whereNotNull('distributed_at')
                ->whereNull('final_report_at')
                ->whereNull('final_report_memo_date'))
            ->with(['party', 'matter', 'matter.court', 'matter.type'])
            ->when($this->selectedPartyId, fn ($q) => $q->where('matter_party.party_id', $this->selectedPartyId)
            );

    }

    public function table(Table $table): Table
    {
        return $table
            // Add a dynamic heading so the user knows why the table changed
            ->heading(fn () => $this->selectedAssistantName
                ? __('Matters for: :name', ['name' => $this->selectedAssistantName])
                : __('All Assistant Matters')
            )
            ->columns([
                TextColumn::make('party.name')
                    ->label(__('Assistant'))
                    ->sortable(),
                TextColumn::make('matter.status')
                    ->label(__('Status')),
                TextColumn::make('matter.reference')
                    ->label(__('Matter'))
                    ->getStateUsing(fn ($record) => "{$record->matter?->year}/{$record->matter?->number}")
                    ->weight(FontWeight::Bold)
                    ->url(fn ($record) => $record->matter_id
                        ? MatterResource::getUrl('view', ['record' => $record->matter_id])
                        : null
                    )
                    ->openUrlInNewTab(),
                TextColumn::make('matter.distributed_at')
                    ->label(__('Distributed At'))
                    ->date(),
                TextColumn::make('matter.court.name')
                    ->label(__('Court'))
                    ->placeholder(__('—')),

                TextColumn::make('matter.type.name')
                    ->label(__('Type'))
                    ->badge()
                    ->placeholder(__('—')),
            ])
            ->headerActions([
                Action::make('clearFilter')
                    ->label(__('Show All'))
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn () => filled($this->selectedPartyId))
                    // ✅ action closure must call $this method, not inline reset
                    ->action(fn () => $this->resetFilter()),
            ]);
    }
}
