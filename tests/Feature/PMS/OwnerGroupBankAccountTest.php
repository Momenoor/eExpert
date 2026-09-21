<?php

namespace Tests\Feature\PMS;

use App\Filament\Pms\Resources\OwnerGroups\Pages\CreateOwnerGroup;
use App\Filament\Pms\Resources\OwnerGroups\Pages\EditOwnerGroup;
use App\Filament\Pms\Resources\OwnerGroups\Pages\ListOwnerGroups;
use App\Filament\Pms\Resources\OwnerGroups\RelationManagers\PropertiesRelationManager;
use App\Filament\Pms\Resources\Properties\Pages\EditProperty;
use App\Models\OwnerGroup;
use App\Models\OwnerGroupBankAccount;
use App\Models\Party;
use App\Models\Property;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OwnerGroupBankAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('pms'));
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin);
    }

    public function test_a_group_can_be_created_with_several_bank_accounts(): void
    {
        Livewire::test(CreateOwnerGroup::class)
            ->fillForm([
                'name' => 'Legal Heirs of Test',
                'bankAccounts' => [
                    ['bank_name' => 'ADCB', 'account_no' => '111', 'iban' => 'AE070331234567890123456', 'is_default' => true],
                    ['bank_name' => 'Mashreq', 'account_no' => '222'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $group = OwnerGroup::sole();
        $this->assertCount(2, $group->bankAccounts);
    }

    public function test_bank_accounts_can_be_added_when_editing_a_group(): void
    {
        $group = OwnerGroup::factory()->create();

        Livewire::test(EditOwnerGroup::class, ['record' => $group->getKey()])
            ->fillForm(['bankAccounts' => [['bank_name' => 'ENBD', 'account_no' => '9']]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('ENBD', $group->bankAccounts()->sole()->bank_name);
    }

    public function test_a_property_is_linked_to_a_specific_account_of_its_group(): void
    {
        $group = OwnerGroup::factory()->create();
        $first = OwnerGroupBankAccount::factory()->create(['owner_group_id' => $group->id, 'is_default' => true]);
        $second = OwnerGroupBankAccount::factory()->create(['owner_group_id' => $group->id]);
        $property = Property::factory()->create();

        Livewire::test(EditProperty::class, ['record' => $property->getKey()])
            ->fillForm([
                'owners' => [['party_id' => Party::factory()->owner()->create()->id, 'ownership_percentage' => 100]],
                'owner_group_id' => $group->id,
            ])
            ->assertSchemaStateSet(['owner_group_bank_account_id' => $first->id])
            ->fillForm(['owner_group_bank_account_id' => $second->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $property->refresh();
        $this->assertSame($group->id, $property->owner_group_id);
        $this->assertSame($second->id, $property->owner_group_bank_account_id);
    }

    public function test_adding_a_property_from_the_group_page_links_the_chosen_account(): void
    {
        $group = OwnerGroup::factory()->create();
        $account = OwnerGroupBankAccount::factory()->create(['owner_group_id' => $group->id]);
        $property = Property::factory()->create();

        Livewire::test(PropertiesRelationManager::class, ['ownerRecord' => $group, 'pageClass' => EditOwnerGroup::class])
            ->callAction(TestAction::make('add_property')->table(), ['property_id' => $property->id, 'owner_group_bank_account_id' => $account->id])
            ->assertHasNoActionErrors();

        $property->refresh();
        $this->assertSame($group->id, $property->owner_group_id);
        $this->assertSame($account->id, $property->owner_group_bank_account_id);
    }

    public function test_a_property_without_a_group_keeps_no_bank_account(): void
    {
        $group = OwnerGroup::factory()->create();
        $account = OwnerGroupBankAccount::factory()->create(['owner_group_id' => $group->id]);
        $property = Property::factory()->create(['owner_group_id' => $group->id, 'owner_group_bank_account_id' => $account->id]);

        $property->update(['owner_group_id' => null]);

        $this->assertNull($property->fresh()->owner_group_bank_account_id);
    }

    public function test_the_owner_groups_list_loads_sorted_by_name(): void
    {
        $groups = OwnerGroup::factory()->count(2)->create();

        Livewire::test(ListOwnerGroups::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($groups);
    }
}
