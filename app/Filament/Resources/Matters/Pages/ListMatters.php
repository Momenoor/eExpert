<?php

namespace App\Filament\Resources\Matters\Pages;

use App\Filament\Exports\AssistantMattersExporter;
use App\Filament\Exports\MatterExporter;
use App\Filament\Resources\Matters\MatterResource;
use App\Enums\MatterStatus;
use App\Models\Matter;
use App\Models\MatterParty;
use Filament\Actions\BulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class ListMatters extends ListRecords
{
    protected static string $resource = MatterResource::class;

    public function getDefaultActiveTab(): string|int|null
    {
        return 'in_progress';
    }


    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->label(__('All')),

            'in_progress' => Tab::make('in progress')
                ->label(__('In Progress'))
                ->badge(fn() => auth()->user()->can('ViewAny:Matter')
                    ? Matter::whereNull('initial_report_at')->withoutTrashed()->count()
                    : null
                )
                ->badgeColor(Color::Blue)
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNull('initial_report_at')->withoutTrashed()),

            'initial_prepared' => Tab::make('Initial Prepared')
                ->label(__('Initial Prepared'))
                ->badge(fn() => auth()->user()->can('ViewAny:Matter')
                    ? Matter::whereNotNull('initial_report_at')->whereNull('final_report_at')->withoutTrashed()->count()
                    : null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNotNull('initial_report_at')->withoutTrashed()->whereNull('final_report_at')),

            'final_submitted' => Tab::make('Final Submitted')
                ->label(__('Final Submitted'))
                ->badge(fn() => auth()->user()->can('ViewAny:Matter')
                    ? Matter::whereNotNull('initial_report_at')->whereNotNull('final_report_at')->withoutTrashed()->count()
                    : null)
                ->badgeColor('success')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNotNull('initial_report_at')->withoutTrashed()->whereNotNull('final_report_at')),

            'deleted' => Tab::make('Deleted')
                ->label(__('Deleted'))
                ->badgeColor('danger')
                ->icon('heroicon-o-trash')
                ->modifyQueryUsing(fn(Builder $query) => $query->onlyTrashed())
                ->visible(fn() => auth()->user()->can('ViewTrashed:Matter'))
                ->badge(fn() => auth()->user()->can('ViewAny:Matter')
                    ? Matter::onlyTrashed()->count()
                    : null),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make('all_matters')
                ->exporter(MatterExporter::class)
                ->label(__('Export All'))
                ->color('warning')
                ->fileDisk('public')
                ->columnMappingColumns(3)
                ->icon('heroicon-o-arrow-down-tray'),
            ExportAction::make('all_matters_by_assistant')
                ->exporter(AssistantMattersExporter::class)
                ->label(__('Export by Assistant'))
                ->color('warning')
                ->fileDisk('public')
                ->columnMappingColumns(3)
                ->icon('heroicon-o-users'),
            CreateAction::make(),

        ];
    }

    protected function getTableBulkActions(): array
    {
        return [
            DeleteBulkAction::make()
                ->visible(
                    fn() => auth()->user()->can('DeleteAny:Matter', Matter::class)
                        && $this->activeTab !== 'deleted'
                ),
            ForceDeleteBulkAction::make()
                ->visible(
                    fn() => auth()->user()->can('forceDeleteAny', Matter::class)
                        && $this->activeTab === 'deleted'
                ),
            RestoreBulkAction::make()
                ->visible(
                    fn() => auth()->user()->can('restoreAny', Matter::class)
                        && $this->activeTab === 'deleted'
                )
                ->defaultColor('success'),
            BulkAction::make('final_report_submission')
                ->label(__('Final Report Submission'))
                ->defaultColor('primary')
                ->icon('heroicon-o-document-text')
                ->visible(
                    fn() => auth()->user()->can('BulkUpdateFinalReportDate:Matter')
                )
                ->action(
                    function (Collection $records) {
                        $records->each(
                            fn($record) => !$record->hasFinalReport()
                                ? $record->finalReportSubmission()
                                : null
                        );
                        Notification::make()
                            ->success()
                            ->title(__('Final Report Submission'))
                            ->body(__('Final report submission finished for selected matters.'))
                            ->send();
                    }
                )
                ->requiresConfirmation(),
        ];
    }
}
