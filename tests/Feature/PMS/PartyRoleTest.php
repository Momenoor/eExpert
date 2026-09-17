<?php

namespace Tests\Feature\PMS;

use App\Models\Building;
use App\Models\OwnerProfile;
use App\Models\Party;
use App\Models\TenantProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tenant and Owner reuse `Party`'s existing role-tag system rather than
 * standalone tables — `isTenant()`/`isOwner()` mirror the already-established
 * `isEmployee()`/`isExpert()`, and `TenantProfile`/`OwnerProfile` mirror
 * `EmployeeProfile`'s split of generic identity from role-specific detail.
 */
class PartyRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_party_can_be_tagged_as_a_tenant(): void
    {
        $party = Party::factory()->tenant()->create();

        $this->assertTrue($party->isTenant());
        $this->assertFalse($party->isOwner());
        $this->assertFalse($party->isEmployee());
    }

    public function test_a_party_can_be_tagged_as_an_owner(): void
    {
        $party = Party::factory()->owner()->create();

        $this->assertTrue($party->isOwner());
        $this->assertFalse($party->isTenant());
    }

    public function test_with_role_scope_finds_tenants_and_owners(): void
    {
        $tenant = Party::factory()->tenant()->create();
        $owner = Party::factory()->owner()->create();
        Party::factory()->employee()->create();

        $this->assertTrue(Party::withRole('tenant')->get()->contains($tenant));
        $this->assertTrue(Party::withRole('owner')->get()->contains($owner));
        $this->assertSame(1, Party::withRole('tenant')->count());
    }

    public function test_tenant_profile_holds_role_specific_fields_separate_from_the_party(): void
    {
        $party = Party::factory()->tenant()->create(['name' => 'Acme Trading LLC']);

        $profile = TenantProfile::create([
            'party_id' => $party->id,
            'tenant_type' => 'company',
            'identification_type' => 'trade_license',
            'identification_number' => 'CN-1234567',
            'trn' => '100123456700003',
        ]);

        $this->assertTrue($party->fresh()->tenantProfile->is($profile));
        $this->assertTrue($profile->isCompany());
        $this->assertSame('Acme Trading LLC', $party->name);
    }

    public function test_owner_profile_holds_role_specific_fields_separate_from_the_party(): void
    {
        $party = Party::factory()->owner()->create();

        $profile = OwnerProfile::create([
            'party_id' => $party->id,
            'iban' => 'AE070331234567890123456',
        ]);

        $this->assertTrue($party->fresh()->ownerProfile->is($profile));
    }

    public function test_a_party_can_hold_a_stake_in_multiple_buildings(): void
    {
        $owner = Party::factory()->owner()->create();
        $buildingOne = Building::factory()->create();
        $buildingTwo = Building::factory()->create();

        $owner->ownedBuildings()->attach([
            $buildingOne->id => ['ownership_percentage' => 100],
            $buildingTwo->id => ['ownership_percentage' => 50],
        ]);

        $this->assertCount(2, $owner->ownedBuildings);
        $this->assertSame(
            50.0,
            (float) $owner->ownedBuildings()->where('building_id', $buildingTwo->id)->first()->pivot->ownership_percentage,
        );
    }

    public function test_a_building_can_have_multiple_owners_with_a_percentage_split(): void
    {
        $building = Building::factory()->create();
        $ownerOne = Party::factory()->owner()->create();
        $ownerTwo = Party::factory()->owner()->create();

        $building->owners()->attach([
            $ownerOne->id => ['ownership_percentage' => 60],
            $ownerTwo->id => ['ownership_percentage' => 40],
        ]);

        $this->assertCount(2, $building->owners);
        $this->assertSame(
            100.0,
            (float) $building->owners->sum(fn (Party $owner): float => (float) $owner->pivot->ownership_percentage),
        );
    }
}
