<?php

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\CalendarWidget;
use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CalendarWidgetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('View:CalendarWidget', 'web');
        Permission::findOrCreate('ViewAny:CalendarEvent', 'web');

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['View:CalendarWidget', 'ViewAny:CalendarEvent']);
        $this->actingAs($this->admin);
    }

    public function test_calendar_widget_renders(): void
    {
        CalendarEvent::create([
            'title' => 'Hearing Meeting',
            'start_datetime' => now()->startOfWeek()->addDay()->setHour(10),
            'end_datetime' => now()->startOfWeek()->addDay()->setHour(11),
            'is_all_day' => false,
        ]);

        Livewire::test(CalendarWidget::class)
            ->assertSuccessful();
    }

    public function test_calendar_widget_can_mount_edit_action(): void
    {
        $event = CalendarEvent::create([
            'title' => 'Initial Title',
            'start_datetime' => now()->startOfWeek()->addDay()->setHour(10),
            'end_datetime' => now()->startOfWeek()->addDay()->setHour(11),
            'location' => 'Office Room 1',
            'description' => 'Meeting notes',
            'is_all_day' => false,
        ]);

        Livewire::test(CalendarWidget::class)
            ->call('onEventClick', [
                'id' => $event->id,
                'title' => 'Initial Title',
                'start' => $event->start_datetime->toIso8601String(),
                'end' => $event->end_datetime->toIso8601String(),
                'extendedProps' => [
                    'location' => 'Office Room 1',
                    'description' => 'Meeting notes',
                ],
            ])
            ->assertSuccessful();
    }
}
