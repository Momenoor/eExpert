<?php

namespace App\Filament\Resources\Matters\Schemas;

use App\Enums\FeeType;
use App\Enums\RequestStatus;
use App\Filament\Actions\Fee\CollectFeeAction;
use App\Filament\Actions\Request\ApproveRequestAction;
use App\Filament\Actions\Request\CreateRequestAction;
use App\Filament\Actions\Request\RejectRequestAction;
use App\Filament\Resources\Matters\MatterResource;
use App\Helpers\FileUploadHelper;
use App\Models\Type;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class MatterInfolist
{
    // ── Shared helpers ────────────────────────────────────────────────────────
    private static function refreshComponent($component): void
    {
        $component->getLivewire()->dispatch('$refresh');
    }

    private static function refreshRecord($component): void
    {
        $component->getLivewire()->getRecord()->refresh();
        static::refreshComponent($component);
    }

    private static function formatType(string $state): string
    {
        return ucfirst(str_replace('_', ' ', $state));
    }

    private static function partyTypeColor(string $state): string
    {
        return match ($state) {
            'plaintiff' => 'success',
            'defendant' => 'danger',
            'implicate-litigant' => 'warning',
            default => 'gray',
        };
    }

    private static function expertTypeColor(string $state): string
    {
        return match ($state) {
            'certified' => 'success',
            'assistant' => 'info',
            'external' => 'warning',
            default => 'gray',
        };
    }

    // ── Main configure ────────────────────────────────────────────────────────

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Grid::make(1)->columnSpan(1)->schema([
                    static::identitySection(),
                    static::classificationSection(),
                    static::datesSection(),
                    static::requestsSection(),
                    static::notesSection(),
                ]),
                Grid::make(1)->columnSpan(1)->schema([
                    static::expertsSection(),
                    static::partiesSection(),
                    static::feesSection(),
                    static::incentiveSection(),
                    static::attachmentsSection(),
                ]),
            ]);
    }

    // ── Left column sections ──────────────────────────────────────────────────

    private static function identitySection(): Section
    {
        return Section::make(__('Basic Information'))
            ->icon('heroicon-o-hashtag')
            ->columns(2)
            ->schema([
                TextEntry::make('year')->label(__('Year')),
                TextEntry::make('number')->label(__('Number')),
                TextEntry::make('status')->label(__('Status'))
                    ->formatStateUsing(fn ($state) => $state->getLabel())
                    ->badge()->columnSpan(2),
                TextEntry::make('collection_status')->label(__('Collection'))->badge(),
                IconEntry::make('has_court_penalty')->label(__('Has Court Penalty'))->boolean(),
                TextEntry::make('commissioning')->label(__('Commissioning'))
                    ->formatStateUsing(fn ($state) => __($state->getLabel())),
                IconEntry::make('is_office_work')
                    ->label(__('Is Office Work'))
                    ->default(false)
                    ->boolean(),
                TextEntry::make('review_count')->label(__('Review Count')),
                IconEntry::make('has_substantive_changes')->label(__('Has Substantive Changes'))->boolean(),
                TextEntry::make('parent_id')
                    ->label(__('Parent Matter'))
                    ->numeric()
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state, $record) => $state
                        ? __('Matter')." #{$record->number}/{$record->year} [#{$state}]"
                        : null
                    )
                    ->url(fn ($state) => $state ? MatterResource::getUrl('view', ['record' => $state]) : null)
                    ->icon('heroicon-o-link')
                    ->color('primary')
                    ->iconPosition(IconPosition::After)
                    ->openUrlInNewTab(),
            ]);
    }

    private static function classificationSection(): Section
    {
        return Section::make(__('Classification'))
            ->icon('heroicon-o-tag')
            ->columns(2)
            ->schema([
                TextEntry::make('court.name')->label(__('Court'))->icon('heroicon-o-building-library'),
                TextEntry::make('type.name')->label(__('Type'))->icon('heroicon-o-rectangle-stack'),
                TextEntry::make('level')->label(__('Level'))->badge(),
                TextEntry::make('difficulty')->label(__('Difficulty'))->badge(),
                Group::make()
                    ->schema(function ($record) {
                        if (! $record || ! $record->type_id) {
                            return [];
                        }

                        $type = Type::find($record->type_id);
                        if (! $type) {
                            return [];
                        }

                        $fields = $type->fieldDefinitions;
                        $entries = [];

                        foreach ($fields as $field) {
                            $value = $record->custom_fields[$field->label] ?? null;

                            if ($value === null) {
                                continue;
                            }

                            $entry = TextEntry::make("custom_fields.{$field->label}")
                                ->label($field->label);

                            if ($field->type === 'select_input') {
                                $entry->formatStateUsing(fn ($state) => $field->options[$state] ?? $state);
                            }

                            $entries[] = $entry;
                        }

                        return $entries;
                    })
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    private static function datesSection(): Section
    {
        return Section::make(__('Key Dates'))
            ->icon('heroicon-o-calendar-days')
            ->columns(2)
            ->schema([
                TextEntry::make('received_at')->label(__('Court Assigning Date'))->date()
                    ->icon('heroicon-o-arrow-down-tray')->placeholder('—'),
                TextEntry::make('next_session_date')->label(__('Next Session'))->dateTime()
                    ->icon('heroicon-o-calendar')->placeholder('—'),
                TextEntry::make('distributed_at')->label(__('Assistant Assigning Date'))->date()
                    ->icon('heroicon-o-arrow-down-tray')->placeholder('—'),
                TextEntry::make('initial_report_at')->label(__('Initial Report'))->date()
                    ->icon('heroicon-o-document-check')->placeholder('—'),
                TextEntry::make('final_report_at')->label(__('Final Report'))->date()
                    ->icon('heroicon-o-paper-airplane')->placeholder('—'),
                TextEntry::make('final_report_memo_date')->label(__('Final Report Memo Date'))
                    ->date()->placeholder('—'),
                TextEntry::make('created_at')->label(__('Created'))->date()->placeholder('—'),
                TextEntry::make('updated_at')->label(__('Updated'))->date()->placeholder('—'),
            ]);
    }

    private static function requestsSection(): Section
    {
        return Section::make(__('Requests'))
            ->icon('heroicon-o-chat-bubble-left-right')
            ->headerActions([static::addRequestAction()])
            ->schema([
                RepeatableEntry::make('requests')
                    ->hiddenLabel()
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('id')->color('primary')->icon(Heroicon::Hashtag)->default(0)->label(__('Number'))->url(fn ($record) => route('filament.admin.resources.matter-requests.view', $record)),
                        TextEntry::make('requestBy.display_name')
                            ->label(__('Requester'))
                            ->weight(FontWeight::SemiBold),
                        TextEntry::make('type')
                            ->label(__('Type'))
                            ->badge()
                            ->color('info'),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge(),
                        TextEntry::make('created_at')
                            ->label(__('Created At'))
                            ->dateTime()
                            ->since()
                            ->icon('heroicon-o-calendar'),

                        TextEntry::make('comment')->label(__('Comment'))->columnSpanFull(),
                        TextEntry::make('approvedBy.display_name')
                            ->label(__('Reviewed By'))
                            ->visible(fn ($record) => $record->status !== RequestStatus::PENDING && $record->status !== RequestStatus::DISPUTED),
                        TextEntry::make('approved_at')
                            ->label(__('Date'))
                            ->icon('heroicon-o-calendar')
                            ->dateTime()
                            ->since()
                            ->visible(fn ($record) => $record->status !== RequestStatus::PENDING && $record->status !== RequestStatus::DISPUTED),
                        TextEntry::make('approved_comment')
                            ->label(__('Reviewer Comment'))
                            ->columnSpanFull()
                            ->visible(fn ($record) => ! empty($record->approved_comment)),
                        RepeatableEntry::make('attachments')
                            ->label(__('Attachments'))
                            ->columnSpanFull()
                            ->visible(fn ($record) => $record->attachments->isNotEmpty())
                            ->schema([
                                TextEntry::make('name')
                                    ->hiddenLabel()
                                    ->icon('heroicon-o-paper-clip')
                                    ->url(fn ($record) => Storage::disk('public')->url($record->path))
                                    ->openUrlInNewTab()
                                    ->color('primary'),
                            ]),
                        Actions::make([
                            static::approveRequestAction(),
                            static::rejectRequestAction(),
                        ])
                            ->columnSpanFull()
                            ->alignEnd(),
                    ]),
            ]);
    }

    private static function notesSection(): Section
    {
        return Section::make(__('Notes'))
            ->icon('heroicon-o-chat-bubble-bottom-center-text')
            ->headerActions([static::addNoteAction()])
            ->schema([
                RepeatableEntry::make('notes')
                    ->hiddenLabel()
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn ($record) => $record?->notes?->isNotEmpty())
                    ->schema([
                        TextEntry::make('text')->label(__('Note'))->columnSpanFull(),
                        TextEntry::make('user.display_name')->label(__('By'))
                            ->icon('heroicon-o-user')->size(TextSize::ExtraSmall),
                        TextEntry::make('datetime')->label(__('Date'))
                            ->since()
                            ->dateTime()->icon('heroicon-o-clock')->size(TextSize::ExtraSmall),
                        Actions::make([
                            static::editNoteAction(),
                            static::deleteNoteAction(),
                        ])->columnSpanFull(),
                    ]),
            ]);
    }

    // ── Right column sections ─────────────────────────────────────────────────

    private static function expertsSection(): Section
    {
        return Section::make(__('Experts'))
            ->icon('heroicon-o-academic-cap')
            ->description(__('Certified experts, assistants, and external appointments'))
            ->schema([
                RepeatableEntry::make('indexedExperts')
                    ->hiddenLabel()
                    ->columns(7)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('type')
                            ->label(__('Role'))
                            ->formatStateUsing(fn ($state) => __($state
                                ? ucfirst(str_replace('-', ' ', $state)) : ''))
                            ->badge()
                            ->color(fn ($state) => static::expertTypeColor($state)),
                        TextEntry::make('role_index')->label('#')->badge()->color('gray'),
                        TextEntry::make('party.name')
                            ->label(__('Name'))
                            ->icon('heroicon-o-user-circle')
                            ->weight(FontWeight::SemiBold)
                            ->columnSpan(5),
                    ]),
            ]);
    }

    private static function partiesSection(): Section
    {
        return Section::make(__('Parties'))
            ->icon('heroicon-o-scale')
            ->description(__('Plaintiffs, defendants, and litigants with their representatives'))
            ->schema([
                RepeatableEntry::make('indexedParties')
                    ->hiddenLabel()
                    ->columns(7)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('type')
                            ->label(__('Role'))
                            ->formatStateUsing(fn ($state) => __($state
                                ? ucfirst(str_replace('-', ' ', $state)) : ''))
                            ->badge()
                            ->color(fn ($state) => static::partyTypeColor($state)),
                        TextEntry::make('role_index')
                            ->label('#')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('party.name')
                            ->label(__('Name'))
                            ->icon('heroicon-o-user-circle')
                            ->weight(FontWeight::SemiBold)
                            ->columnSpan(5)
                            ->grow(),
                        RepeatableEntry::make('representatives')
                            ->label(__('Representatives'))
                            ->columns(1)
                            ->columnSpanFull()
                            ->visible(fn ($record) => $record?->representatives?->isNotEmpty())
                            ->schema([
                                TextEntry::make('party.name')->label(__('Name'))
                                    ->icon('heroicon-o-user')->columnSpan(3),
                            ]),
                    ]),
            ]);
    }

    private static function feesSection(): Section
    {
        return Section::make(__('Fees & Collections'))
            ->icon('heroicon-o-banknotes')
            ->headerActions([
                Action::make('create_fee')
                    ->visible(fn ($record) => auth()->user()->can('CreateFee:Matter'))
                    ->label(__('Add Fee'))
                    ->icon('heroicon-o-plus')
                    ->schema([
                        Select::make('type')
                            ->label(__('Type'))
                            ->options(FeeType::class)
                            ->afterStateUpdated(fn ($state, $component) => $state?->isNegative() ? $component->getLivewire()->refresh() : null)
                            ->live(onBlur: true)
                            ->required(),
                        TextInput::make('amount')
                            ->label(__('Amount'))
                            ->numeric()
                            ->prefix(fn (Get $get) => $get('type')?->isNegative() ? '-' : '+')
                            ->live()
                            ->required(),
                        Textarea::make('description')
                            ->label(__('Description')),
                    ])->action(function (array $data, $record, $component) {
                        $record->fees()->create($data);
                        $record->updateCollectionStatus();
                        $record->refresh();
                        $component->getLivewire()->refresh();
                    }),
            ])
            ->schema([
                RepeatableEntry::make('fees')
                    ->columns(5)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('amount')
                            ->label(__('Fee Amount'))
                            ->money('AED')
                            ->weight(FontWeight::SemiBold)
                            ->icon('heroicon-o-banknotes')
                            ->color(fn ($state, Get $get) => $get('type')?->isNegative() ? 'danger' : null),
                        TextEntry::make('collected_amount')
                            ->label(__('Collected'))
                            ->money('AED')
                            ->icon('heroicon-o-check-circle')
                            ->getStateUsing(fn ($record) => $record?->allocations?->sum('amount') ?? 0)
                            ->color(fn ($state, $record) => match (true) {
                                (float) $state >= (float) ($record?->amount ?? 0) => 'success',
                                (float) $state > 0 => 'warning',
                                default => 'danger',
                            }),
                        TextEntry::make('type')->badge(),

                        TextEntry::make('date')->label(__('Date'))
                            ->date()->icon('heroicon-o-calendar'),

                        Actions::make([
                            static::collectFeeAction(),
                            static::editFeeAction(),
                            static::deleteFeeAction(),
                        ])->alignEnd(),
                        TextEntry::make('description')->label(__('Description'))
                            ->placeholder('—')->columnSpan(2),
                        RepeatableEntry::make('allocations')
                            ->label(__('Payment History'))
                            ->columns(4)
                            ->columnSpanFull()
                            ->visible(fn ($record) => $record?->allocations?->isNotEmpty())
                            ->schema([
                                TextEntry::make('amount')->label(__('Amount'))
                                    ->money('AED')->weight(FontWeight::SemiBold)->color('success'),
                                TextEntry::make('date')->label(__('Date'))->date(),
                                TextEntry::make('description')->label(__('Notes'))
                                    ->placeholder('—')->columnSpan(2),
                                Actions::make([
                                    static::editAllocationAction(),
                                    static::deleteAllocationAction(),
                                ])->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    private static function incentiveSection(): Section
    {
        return Section::make(__('Incentive'))
            ->icon('heroicon-o-calculator')
            ->description(__('Finalized incentive calculations for this matter'))
            ->visible(fn ($record) => $record && $record->incentiveLines()
                ->whereHas('calculation', fn ($q) => $q->where('status', 'finalized'))
                ->exists())
            ->schema(function ($record) {
                if (! $record) {
                    return [];
                }

                $lines = $record->incentiveLines()
                    ->whereHas('calculation', fn ($q) => $q->where('status', 'finalized'))
                    ->with(['calculation', 'assistantLines.party'])
                    ->get();

                return $lines->map(function ($line) {
                    // These two figures are DIFFERENT stages of the same
                    // calculation, not the same number twice: "Incentive Base"
                    // is the office's own fee × rate − deductions, before any
                    // per-assistant rate is applied; "Paid to Assistants" is
                    // what actually gets disbursed, including that rate (which
                    // is not stored per line — only looked up from the type's
                    // current configuration, which can be edited at any time
                    // and drift from whatever was in force when an older,
                    // already-finalized calculation ran) plus any monthly
                    // bonus or shortfall penalty. Showing them side by side
                    // with no explanation, as "Net Amount" next to "Assistant
                    // Shares", read as the same figure reported twice and
                    // disagreeing — it is two figures that were never meant to
                    // match.
                    $totalPaid = $line->assistantLines->sum('total_amount');

                    return Group::make()
                        ->columns(6)
                        ->columnSpanFull()
                        ->schema([
                            TextEntry::make("incentive_{$line->id}_calc")
                                ->label(__('Calculation'))
                                ->state($line->calculation?->name ?? '—'),
                            TextEntry::make("incentive_{$line->id}_difficulty")
                                ->label(__('Difficulty'))
                                ->badge()
                                ->state($line->difficulty ?: '—'),
                            TextEntry::make("incentive_{$line->id}_days")
                                ->label(__('Days'))
                                ->state($line->completion_days ?? '—'),
                            TextEntry::make("incentive_{$line->id}_rate")
                                ->label(__('Rate %'))
                                ->state($line->effective_percentage.'%'),
                            TextEntry::make("incentive_{$line->id}_deductions")
                                ->label(__('Deductions'))
                                ->color('danger')
                                ->state($line->total_deduction_pct > 0 ? '-'.$line->total_deduction_pct.'%' : '—'),
                            TextEntry::make("incentive_{$line->id}_net")
                                ->label(__('Incentive Base'))
                                ->helperText(__('The fee at this rate, before the assistant rate is applied — not the amount paid out.'))
                                ->state(number_format($line->net_amount, 2).' AED'),
                            TextEntry::make("incentive_{$line->id}_assistants")
                                ->label(__('Paid to Assistants'))
                                ->columnSpanFull()
                                ->weight(FontWeight::Bold)
                                ->helperText(__('Includes each assistant\'s rate on the incentive base above, plus any monthly bonus or shortfall penalty — so it will not equal the base.'))
                                ->state($line->assistantLines->isEmpty() ? '—' : $line->assistantLines->map(
                                    fn ($al) => ($al->party?->name ?? '—').': '.number_format($al->total_amount, 2).' AED'
                                    .($al->percentage_override !== null ? ' ('.__('override').')' : '')
                                )->implode(' | ').' — '.__('Total').': '.number_format($totalPaid, 2).' AED'),
                        ]);
                })->all();
            });
    }

    private static function attachmentsSection(): Section
    {
        return Section::make(__('Attachments'))
            ->icon('heroicon-o-paper-clip')
            ->headerActions([static::addAttachmentsAction()])
            ->schema([
                RepeatableEntry::make('attachments')
                    ->hiddenLabel()
                    ->columns(5)
                    ->columnSpanFull()
                    ->visible(fn ($record) => $record?->attachments?->isNotEmpty())
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('Name'))
                            ->weight(FontWeight::SemiBold)
                            ->columnSpan(4)
                            ->alignStart()
                            ->icon('heroicon-o-document-text')
                            ->url(fn ($record) => Storage::disk('public')->url($record->path))
                            ->openUrlInNewTab()
                            ->alignJustify(),
                        Actions::make([
                            static::downloadAttachmentAction(),
                            static::deleteAttachmentAction(),
                        ])->alignEnd(),
                        TextEntry::make('type')
                            ->label(__('Type'))
                            ->badge()
                            ->color('info')
                            ->formatStateUsing(fn ($state) => $state
                                ? __($state
                                        |> __(...)
                                        |> (fn ($x) => str_replace('_', ' ', $x))
                                        |> ucfirst(...)) : ''),
                        TextEntry::make('extension')->label(__('Extension'))->badge()->color('gray'),
                        TextEntry::make('size')
                            ->label(__('Size'))
                            ->formatStateUsing(fn ($state) => number_format($state / (1024 * 1024), 2).' MB'),
                        TextEntry::make('created_at')
                            ->label(__('Date'))
                            ->dateTime(format: 'M, d Y - H:i A')
                            ->columnSpan(2)
                            ->icon('heroicon-o-calendar'),
                    ]),
            ]);
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    private static function addRequestAction(): Action
    {
        return CreateRequestAction::make();

    }

    private static function approveRequestAction(): Action
    {
        return ApproveRequestAction::make();
    }

    private static function rejectRequestAction(): Action
    {
        return RejectRequestAction::make();
    }

    private static function addNoteAction(): Action
    {
        return Action::make('addNote')
            ->label(__('Add Note'))
            ->icon('heroicon-o-plus')
            ->visible(fn ($record) => auth()->user()->can('CreateNote:Matter'))
            ->modalHeading(__('Add New Note'))
            ->schema([
                Textarea::make('text')->label(__('Content'))->required()->rows(3),
            ])
            ->action(function (array $data, $record, $component) {
                $record->notes()->create([
                    'text' => $data['text'],
                    'user_id' => auth()->id(),
                    'datetime' => now(),
                ]);

                $record->refresh();
                $record->unsetRelation('notes');
                static::refreshComponent($component);
            })
            ->successNotificationTitle(__('Note added successfully.'));
    }

    private static function editNoteAction(): Action
    {
        return Action::make('editNote')
            ->label(__('Edit'))
            ->iconButton()
            ->icon('heroicon-o-pencil')
            ->visible(fn ($record) => auth()->user()->can('UpdateNote:Matter'))
            ->modalHeading(__('Edit Note'))
            ->schema([
                Textarea::make('text')->label(__('Content'))->required()->rows(3),
            ])
            ->fillForm(fn ($record) => ['text' => $record->text])
            ->action(function (array $data, $record, $component) {
                $record->update(['text' => $data['text']]);
                $record->refresh();
                static::refreshComponent($component);
            });
    }

    private static function deleteNoteAction(): Action
    {
        return Action::make('deleteNote')
            ->label(__('Delete'))
            ->iconButton()
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn ($record) => auth()->user()->can('DeleteNote:Matter'))
            ->requiresConfirmation()
            ->action(function ($record, $component) {
                $record->delete();
                static::refreshComponent($component);
            });
    }

    private static function collectFeeAction(): Action
    {
        return CollectFeeAction::make()->after(function ($component) {
            static::refreshRecord($component);
        });

    }

    private static function editFeeAction(): Action
    {
        return Action::make('editFee')
            ->label(__('Edit'))
            ->iconButton()
            ->icon('heroicon-o-pencil')
            ->visible(fn ($record) => auth()->user()->can('UpdateFee:Matter'))
            ->modalHeading(__('Edit Fee'))
            ->schema([
                TextInput::make('amount')->label(__('Fee Amount'))
                    ->numeric()->required()->prefix('AED'),
                TextInput::make('description')->label(__('Description'))->required(),
            ])
            ->fillForm(fn ($record) => [
                'amount' => $record->amount,
                'description' => $record->description,
            ])
            ->action(function (array $data, $record, $component) {
                $record->update([
                    'amount' => $data['amount'],
                    'description' => $data['description'],
                ]);
                $record->refresh();
                static::refreshRecord($component);
            });
    }

    private static function deleteFeeAction(): Action
    {
        return Action::make('deleteFee')
            ->label(__('Delete'))
            ->iconButton()
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn ($record) => auth()->user()->can('DeleteFee:Matter'))
            ->requiresConfirmation()
            ->action(function ($record, $component) {
                $record->delete();
                static::refreshRecord($component);
            });
    }

    private static function editAllocationAction(): Action
    {
        return Action::make('editAllocation')
            ->label(__('Edit'))
            ->iconButton()
            ->icon('heroicon-o-pencil')
            ->visible(fn ($record) => auth()->user()->can('updateAllocation', $record->matter))
            ->modalHeading(__('Edit Payment'))
            ->schema([
                TextInput::make('amount')->label(__('Amount'))
                    ->numeric()->required()->prefix('AED'),
                DatePicker::make('date')->label(__('Payment Date'))->required(),
                Textarea::make('description')->label(__('Notes / Reference'))->rows(2),
            ])
            ->fillForm(fn ($record) => [
                'amount' => $record->amount,
                'date' => $record->date,
                'description' => $record->description,
            ])
            ->action(function (array $data, $record, $component) {
                $record->update([
                    'amount' => $data['amount'],
                    'date' => $data['date'],
                    'description' => $data['description'],
                ]);
                $record->refresh();
                static::refreshRecord($component);
            });
    }

    private static function deleteAllocationAction(): Action
    {
        return Action::make('deleteAllocation')
            ->label(__('Delete'))
            ->iconButton()
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn ($record) => auth()->user()->can('deleteAllocation', $record->matter))
            ->requiresConfirmation()
            ->action(function ($record, $component) {
                $record->delete();
                static::refreshRecord($component);
            });
    }

    private static function addAttachmentsAction(): Action
    {
        return Action::make('addAttachments')
            ->label(__('Add Attachments'))
            ->icon('heroicon-o-plus')
            ->visible(fn ($record) => auth()->user()->can('createAttachment', $record))
            ->modalHeading(__('Add New Attachments'))
            ->schema([
                Repeater::make('attachments_data')
                    ->label(__('Files'))
                    ->schema([
                        FileUpload::make('file')
                            ->label(__('Attachment'))
                            ->disk('public')
                            ->directory('matter-attachments')
                            ->visibility('public')
                            ->required()
                            ->getUploadedFileNameForStorageUsing(fn ($file) => FileUploadHelper::getUniqueFilename($file, 'matter-attachments'))
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state instanceof TemporaryUploadedFile) {
                                    $set('name', $state->getClientOriginalName());
                                }
                            }),
                        Hidden::make('name'),
                        Select::make('type')
                            ->label(__('Type'))
                            ->options([
                                'initial_report' => __('Initial Report'),
                                'final_report' => __('Final Report'),
                                'supporting_document' => __('Supporting Document'),
                                'correspondence' => __('Correspondence'),
                                'other' => __('Other'),
                            ])
                            ->required(),
                    ])
                    ->columns(2)
                    ->addActionLabel(__('Add Another File')),
            ])
            ->action(function (array $data, $record, $component) {
                $disk = Storage::disk('public');
                if ($data['attachments_data']) {
                    foreach ($data['attachments_data'] as $item) {
                        $path = $item['file'];
                        $record->attachments()->create([
                            'user_id' => auth()->id(),
                            'type' => $item['type'],
                            'path' => $path,
                            'name' => $item['name'] ?? basename($path),
                            'size' => $disk->exists($path) ? $disk->size($path) : 0,
                            'extension' => pathinfo($path, PATHINFO_EXTENSION) ?? '',
                        ]);
                    }
                }

                $record->refresh();
                $record->unsetRelation('attachments');
                static::refreshComponent($component);
            })
            ->successNotificationTitle(__('Attachments added successfully.'));
    }

    private static function downloadAttachmentAction(): Action
    {
        return Action::make('download')
            ->icon('heroicon-o-arrow-down-tray')
            ->iconButton()
            ->tooltip(__('Download'))
            ->url(fn ($record) => route('attachment.download', $record))
            ->openUrlInNewTab(false);
    }

    private static function deleteAttachmentAction(): Action
    {
        return Action::make('deleteAttachment')
            ->label(__('Delete'))
            ->iconButton()
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn ($record) => auth()->user()->can('deleteAttachment', $record->matter))
            ->requiresConfirmation()
            ->action(function ($record, $component) {
                Storage::disk('public')->delete($record->path);
                $record->delete();
                $component->getLivewire()->getRecord()->refresh();
                $component->getLivewire()->getRecord()->unsetRelation('attachments');
                static::refreshComponent($component);
            });
    }
}
