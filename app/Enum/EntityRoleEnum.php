<?php

namespace App\Enum;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum EntityRoleEnum: string implements HasColor, HasIcon, HasLabel
{
    use \App\Filament\Contracts\HasLabel;

    case Expert = 'expert';
    case Broker = 'broker';
    case Party = 'party';
    case LegalRepresentative = 'legal_representative';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Expert => Color::Blue,
            self::Broker => Color::Green,
            self::Party => Color::Red,
            self::LegalRepresentative => Color::Amber,
        };
    }

    public function getIcon(): string
    {

    }

    public function subRoles(): array
    {
        return match ($this) {
            self::Expert => collect([
                EntitySubRoleEnum::MainExpert,
                EntitySubRoleEnum::InternalExpert,
                EntitySubRoleEnum::InternalAssistant,
                EntitySubRoleEnum::ExternalExpert,
                EntitySubRoleEnum::ExternalAssistant,
            ])->mapWithKeys(fn($role) => [$role->value => $role->getLabel()])->toArray(),
            self::Broker => collect([
                EntitySubRoleEnum::InternalBroker,
                EntitySubRoleEnum::ExternalBroker,
            ])->mapWithKeys(fn($role) => [$role->value => $role->getLabel()])->toArray(),
            self::Party => collect([
                EntitySubRoleEnum::Defendant,
                EntitySubRoleEnum::Plaintiff,
                EntitySubRoleEnum::ThirdPartyDefendant,
            ])->mapWithKeys(fn($role) => [$role->value => $role->getLabel()])->toArray(),
            self::LegalRepresentative => collect([
                EntitySubRoleEnum::LegalPerson,
                EntitySubRoleEnum::LegalFirm,
            ])->mapWithKeys(fn($role) => [$role->value => $role->getLabel()])->toArray(),
        };
    }
}
