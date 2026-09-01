<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CustomProfile extends EditProfile
{
    public function hasLogo(): bool
    {
        return true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('Name'))
                ->required(),
            TextInput::make('email')
                ->label(__('Email'))
                ->required()
                ->email(),
            TextInput::make('display_name')
                ->label(__('Display Name'))
                ->required(),
            Toggle::make('notify_by_whatsapp')
                ->label(__('Notify by Whatsapp'))
                ->visible(fn () => auth()->user()->hasAnyRole(['super-admin', 'super_admin']))
                ->required(),
            Toggle::make('notify_by_email')
                ->label(__('Notify by Email'))
                ->default(true)
                ->required(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }
}
