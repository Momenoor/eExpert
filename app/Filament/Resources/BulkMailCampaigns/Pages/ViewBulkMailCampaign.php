<?php

namespace App\Filament\Resources\BulkMailCampaigns\Pages;

use App\Enums\BulkMailCampaignStatus;
use App\Filament\Resources\BulkMailCampaigns\BulkMailCampaignResource;
use App\Filament\Resources\BulkMailCampaigns\Widgets\CampaignStatsWidget;
use App\Jobs\SendBulkMailBatch;
use App\Mail\BulkMailMessage;
use App\Models\BulkMailRecipient;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Mail;

class ViewBulkMailCampaign extends ViewRecord
{
    protected static string $resource = BulkMailCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('send_test')
                ->label(__('bulk_mail.actions.send_test'))
                ->icon('heroicon-o-paper-airplane')
                ->action(function ($record) {
                    $recipient = new BulkMailRecipient([
                        'email' => auth()->user()->email,
                        'name' => auth()->user()->name,
                    ]);
                    Mail::to(auth()->user()->email)
                        ->send(new BulkMailMessage($record, $recipient));

                    Notification::make()
                        ->success()
                        ->title(__('bulk_mail.notifications.test_sent'))
                        ->send();
                }),

            Actions\Action::make('start_campaign')
                ->label(__('bulk_mail.actions.start'))
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => in_array($this->record->status, [BulkMailCampaignStatus::Draft, BulkMailCampaignStatus::Paused]))
                ->action(function () {
                    $this->record->update(['status' => BulkMailCampaignStatus::Active]);
                    SendBulkMailBatch::dispatch($this->record->id);

                    Notification::make()
                        ->success()
                        ->title(__('bulk_mail.status.active'))
                        ->send();
                }),

            Actions\Action::make('pause_campaign')
                ->label(__('bulk_mail.actions.pause'))
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->visible(fn () => $this->record->status === BulkMailCampaignStatus::Active)
                ->action(function () {
                    $this->record->update(['status' => BulkMailCampaignStatus::Paused]);

                    Notification::make()
                        ->warning()
                        ->title(__('bulk_mail.status.paused'))
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CampaignStatsWidget::class,
        ];
    }
}
