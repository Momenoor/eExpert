<?php

namespace Tests\Feature\PMS;

use App\Enums\PMS\LeasePartyRole;
use App\Models\Lease;
use App\Models\LeasePrintTemplate;
use App\Models\LeasePrintTemplateField;
use App\Models\LeasePrintTemplatePage;
use App\Models\Party;
use App\Models\Unit;
use App\Models\User;
use App\Services\PMS\LeaseService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeasePrintTest extends TestCase
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

    private function lease(): Lease
    {
        $unit = Unit::factory()->residential()->create();
        $tenant = Party::factory()->tenant()->create();

        return app(LeaseService::class)->createFromRawInputs([
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'total_base_rent' => 40000,
            'government_contract_number' => 'CN-2026-042',
        ], [
            ['party_id' => $tenant->id, 'role' => LeasePartyRole::PRIMARY_TENANT->value],
        ], [$unit->id]);
    }

    public function test_it_shows_a_not_configured_message_when_the_template_has_no_pages(): void
    {
        $lease = $this->lease();

        $response = $this->get(route('pms.leases.print', ['lease' => $lease, 'format' => 'sharjah_residential']));

        $response->assertOk();
        $response->assertSee("hasn't been set up yet", false);
    }

    public function test_it_shows_a_not_configured_message_when_the_format_has_no_template_at_all(): void
    {
        $lease = $this->lease();

        $response = $this->get(route('pms.leases.print', ['lease' => $lease, 'format' => 'unknown_format']));

        $response->assertOk();
        $response->assertSee("hasn't been set up yet", false);
    }

    public function test_it_renders_the_background_image_and_placed_field_values(): void
    {
        Storage::fake('public');

        $lease = $this->lease();

        $template = LeasePrintTemplate::create(['name' => 'Test', 'contract_format' => 'sharjah_residential']);
        $image = UploadedFile::fake()->image('page1.png', 1000, 1400);
        $path = $image->store('lease-print-templates', 'public');

        $page = LeasePrintTemplatePage::create([
            'lease_print_template_id' => $template->id,
            'page_number' => 1,
            'background_image_path' => $path,
            'image_width' => 1000,
            'image_height' => 1400,
        ]);

        LeasePrintTemplateField::create([
            'lease_print_template_page_id' => $page->id,
            'field_key' => 'government_contract_number',
            'x_percent' => 25.5,
            'y_percent' => 10,
            'font_size' => 12,
            'text_align' => 'left',
        ]);

        $response = $this->get(route('pms.leases.print', ['lease' => $lease, 'format' => 'sharjah_residential']));

        $response->assertOk();
        $response->assertSee('CN-2026-042');
        $response->assertSee('25.500%', false);
        $response->assertDontSee("hasn't been set up yet", false);
    }

    public function test_a_user_without_view_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $lease = $this->lease();

        $response = $this->get(route('pms.leases.print', ['lease' => $lease, 'format' => 'sharjah_residential']));

        $response->assertForbidden();
    }
}
