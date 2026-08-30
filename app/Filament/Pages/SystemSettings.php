<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Schemas\SystemSettingsForm;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use UnitEnum;

class SystemSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::Cog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 99;

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('System Settings');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    public function getTitle(): string
    {
        return __('System Settings');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole(['super-admin', 'super_admin']);
        }

        return (bool) ($user->is_admin ?? false);
    }

    public function mount(): void
    {
        $this->form->fill([
            'app_offline' => Setting::get('app_offline', false),
            'offline_message' => Setting::get('offline_message', __('System is currently undergoing scheduled maintenance. Please check back later.')),
            'offline_allow_admins' => Setting::get('offline_allow_admins', true),

            'app_name' => Setting::get('app_name', config('app.name', 'JPA Emirates')),
            'company_name' => Setting::get('company_name', 'JPA Auditing & Accounting'),
            'app_timezone' => Setting::get('app_timezone', 'Asia/Muscat'),
            'app_locale' => Setting::get('app_locale', 'ar'),
            'currency_code' => Setting::get('currency_code', 'AED'),
            'records_per_page' => Setting::get('records_per_page', 25),

            'mail_mailer' => Setting::get('mail_mailer', config('mail.default', 'smtp')),
            'mail_host' => Setting::get('mail_host', config('mail.mailers.smtp.host', '')),
            'mail_port' => Setting::get('mail_port', config('mail.mailers.smtp.port', 587)),
            'mail_username' => Setting::get('mail_username', config('mail.mailers.smtp.username', '')),
            'mail_password' => Setting::get('mail_password', config('mail.mailers.smtp.password', '')),
            'mail_encryption' => Setting::get('mail_encryption', config('mail.mailers.smtp.encryption', 'tls')),
            'mail_from_address' => Setting::get('mail_from_address', config('mail.from.address', '')),
            'mail_from_name' => Setting::get('mail_from_name', config('mail.from.name', '')),

            'default_notify_by_email' => Setting::get('default_notify_by_email', true),
            'default_notify_by_whatsapp' => Setting::get('default_notify_by_whatsapp', false),
            'system_announcement' => Setting::get('system_announcement', ''),
            'show_system_announcement' => Setting::get('show_system_announcement', false),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return SystemSettingsForm::configure($schema)
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    EmbeddedSchema::make('form'),
                ])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make($this->getFormActions())
                            ->key('form-actions'),
                    ]),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('Save Settings'))
                ->submit('save')
                ->icon(Heroicon::Check)
                ->color('primary')
                ->keyBindings(['mod+s']),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTestEmail')
                ->label(__('Send Test Email'))
                ->icon(Heroicon::PaperAirplane)
                ->color('gray')
                ->form([
                    TextInput::make('recipient')
                        ->label(__('Recipient Email Address'))
                        ->email()
                        ->required()
                        ->default(fn () => auth()->user()?->email),
                ])
                ->action(function (array $data): void {
                    $this->sendTestEmail($data['recipient']);
                }),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $groupMap = [
            'app_offline' => 'system',
            'offline_message' => 'system',
            'offline_allow_admins' => 'system',

            'app_name' => 'general',
            'company_name' => 'general',
            'app_timezone' => 'general',
            'app_locale' => 'general',
            'currency_code' => 'general',
            'records_per_page' => 'general',

            'mail_mailer' => 'mail',
            'mail_host' => 'mail',
            'mail_port' => 'mail',
            'mail_username' => 'mail',
            'mail_password' => 'mail',
            'mail_encryption' => 'mail',
            'mail_from_address' => 'mail',
            'mail_from_name' => 'mail',

            'default_notify_by_email' => 'notifications',
            'default_notify_by_whatsapp' => 'notifications',
            'system_announcement' => 'notifications',
            'show_system_announcement' => 'notifications',
        ];

        foreach ($state as $key => $value) {
            $group = $groupMap[$key] ?? 'general';
            Setting::set($key, $value, $group);
        }

        Setting::applyMailConfig();

        Notification::make()
            ->title(__('Settings saved successfully'))
            ->success()
            ->send();
    }

    protected function sendTestEmail(string $recipient): void
    {
        try {
            Setting::applyMailConfig();

            Mail::raw(
                __('This is a test email sent from :app to verify that your email server settings are properly configured.', [
                    'app' => Setting::get('app_name', config('app.name', 'JPA Emirates')),
                ]),
                function ($message) use ($recipient) {
                    $message->to($recipient)
                        ->subject(__('Test Email - :app', [
                            'app' => Setting::get('app_name', config('app.name', 'JPA Emirates')),
                        ]));
                }
            );

            Notification::make()
                ->title(__('Test email sent successfully'))
                ->body(__('A test email has been dispatched to :recipient.', ['recipient' => $recipient]))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('Failed to send test email'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
