<?php

namespace Tests\Feature\PMS;

use App\Models\Property;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `Property::total_units` is kept in sync with its actual units — never
 * hand-edited on the record, since `PropertyForm` shows it as a read-only
 * entry.
 */
class PropertyTotalUnitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_a_unit_increments_the_propertys_total_units(): void
    {
        $property = Property::factory()->create(['total_units' => 0]);

        Unit::factory()->create(['property_id' => $property->id]);
        Unit::factory()->create(['property_id' => $property->id]);

        $this->assertSame(2, $property->fresh()->total_units);
    }

    public function test_deleting_a_unit_decrements_the_propertys_total_units(): void
    {
        $property = Property::factory()->create(['total_units' => 0]);
        $unit = Unit::factory()->create(['property_id' => $property->id]);
        Unit::factory()->create(['property_id' => $property->id]);

        $unit->delete();

        $this->assertSame(1, $property->fresh()->total_units);
    }

    public function test_restoring_a_soft_deleted_unit_re_counts_it(): void
    {
        $property = Property::factory()->create(['total_units' => 0]);
        $unit = Unit::factory()->create(['property_id' => $property->id]);
        $unit->delete();

        $unit->restore();

        $this->assertSame(1, $property->fresh()->total_units);
    }

    public function test_moving_a_unit_to_another_property_updates_both_counts(): void
    {
        $origin = Property::factory()->create(['total_units' => 0]);
        $destination = Property::factory()->create(['total_units' => 0]);
        $unit = Unit::factory()->create(['property_id' => $origin->id]);

        $unit->update(['property_id' => $destination->id]);

        $this->assertSame(0, $origin->fresh()->total_units);
        $this->assertSame(1, $destination->fresh()->total_units);
    }
}
