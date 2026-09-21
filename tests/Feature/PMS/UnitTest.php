<?php

namespace Tests\Feature\PMS;

use App\Enums\PMS\PropertyClassification;
use App\Enums\PMS\UnitType;
use App\Models\Property;
use App\Models\Setting;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `Unit::vatRate()` is the single source of truth for VAT applicability —
 * quotation and installment generation both call through it rather than
 * re-deriving the 0%/5% rule themselves.
 */
class UnitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearCache();
    }

    private function unit(PropertyClassification $classification): Unit
    {
        return Unit::factory()->create([
            'property_id' => Property::factory(),
            'property_classification' => $classification,
            'unit_type' => UnitType::OFFICE,
        ]);
    }

    public function test_residential_units_are_vat_exempt(): void
    {
        $unit = $this->unit(PropertyClassification::RESIDENTIAL);

        $this->assertSame(0.0, $unit->vatRate());
        $this->assertFalse($unit->isTaxable());
    }

    public function test_commercial_units_are_taxed_at_five_percent(): void
    {
        $unit = $this->unit(PropertyClassification::COMMERCIAL);

        $this->assertSame(0.05, $unit->vatRate());
        $this->assertTrue($unit->isTaxable());
    }

    public function test_industrial_units_are_taxed_at_five_percent(): void
    {
        $unit = $this->unit(PropertyClassification::INDUSTRIAL);

        $this->assertSame(0.05, $unit->vatRate());
        $this->assertTrue($unit->isTaxable());
    }

    public function test_mixed_use_units_use_the_configurable_setting(): void
    {
        Setting::set('pms_mixed_use_vat_rate', 0.025);

        $unit = $this->unit(PropertyClassification::MIXED_USE);

        $this->assertSame(0.025, $unit->vatRate());
        $this->assertTrue($unit->isTaxable());
    }

    public function test_mixed_use_defaults_to_the_standard_rate_when_unset(): void
    {
        $unit = $this->unit(PropertyClassification::MIXED_USE);

        $this->assertSame(0.05, $unit->vatRate());
    }

    public function test_unit_type_defaults_to_a_sensible_classification(): void
    {
        $this->assertSame(PropertyClassification::RESIDENTIAL, UnitType::STUDIO->defaultClassification());
        $this->assertSame(PropertyClassification::COMMERCIAL, UnitType::SHOP->defaultClassification());
        $this->assertSame(PropertyClassification::INDUSTRIAL, UnitType::WAREHOUSE->defaultClassification());
        $this->assertSame(PropertyClassification::RESIDENTIAL, UnitType::PARKING_BAY->defaultClassification());
    }
}
