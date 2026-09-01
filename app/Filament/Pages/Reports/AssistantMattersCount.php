<?php

namespace App\Filament\Pages\Reports;

use App\Enums\MatterStatus;
use App\Filament\Widgets\AssistantMatterCountTableWidget;
use App\Filament\Widgets\AssistantMattersCountChartWidget;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class AssistantMattersCount extends Page
{
    use HasPageShield;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Assistant Matters Count';

    protected static string|null|UnitEnum $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(parent::getNavigationGroup());
    }

    public static function getNavigationLabel(): string
    {
        return __('Assistant Matters Count');
    }

    public function getTitle(): string
    {
        return __('Assistant Matters Count').': '.MatterStatus::IN_PROGRESS->getLabel();
    }

    public function getColumns(): int|array
    {
        return 2;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AssistantMattersCountChartWidget::class,
            AssistantMatterCountTableWidget::class,
        ];
    }
}
