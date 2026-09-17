<?php

namespace Tests\Feature\PMS;

use App\Filament\Resources\OwnerGroups\Pages\CreateOwnerGroup;
use App\Filament\Resources\OwnerGroups\Pages\EditOwnerGroup;
use App\Models\OwnerGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OwnerGroupResourceTest extends TestCase
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

    public function test_creating_an_owner_group_creates_both_the_party_and_the_group(): void
    {
        Livewire::test(CreateOwnerGroup::class)
            ->fillForm([
                'name' => 'Legal Heirs of Mahmoud Kalbat',
                'phone' => ['0501112222'],
                'iban' => 'AE070331234567890123456',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $group = OwnerGroup::where('name', 'Legal Heirs of Mahmoud Kalbat')->sole();
        $this->assertSame('AE070331234567890123456', $group->iban);

        $party = $group->party;
        $this->assertTrue($party->isOwnerGroup());
        $this->assertSame(['0501112222'], $party->phone);
    }

    public function test_editing_an_owner_group_updates_both_the_party_and_the_group(): void
    {
        $group = OwnerGroup::factory()->create();

        Livewire::test(EditOwnerGroup::class, ['record' => $group->getKey()])
            ->fillForm([
                'name' => 'Legal Heirs of Ahmed',
                'bank_name' => 'Mashreq Bank',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Legal Heirs of Ahmed', $group->fresh()->name);
        $this->assertSame('Legal Heirs of Ahmed', $group->party->fresh()->name);
        $this->assertSame('Mashreq Bank', $group->fresh()->bank_name);
    }
}
