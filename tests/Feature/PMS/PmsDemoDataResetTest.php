<?php

namespace Tests\Feature\PMS;

use App\Enums\PMS\LeaseStatus;
use App\Models\Installment;
use App\Models\InstallmentPayment;
use App\Models\Lease;
use App\Models\LeasePrintTemplateField;
use App\Models\OwnerGroupBankAccount;
use App\Models\OwnerProfile;
use App\Models\Party;
use App\Models\Property;
use App\Models\Quotation;
use App\Models\Tenant;
use App\Models\Unit;
use Database\Seeders\PMSDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PmsDemoDataResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_seeder_populates_every_pms_table(): void
    {
        $this->seed(PMSDemoSeeder::class);

        $this->assertGreaterThan(0, Property::count());
        $this->assertGreaterThan(0, Unit::count());
        $this->assertGreaterThan(0, Tenant::count());
        $this->assertGreaterThan(0, Lease::count());
        $this->assertGreaterThan(0, Quotation::count());
        $this->assertGreaterThan(0, OwnerProfile::count());
        $this->assertGreaterThan(0, OwnerGroupBankAccount::count());
        $this->assertGreaterThan(0, Installment::count());
        $this->assertGreaterThan(0, InstallmentPayment::count());
        $this->assertGreaterThan(0, LeasePrintTemplateField::count());

        $this->assertTrue(OwnerProfile::where('is_primary', true)->exists());
        $this->assertTrue(Lease::where('status', LeaseStatus::DRAFT)->exists());
        $this->assertTrue(Lease::where('status', LeaseStatus::TERMINATED)->exists());
        $this->assertTrue(Lease::where('status', LeaseStatus::ACTIVE)->exists());
    }

    public function test_the_reset_command_truncates_pms_tables_back_to_id_1_and_reseeds(): void
    {
        $this->seed(PMSDemoSeeder::class);
        $partiesBefore = Party::count();

        Artisan::call('pms:reset-demo-data', ['--force' => true]);

        $this->assertGreaterThan(0, Property::count());
        $this->assertSame(1, Property::orderBy('id')->value('id'));
        $this->assertSame(1, Lease::orderBy('id')->value('id'));
        $this->assertSame(1, Unit::orderBy('id')->value('id'));

        // The shared parties table is untouched — reseeding only adds rows.
        $this->assertGreaterThan($partiesBefore, Party::count());
    }

    public function test_the_reset_command_can_be_run_twice_in_a_row(): void
    {
        $this->seed(PMSDemoSeeder::class);

        Artisan::call('pms:reset-demo-data', ['--force' => true]);
        Artisan::call('pms:reset-demo-data', ['--force' => true]);

        $this->assertSame(1, Property::orderBy('id')->value('id'));
        $this->assertGreaterThan(0, Lease::count());
    }
}
