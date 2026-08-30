<?php

namespace Tests\Feature\Filament\Resources\Types;

use App\Filament\Resources\Types\Pages\CreateType;
use App\Filament\Resources\Types\TypeResource;
use App\Models\Type;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TypeResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');
        $this->actingAs($this->admin);

        Filament::setCurrentPanel('admin');
    }

    public function test_can_render_list_page(): void
    {
        $this->get(TypeResource::getUrl('index'))->assertSuccessful();
    }

    public function test_can_render_create_page(): void
    {
        $this->get(TypeResource::getUrl('create'))->assertSuccessful();
    }

    public function test_can_render_edit_page(): void
    {
        $type = Type::create([
            'name' => 'Test Matter Type',
            'incentive_trigger_type' => 'final_report_date',
            'active' => true,
        ]);

        $this->get(TypeResource::getUrl('edit', ['record' => $type]))->assertSuccessful();
    }

    public function test_can_render_view_page(): void
    {
        $type = Type::create([
            'name' => 'Test Matter Type',
            'incentive_trigger_type' => 'final_report_date',
            'active' => true,
        ]);

        $this->get(TypeResource::getUrl('view', ['record' => $type]))->assertSuccessful();
    }

    public function test_can_create_type(): void
    {
        Livewire::test(CreateType::class)
            ->fillForm([
                'name' => 'Commercial Type',
                'incentive_trigger_type' => 'final_report_date',
                'active' => true,
                'allow_current_status_import' => false,
                'exclude_from_incentive_count' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Type::class, [
            'name' => 'Commercial Type',
            'incentive_trigger_type' => 'final_report_date',
        ]);
    }

    public function test_can_translate_matter_type_labels_in_arabic(): void
    {
        app()->setLocale('ar');

        $this->assertSame('نوع القضية', __('Matter Type'));
        $this->assertSame('أنواع القضايا', __('Matter Types'));
        $this->assertSame('نوع استحقاق الحافز', __('Incentive Trigger Type'));
        $this->assertSame('إيداع التقرير النهائي للدعوى', __('Matter Final Reported'));
        $this->assertSame('تحصيل الأتعاب', __('Fees Collected'));
        $this->assertSame('السماح بالاستيراد بالحالة الحالية', __('Allow Current Status Import'));
        $this->assertSame('استبعاد من عدد احتساب الحوافز', __('Exclude from Incentive Count'));
        $this->assertSame('تهيئة الحافز', __('Incentive Configuration'));
    }
}
