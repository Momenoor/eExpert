<?php

namespace App\Filament\Actions\Request;

use App\Helpers\FileUploadHelper;
use App\Services\Requests\RequestServiceFactory;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class RejectRequestAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'reject_request';
    }

    public function setUp(): void
    {
        parent::setUp();
        $this
            ->label(__('Reject'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn ($record) => RequestServiceFactory::make($record)->canBeRejected(auth()->user()))
            ->modalHeading(__('Reject Request'))
            ->successNotificationTitle(__('Request rejected successfully.'))
            ->action(fn ($record, array $data, $component) => RequestServiceFactory::make($record)->reject(data: $data, component: $component));
    }

    public function getSchema(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('approved_comment')->label(__('Reviewer Comment'))->required()->rows(2),
            Repeater::make('attachments')
                ->label(__('Attachments'))
                ->schema([
                    FileUpload::make('path')
                        ->label(__('File'))
                        ->disk('public')
                        ->directory('requests-attachments')
                        ->required()
                        ->preserveFilenames()
                        ->getUploadedFileNameForStorageUsing(fn ($file) => FileUploadHelper::getUniqueFilename($file, 'requests-attachments')),
                ])
                ->lazy()
                ->defaultItems(fn ($record) => RequestServiceFactory::classFor($record->type)::rejectionRequiresAttachments() ? 1 : 0)
                ->required(fn ($record) => RequestServiceFactory::classFor($record->type)::rejectionRequiresAttachments())
                ->collapsible(),
        ]);
    }
}
