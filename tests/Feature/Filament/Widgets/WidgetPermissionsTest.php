<?php

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\AssistantMatterCountTableWidget;
use App\Filament\Widgets\AssistantMattersCountChartWidget;
use App\Filament\Widgets\AttentionNeededWidget;
use App\Filament\Widgets\CalendarWidget;
use App\Filament\Widgets\CollectionsAgingWidget;
use App\Filament\Widgets\IncentiveExtraRulesOverviewWidget;
use App\Filament\Widgets\IncentiveMetaAdjustmentsOverviewWidget;
use App\Filament\Widgets\IncentiveSummaryTableWidget;
use App\Filament\Widgets\IncentiveTypeConfigsOverviewWidget;
use App\Filament\Widgets\MattersPerYearWidget;
use App\Filament\Widgets\MatterStatsWidget;
use App\Filament\Widgets\UpcomingSessionsWidget;
use App\Filament\Widgets\VacationCalendarWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WidgetPermissionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string, string, string}>
     */
    public static function widgetPermissionProvider(): array
    {
        return [
            'UpcomingSessionsWidget' => [
                UpcomingSessionsWidget::class,
                'View:UpcomingSessionsWidget',
                'ViewAny:Matter',
            ],
            'AssistantMatterCountTableWidget' => [
                AssistantMatterCountTableWidget::class,
                'View:AssistantMatterCountTableWidget',
                'ViewAny:Matter',
            ],
            'AssistantMattersCountChartWidget' => [
                AssistantMattersCountChartWidget::class,
                'View:AssistantMattersCountChartWidget',
                'ViewAny:Matter',
            ],
            'AttentionNeededWidget' => [
                AttentionNeededWidget::class,
                'View:AttentionNeededWidget',
                'ViewAny:Matter',
            ],
            'CalendarWidget' => [
                CalendarWidget::class,
                'View:CalendarWidget',
                'ViewAny:CalendarEvent',
            ],
            'CollectionsAgingWidget' => [
                CollectionsAgingWidget::class,
                'View:CollectionsAgingWidget',
                'CollectFee:Matter',
            ],
            'IncentiveExtraRulesOverviewWidget' => [
                IncentiveExtraRulesOverviewWidget::class,
                'View:IncentiveExtraRulesOverviewWidget',
                'ViewAny:IncentiveExtraRule',
            ],
            'IncentiveMetaAdjustmentsOverviewWidget' => [
                IncentiveMetaAdjustmentsOverviewWidget::class,
                'View:IncentiveMetaAdjustmentsOverviewWidget',
                'ViewAny:IncentiveMetaAdjustment',
            ],
            'IncentiveSummaryTableWidget' => [
                IncentiveSummaryTableWidget::class,
                'View:IncentiveSummaryTableWidget',
                'View:IncentiveCalculation',
            ],
            'IncentiveTypeConfigsOverviewWidget' => [
                IncentiveTypeConfigsOverviewWidget::class,
                'View:IncentiveTypeConfigsOverviewWidget',
                'ViewAny:MatterTypeIncentiveConfig',
            ],
            'MatterStatsWidget' => [
                MatterStatsWidget::class,
                'View:MatterStatsWidget',
                'ViewAny:Matter',
            ],
            'MattersPerYearWidget' => [
                MattersPerYearWidget::class,
                'View:MattersPerYearWidget',
                'ViewAny:Matter',
            ],
            'VacationCalendarWidget' => [
                VacationCalendarWidget::class,
                'View:VacationCalendarWidget',
                'ViewAny:PartyLeave',
            ],
        ];
    }

    #[DataProvider('widgetPermissionProvider')]
    public function test_user_with_widget_permission_can_view_widget(
        string $widgetClass,
        string $widgetPermission,
        string $resourcePermission
    ): void {
        Permission::findOrCreate($widgetPermission, 'web');

        $user = User::factory()->create();
        $user->givePermissionTo($widgetPermission);
        $this->actingAs($user);

        $this->assertTrue($widgetClass::canView());
    }

    #[DataProvider('widgetPermissionProvider')]
    public function test_user_with_only_resource_permission_cannot_view_widget(
        string $widgetClass,
        string $widgetPermission,
        string $resourcePermission
    ): void {
        Permission::findOrCreate($widgetPermission, 'web');
        Permission::findOrCreate($resourcePermission, 'web');

        $user = User::factory()->create();
        // Give only the resource permission, NOT the widget permission
        $user->givePermissionTo($resourcePermission);
        $this->actingAs($user);

        $this->assertFalse(
            $widgetClass::canView(),
            "Expected {$widgetClass} to not be viewable without {$widgetPermission}"
        );
    }
}
