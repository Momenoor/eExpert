<?php

namespace App\Filament\Actions\Request;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Helpers\FileUploadHelper;
use App\Services\Requests\RequestServiceFactory;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class CreateRequestAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'add_request';
    }

    public function setUp(): void
    {
        parent::setUp();
        $this
            ->label(__('Add Request'))
            ->icon('heroicon-o-plus')
            ->visible(fn ($record) => auth()->user()->can('CreateMatterRequest:MatterRequest') || auth()->user()->can('CreateRequest:Matter'))
            ->modalHeading(__('Submit New Request'))
            ->successNotificationTitle(__('Request submitted successfully.'))
            ->action(function (array $data, $record, $component) {
                $type = $data['type'];
                $service = RequestServiceFactory::classFor($type);
                $prepared = $service::prepareForCreation($data, $record);

                $request = $record->requests()->create([
                    'request_by' => auth()->id(),
                    'type' => $type,
                    'status' => 'pending',
                    'comment' => $prepared['comment'],
                    'extra' => $prepared['extra'],
                ]);

                foreach ($data['attachments'] ?? [] as $item) {
                    $path = $item['path'];
                    $request->attachments()->create([
                        'name' => 'request-attachment-'.$request->id.'-'.basename($path),
                        'path' => $path,
                        'size' => Storage::disk('public')->size($path),
                        'extension' => pathinfo($path, PATHINFO_EXTENSION),
                        'type' => 'matter-request',
                        'matter_id' => $record->id,
                        'matter_request_id' => $request->id,
                        'user_id' => auth()->id(),
                    ]);
                }

                $requestService = RequestServiceFactory::make($request);
                $requestService->afterCreated();
                $requestService->onCreateNotify();
                $requestService->refresh($component);
            });
    }

    public function getSchema(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->label(__('Request Type'))
                ->options(RequestType::class)
                ->required()
                ->disableOptionWhen(fn (string $value, $record): bool => $record->requests()->where('type', $value)->whereNot('status', RequestStatus::REJECTED)->exists())
                ->live(),
            Group::make()
                ->schema(fn (Get $get) => $get('type')
                    ? RequestServiceFactory::classFor($get('type'))::createFormFields()
                    : [])
                ->columnSpanFull(),
            Textarea::make('comment')->label(__('Comment'))->required()->rows(3),
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
                ->defaultItems(fn (Get $get) => $get('type') && RequestServiceFactory::classFor($get('type'))::requiresAttachmentsOnCreate() ? 1 : 0)
                ->required(fn (Get $get) => $get('type') && RequestServiceFactory::classFor($get('type'))::requiresAttachmentsOnCreate())
                ->collapsible(),
        ]);
    }
}
