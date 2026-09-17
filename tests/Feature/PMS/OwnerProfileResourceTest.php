<?php

namespace Tests\Feature\PMS;

use App\Filament\Resources\OwnerProfiles\Pages\CreateOwnerProfile;
use App\Filament\Resources\OwnerProfiles\Pages\EditOwnerProfile;
use App\Models\OwnerProfile;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OwnerProfileResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin);
    }

    public function test_creating_an_owner_creates_both_the_party_and_the_profile(): void
    {
        Livewire::test(CreateOwnerProfile::class)
            ->fillForm([
                'name' => 'Khalid Al Marzooqi',
                'phone' => ['0509876543'],
                'iban' => 'AE070331234567890123456',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $party = Party::where('name', 'Khalid Al Marzooqi')->sole();
        $this->assertTrue($party->isOwner());

        $profile = OwnerProfile::where('party_id', $party->id)->sole();
        $this->assertSame('AE070331234567890123456', $profile->iban);
    }

    public function test_editing_an_owner_updates_both_the_party_and_the_profile(): void
    {
        $party = Party::factory()->owner()->create(['name' => 'Old Owner']);
        $profile = OwnerProfile::create(['party_id' => $party->id]);

        Livewire::test(EditOwnerProfile::class, ['record' => $profile->getKey()])
            ->fillForm([
                'name' => 'New Owner',
                'bank_name' => 'Emirates NBD',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('New Owner', $party->fresh()->name);
        $this->assertSame('Emirates NBD', $profile->fresh()->bank_name);
    }
}
