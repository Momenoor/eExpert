<?php

namespace Tests\Feature\PMS;

use App\Models\Building;
use App\Models\OwnerGroup;
use App\Models\OwnerProfile;
use App\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `Building::landlordName()` shows a shared estate's collective name (e.g.
 * "Legal Heirs of Mahmoud Kalbat") when every owner on the building belongs
 * to the same `OwnerGroup`, and falls back to listing each owner's own name
 * otherwise.
 */
class OwnerGroupTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithGroup(?OwnerGroup $group = null): Party
    {
        $party = Party::factory()->owner()->create();

        OwnerProfile::create([
            'party_id' => $party->id,
            'owner_group_id' => $group?->id,
        ]);

        return $party;
    }

    public function test_landlord_name_is_the_group_name_when_every_owner_shares_one(): void
    {
        $group = OwnerGroup::factory()->create(['name' => 'Legal Heirs of Mahmoud Kalbat']);

        $sonOne = $this->ownerWithGroup($group);
        $sonTwo = $this->ownerWithGroup($group);

        $building = Building::factory()->create();
        $building->owners()->attach([
            $sonOne->id => ['ownership_percentage' => 50],
            $sonTwo->id => ['ownership_percentage' => 50],
        ]);

        $this->assertSame('Legal Heirs of Mahmoud Kalbat', $building->landlordName());
    }

    public function test_landlord_name_falls_back_to_individual_names_when_owners_have_no_shared_group(): void
    {
        $ownerOne = $this->ownerWithGroup();
        $ownerTwo = $this->ownerWithGroup();

        $building = Building::factory()->create();
        $building->owners()->attach([
            $ownerOne->id => ['ownership_percentage' => 60],
            $ownerTwo->id => ['ownership_percentage' => 40],
        ]);

        $this->assertSame(
            "{$ownerOne->name}, {$ownerTwo->name}",
            $building->landlordName(),
        );
    }

    public function test_landlord_name_falls_back_when_owners_belong_to_different_groups(): void
    {
        $groupOne = OwnerGroup::factory()->create();
        $groupTwo = OwnerGroup::factory()->create();

        $ownerOne = $this->ownerWithGroup($groupOne);
        $ownerTwo = $this->ownerWithGroup($groupTwo);

        $building = Building::factory()->create();
        $building->owners()->attach([
            $ownerOne->id => ['ownership_percentage' => 50],
            $ownerTwo->id => ['ownership_percentage' => 50],
        ]);

        $this->assertSame(
            "{$ownerOne->name}, {$ownerTwo->name}",
            $building->landlordName(),
        );
    }

    public function test_a_single_owner_with_no_group_shows_their_own_name(): void
    {
        $owner = $this->ownerWithGroup();

        $building = Building::factory()->create();
        $building->owners()->attach($owner->id, ['ownership_percentage' => 100]);

        $this->assertSame($owner->name, $building->landlordName());
    }

    public function test_a_building_with_no_owners_has_a_blank_landlord_name(): void
    {
        $building = Building::factory()->create();

        $this->assertSame('', $building->landlordName());
    }
}
