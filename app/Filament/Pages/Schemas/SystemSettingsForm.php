<?php

namespace App\Filament\Pages\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SystemSettingsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('system-settings-tabs')
                ->id('system-settings-tabs')
                ->contained(false)
                ->tabs([
                    Tabs\Tab::make(__('Maintenance & Offline'))
                        ->icon(Heroicon::SignalSlash)
                        ->schema([
                            Section::make(__('System Status & Offline Mode'))
                                ->description(__('Control whether the application is accessible to regular users or put into maintenance mode.'))
                                ->icon(Heroicon::Signal)
                                ->schema([
                                    Toggle::make('app_offline')
                                        ->label(__('Enable Offline / Maintenance Mode'))
                                        ->helperText(__('When enabled, regular users will be redirected to the maintenance page.'))
                                        ->live()
                                        ->columnSpanFull(),

                                    Textarea::make('offline_message')
                                        ->label(__('Maintenance Message'))
                                        ->helperText(__('Custom message displayed to users during offline maintenance.'))
                                        ->rows(3)
                                        ->columnSpanFull()
                                        ->visible(fn (Get $get): bool => (bool) $get('app_offline')),

                                    Toggle::make('offline_allow_admins')
                                        ->label(__('Allow Administrators to Access While Offline'))
                                        ->helperText(__('Super administrators and administrators will retain full access to manage the system.'))
                                        ->default(true)
                                        ->columnSpanFull(),
                                ]),
                        ]),

                    Tabs\Tab::make(__('General Settings'))
                        ->icon(Heroicon::Cog6Tooth)
                        ->schema([
                            Section::make(__('Application Defaults'))
                                ->description(__('General system information and localized default parameters.'))
                                ->icon(Heroicon::AdjustmentsHorizontal)
                                ->columns(2)
                                ->schema([
                                    TextInput::make('app_name')
                                        ->label(__('System Name'))
                                        ->required()
                                        ->maxLength(255),

                                    TextInput::make('company_name')
                                        ->label(__('Company / Organization Name'))
                                        ->maxLength(255),

                                    Select::make('app_locale')
                                        ->label(__('Default Language'))
                                        ->options([
                                            'ar' => __('Arabic'),
                                            'en' => __('English'),
                                        ])
                                        ->required()
                                        ->default('ar'),

                                    Select::make('app_timezone')
                                        ->label(__('Default Timezone'))
                                        ->options([
                                            'Asia/Muscat' => 'Asia/Muscat (UTC+4)',
                                            'Asia/Dubai' => 'Asia/Dubai (UTC+4)',
                                            'Asia/Riyadh' => 'Asia/Riyadh (UTC+3)',
                                            'UTC' => 'UTC',
                                        ])
                                        ->searchable()
                                        ->required()
                                        ->default('Asia/Muscat'),

                                    TextInput::make('currency_code')
                                        ->label(__('Default Currency'))
                                        ->required()
                                        ->default('AED')
                                        ->maxLength(10),

                                    Select::make('records_per_page')
                                        ->label(__('Default Records Per Page'))
                                        ->options([
                                            10 => '10',
                                            25 => '25',
                                            50 => '50',
                                            100 => '100',
                                        ])
                                        ->required()
                                        ->default(25),
                                ]),
                        ]),

                    Tabs\Tab::make(__('Email Settings'))
                        ->icon(Heroicon::Envelope)
                        ->schema([
                            Section::make(__('Mail Server Configuration'))
                                ->description(__('Configure SMTP and outbound email server details.'))
                                ->icon(Heroicon::EnvelopeOpen)
                                ->columns(2)
                                ->schema([
                                    Select::make('mail_mailer')
                                        ->label(__('Mail Driver'))
                                        ->options([
                                            'microsoft-graph' => 'Microsoft Graph',
                                            'smtp' => 'SMTP',
                                            'sendmail' => 'Sendmail',
                                            'log' => 'Log (Testing)',
                                        ])
                                        ->required()
                                        ->live()
                                        ->default('smtp')
                                        ->columnSpanFull(),

                                    TextInput::make('mail_host')
                                        ->label(__('SMTP Host'))
                                        ->placeholder('smtp.mailgun.org')
                                        ->required(fn (Get $get): bool => $get('mail_mailer') === 'smtp'),

                                    TextInput::make('mail_port')
                                        ->label(__('SMTP Port'))
                                        ->numeric()
                                        ->placeholder('587')
                                        ->default(587)
                                        ->required(fn (Get $get): bool => $get('mail_mailer') === 'smtp'),

                                    Select::make('mail_encryption')
                                        ->label(__('Encryption'))
                                        ->options([
                                            'tls' => 'TLS',
                                            'ssl' => 'SSL',
                                            'none' => __('None'),
                                        ])
                                        ->default('tls'),

                                    TextInput::make('mail_username')
                                        ->label(__('SMTP Username'))
                                        ->placeholder('user@example.com'),

                                    TextInput::make('mail_password')
                                        ->label(__('SMTP Password'))
                                        ->password()
                                        ->revealable(),

                                    TextInput::make('mail_from_address')
                                        ->label(__('Sender Email Address'))
                                        ->email()
                                        ->placeholder('noreply@example.com')
                                        ->required(),

                                    TextInput::make('mail_from_name')
                                        ->label(__('Sender Name'))
                                        ->placeholder('JPA Emirates')
                                        ->required(),
                                ]),
                        ]),

                    Tabs\Tab::make(__('Notifications & Announcements'))
                        ->icon(Heroicon::Bell)
                        ->schema([
                            Section::make(__('User Defaults & Announcements'))
                                ->description(__('Configure notification defaults for users and system-wide announcements.'))
                                ->icon(Heroicon::Megaphone)
                                ->schema([
                                    Toggle::make('default_notify_by_email')
                                        ->label(__('Default Notify by Email for New Users'))
                                        ->default(true),

                                    Toggle::make('default_notify_by_whatsapp')
                                        ->label(__('Default Notify by WhatsApp for New Users'))
                                        ->default(false),

                                    Toggle::make('show_system_announcement')
                                        ->label(__('Display System Announcement Banner'))
                                        ->live(),

                                    Textarea::make('system_announcement')
                                        ->label(__('Announcement Message'))
                                        ->rows(3)
                                        ->visible(fn (Get $get): bool => (bool) $get('show_system_announcement')),
                                ]),
                        ]),
                ]),
        ]);
    }
}
