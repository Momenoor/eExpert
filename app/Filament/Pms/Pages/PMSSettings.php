<?php

namespace App\Filament\Pms\Pages;

use App\Filament\Mms\Pages\Schemas\PMSSettingsForm;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class PMSSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::AdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 7;

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('PMS Settings');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    public function getTitle(): string
    {
        return __('PMS Settings');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:PMSSettings') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'pms_mixed_use_vat_rate' => Setting::get('pms_mixed_use_vat_rate', 0.05),
            'pms_attestation_fee_estimate' => Setting::get('pms_attestation_fee_estimate', 0),
            'pms_bounced_cheque_penalty' => Setting::get('pms_bounced_cheque_penalty', 100),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return PMSSettingsForm::configure($schema)
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([
                EmbeddedSchema::make('form'),
            ])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('Save Settings'))
                            ->submit('save')
                            ->icon(Heroicon::Check)
                            ->color('primary')
                            ->keyBindings(['mod+s']),
                    ])->key('form-actions'),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach ($state as $key => $value) {
            Setting::set($key, $value, 'pms');
        }

        Notification::make()
            ->title(__('Settings saved successfully'))
            ->success()
            ->send();
    }
}
