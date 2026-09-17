<?php

namespace Tests\Feature\PMS;

use App\Filament\Pms\Resources\Properties\Pages\CreateProperty;
use App\Models\Party;
use App\Models\Property;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A Property's owners are managed as a repeater bound straight to the
 * `owners` belongsToMany relationship (pivot: ownership_percentage) — the
 * one rule enforced at the form level is that a property's percentages add
 * up to 100, since the database itself can't see sibling rows at insert time.
 */
class PropertyResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // These resources only exist in the 'pms' panel now — without this,
        // Filament resolves getUrl()/Livewire::test() against the default
        // ('admin'/mms) panel, where the route doesn't exist.
        Filament::setCurrentPanel(Filament::getPanel('pms'));

        // 'super-admin' (hyphenated) is the role Shield's Gate::before bypass
        // actually checks (config/filament-shield.php) — no explicit
        // permission seeding needed for a super-admin-driven test.
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin);
    }

    public function test_creating_a_property_with_owners_summing_to_100_percent_succeeds(): void
    {
        $ownerOne = Party::factory()->owner()->create();
        $ownerTwo = Party::factory()->owner()->create();

        Livewire::test(CreateProperty::class)
            ->fillForm([
                'name' => 'Marina Tower',
                'city' => 'Dubai',
                'owners' => [
                    ['party_id' => $ownerOne->id, 'ownership_percentage' => 60],
                    ['party_id' => $ownerTwo->id, 'ownership_percentage' => 40],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $property = Property::where('name', 'Marina Tower')->sole();
        $this->assertCount(2, $property->owners);
    }

    public function test_creating_a_property_with_owners_not_summing_to_100_percent_is_refused(): void
    {
        $ownerOne = Party::factory()->owner()->create();
        $ownerTwo = Party::factory()->owner()->create();

        Livewire::test(CreateProperty::class)
            ->fillForm([
                'name' => 'Business Bay Plaza',
                'owners' => [
                    ['party_id' => $ownerOne->id, 'ownership_percentage' => 60],
                    ['party_id' => $ownerTwo->id, 'ownership_percentage' => 60],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['owners']);

        $this->assertSame(0, Property::where('name', 'Business Bay Plaza')->count());
    }

    public function test_a_single_owner_at_100_percent_is_valid(): void
    {
        $owner = Party::factory()->owner()->create();

        Livewire::test(CreateProperty::class)
            ->fillForm([
                'name' => 'Downtown Residences',
                'owners' => [
                    ['party_id' => $owner->id, 'ownership_percentage' => 100],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }
}
