<?php

namespace App\Filament\Resources\Matters\Pages;

use App\Enums\MatterCollectionStatus;
use App\Enums\MatterLevel;
use App\Filament\Actions\Calendar\SyncToOutlookAction;
use App\Filament\Resources\Matters\MatterResource;
use App\Helpers\FileUploadHelper;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ViewMatter extends ViewRecord
{
    protected static string $resource = MatterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SyncToOutlookAction::make(),
            Action::make('initial_report')
                ->label(__('Initial Report'))
                ->color(fn ($record) => $record->initial_report_at === null ? 'warning' : Color::Stone)
                ->visible(fn ($record) => auth()->user()->can('initialReport', $record))
                ->disabled(fn ($record) => $record->initial_report_at !== null)
                ->label(fn ($record) => $record->initial_report_at ? __('Initial Report Submitted') : __('Submit Initial Report'))
                ->schema([
                    FileUpload::make('screen_shot')
                        ->label(__('Screenshot'))
                        ->acceptedFileTypes(['image/*', 'application/pdf'])
                        ->disk('public')
                        ->directory('matter-attachments')
                        ->visibility('public')
                        ->required()
                        ->getUploadedFileNameForStorageUsing(fn ($file) => FileUploadHelper::getUniqueFilename($file, 'matter-attachments')),
                    DatePicker::make('date')
                        ->label(__('Date'))
                        ->visible(auth()->user()->can('UpdateInitialReportDate:Matter')),
                ])
                ->requiresConfirmation()
                ->action(fn ($record, array $data) => $this->initialReportSubmit($record, $data)),

            Action::make('final_report')
                ->label(fn ($record) => $record->final_report_at ? __('Final Report Submitted') : __('Submit Final Report'))
                ->color(fn ($record) => $record->final_report_at === null ? 'success' : Color::Stone)
                ->visible(fn ($record) => auth()->user()->can('finalReport', $record))
                ->disabled(fn ($record) => $record->final_report_at !== null || $record->final_report_memo_date == null)
                ->tooltip(fn ($record) => $record->final_report_memo_date == null ? __('Final Report must be approved first.') : null)
                ->schema([
                    DatePicker::make('date')
                        ->label(__('Date'))
                        ->visible(auth()->user()->can('UpdateFinalReportDate:Matter')),
                ])
                ->requiresConfirmation()
                ->action(fn ($record, array $data) => $this->finalReportSubmit($record, $data)),

            Action::make('clone')
                ->label(__('Supplementary Matter'))
                ->color('info')
                ->visible(fn ($record) => auth()->user()->can('replicate', $record))
                ->schema(fn ($record) => [
                    Section::make(__('Supplementary Matter Details'))
                        ->columns(2)
                        ->schema([
                            TextInput::make('number')
                                ->label(__('Number'))
                                ->default($record->number),
                            TextInput::make('year')
                                ->label(__('Year'))
                                ->default($record->year),
                            DatePicker::make('received_at')
                                ->label(__('Received Date'))
                                ->default(now()),
                            DatePicker::make('distributed_at')
                                ->label(__('Distributed Date'))
                                ->default(now()),
                            DatePicker::make('next_session_date')
                                ->label(__('Next Session Date'))
                                ->default(now()->addDays(7)),
                            Select::make('level')
                                ->label(__('Level'))
                                ->options(MatterLevel::class)
                                ->default($record->level)
                                ->required(),
                        ]),
                ])
                ->action(fn ($record, $data) => $this->cloneMatter($record, $data)),
            EditAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make()
                ->visible(fn ($record) => $record->trashed()),
            RestoreAction::make()
                ->visible(fn ($record) => $record->trashed()),
        ];
    }

    private function cloneMatter($record, $data): void
    {
        DB::transaction(callback: function () use ($record, $data) {
            // ── 1. Clone the matter itself ────────────────────────────────
            $newMatter = $record->replicate();

            // Clear all dates except received_date
            $newMatter->distributed_at = $data['distributed_at'];
            $newMatter->next_session_date = $data['next_session_date'];
            $newMatter->initial_report_at = null;
            $newMatter->final_report_at = null;
            $newMatter->received_at = $data['received_at'];
            $newMatter->number = $data['number'];
            $newMatter->year = $data['year'];
            $newMatter->level = $data['level'];

            // Reset status fields
            $newMatter->collection_status = MatterCollectionStatus::NO_FEES;
            $newMatter->parent_id = $record->id;

            $newMatter->save();

            // ── 2. Clone main parties (plaintiffs / defendants etc.) ───────
            $mainParties = $record->mainPartiesOnly()
                ->with('representatives')
                ->get();

            foreach ($mainParties as $mp) {
                $newMp = $mp->replicate();
                $newMp->matter_id = $newMatter->id;
                $newMp->save();

                // Clone representatives for this party
                foreach ($mp->representatives as $rep) {
                    $newRep = $rep->replicate();
                    $newRep->matter_id = $newMatter->id;
                    $newRep->parent_id = $newMp->id;
                    $newRep->save();
                }
            }

            // ── 3. Clone experts ──────────────────────────────────────────
            $experts = $record->expertsOnly()->get();

            foreach ($experts as $expert) {
                $newExpert = $expert->replicate();
                $newExpert->matter_id = $newMatter->id;
                $newExpert->save();
            }

            // ── 4. Fees are intentionally NOT cloned ──────────────────────

            Notification::make()
                ->success()
                ->title(__('Supplementary Matter cloned successfully'))
                ->body(__('Redirecting to the new matter...'))
                ->send();

            // Redirect to the new matter's view page
            $this->redirect(
                MatterResource::getUrl('view', ['record' => $newMatter->id])
            );
        });
    }

    private function finalReportSubmit($record, array $data): void
    {
        if (! $record->initial_report_at) {
            $record->initial_report_at = $data['date'] ?? now();
        }
        $record->final_report_at = $data['date'] ?? now();
        $record->saveQuietly();
    }

    private function initialReportSubmit($record, array $data): void
    {
        $path = $data['screen_shot'];
        $disk = Storage::disk('public');

        $record->initial_report_at = $data['date'] ?? now();
        $record->save();

        $record->attachments()->create([
            'user_id' => Auth::id(),
            'type' => 'initial_report_submission',
            'path' => $path,
            'name' => basename($path),
            'size' => $disk->exists($path) ? $disk->size($path) : 0,
            'extension' => pathinfo($path, PATHINFO_EXTENSION) ?? 0,
        ]);

        Notification::make('Initial Report Submitted')
            ->title(__('Initial Report Submitted'))
            ->success()
            ->send();
    }
}
