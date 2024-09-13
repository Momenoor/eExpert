<?php

namespace App\Enum;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum EntitySubRoleEnum: string implements HasColor, HasIcon, HasLabel
{
    use \App\Filament\Contracts\HasLabel;
    case MainExpert = 'expert_main_expert';
    case InternalExpert = 'expert_internal_expert';
    case InternalAssistant = 'expert_internal_assistant';
    case ExternalExpert = 'expert_external_expert';
    case ExternalAssistant = 'expert_external_assistant';
    case Plaintiff = 'party_plaintiff';
    case Defendant = 'party_defendant';
    case ThirdPartyDefendant = 'party_third_party_defendant';
    case LegalFirm = 'legal_representative_legal_firm';
    case LegalPerson = 'legal_representative_legal_person';
    case InternalBroker = 'broker_internal_broker';
    case ExternalBroker = 'broker_external_broker';


    public function getColor(): string|array|null
    {
        return match ($this) {
            self::MainExpert => Color::Blue,
            self::InternalExpert => Color::Amber,
            self::InternalAssistant => Color::Zinc,
            self::ExternalExpert => Color::Pink,
            self::ExternalAssistant => Color::Purple,
            self::Plaintiff => Color::Green,
            self::Defendant => Color::Red,
            self::LegalFirm => Color::Orange,
            self::LegalPerson => Color::Stone,
            self::InternalBroker => Color::Neutral,
            self::ExternalBroker => Color::Slate,
            self::ThirdPartyDefendant => Color::Yellow,
        };
    }

    public function getIcon(): string
    {

    }

}
