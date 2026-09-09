<?php

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\VacationCalendarWidget;
use App\Models\Party;
use App\Models\PartyLeave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class VacationCalendarWidgetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('View:VacationCalendarWidget', 'web');
        Permission::findOrCreate('ViewAny:PartyLeave', 'web');

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['View:VacationCalendarWidget', 'ViewAny:PartyLeave']);
        $this->actingAs($this->admin);
    }

    public function test_vacation_calendar_widget_renders(): void
    {
        $party = Party::factory()->create(['role' => ['role' => 'expert']]);
        PartyLeave::create([
            'party_id' => $party->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->startOfMonth()->addDays(5)->toDateString(),
            'reason' => 'Annual vacation',
        ]);

        Livewire::test(VacationCalendarWidget::class)
            ->assertSuccessful();
    }

    public function test_vacation_calendar_widget_can_mount_edit_action(): void
    {
        $party = Party::factory()->create(['role' => ['role' => 'expert']]);
        $leave = PartyLeave::create([
            'party_id' => $party->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->startOfMonth()->addDays(5)->toDateString(),
            'reason' => 'Annual vacation',
        ]);

        Livewire::test(VacationCalendarWidget::class)
            ->call('onEventClick', [
                'id' => $leave->id,
                'title' => $party->name,
                'start' => $leave->start_date->toDateString(),
                'end' => $leave->end_date->copy()->addDay()->toDateString(),
                'party_id' => $party->id,
                'extendedProps' => [
                    'reason' => 'Annual vacation',
                ],
            ])
            ->assertSuccessful();
    }
}
