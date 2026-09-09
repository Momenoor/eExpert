<?php

namespace Tests\Unit;

use App\Services\LoanScheduleService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The 50 AED rounding rule for staff loans and petty cash advances.
 *
 * Two properties matter more than any individual figure and are asserted for
 * every case below: the instalments must sum back to the principal exactly, and
 * every instalment after the first must be a whole multiple of 50. Rounding
 * schemes fail by leaking fils, and a schedule that recovers 9,999.99 of a
 * 10,000 advance leaves a loan that never closes.
 */
class LoanScheduleTest extends TestCase
{
    private LoanScheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LoanScheduleService;
    }

    /**
     * @return array<string, array{0: float, 1: int, 2: list<float>}>
     */
    public static function scheduleProvider(): array
    {
        return [
            // The worked example from the specification.
            '10,000 over 6 months' => [10000.0, 6, [1750.0, 1650.0, 1650.0, 1650.0, 1650.0, 1650.0]],

            // Divides evenly into 50s, so there is no remainder to carry.
            '6,000 over 6 months' => [6000.0, 6, [1000.0, 1000.0, 1000.0, 1000.0, 1000.0, 1000.0]],

            // 1,234/4 = 308.50, rounded down to 300; 1,234 − 900 = 334 in month one.
            '1,234 over 4 months' => [1234.0, 4, [334.0, 300.0, 300.0, 300.0]],

            // A single instalment is the whole principal, rounding or not.
            '875 over 1 month' => [875.0, 1, [875.0]],

            // Guard: 300/12 rounds to zero, so the term shortens to six 50s.
            '300 over 12 months collapses' => [300.0, 12, [50.0, 50.0, 50.0, 50.0, 50.0, 50.0]],

            // Guard: below one step, the advance is recovered in one go.
            '30 over 3 months collapses to one' => [30.0, 3, [30.0]],
        ];
    }

    /**
     * @param  list<float>  $expected
     */
    #[DataProvider('scheduleProvider')]
    public function test_it_plans_the_expected_instalments(float $principal, int $months, array $expected): void
    {
        $schedule = $this->service->plan($principal, $months, '2026-01');

        $this->assertSame($expected, array_column($schedule, 'amount'));
    }

    /**
     * @param  list<float>  $expected
     */
    #[DataProvider('scheduleProvider')]
    public function test_the_instalments_always_recover_the_principal_exactly(float $principal, int $months, array $expected): void
    {
        $schedule = $this->service->plan($principal, $months, '2026-01');

        $this->assertEqualsWithDelta($principal, array_sum(array_column($schedule, 'amount')), 0.005);
    }

    /**
     * @param  list<float>  $expected
     */
    #[DataProvider('scheduleProvider')]
    public function test_every_instalment_after_the_first_is_a_whole_fifty(float $principal, int $months, array $expected): void
    {
        $schedule = $this->service->plan($principal, $months, '2026-01');

        $this->assertCount(count($expected), $schedule);

        foreach (array_slice($schedule, 1) as $installment) {
            $this->assertSame(
                0.0,
                fmod($installment['amount'], $this->service->roundingStep()),
                "Instalment {$installment['seq']} of {$installment['amount']} is not a multiple of 50.",
            );
        }
    }

    public function test_the_remainder_is_charged_in_the_first_month_not_the_last(): void
    {
        $schedule = $this->service->plan(10000.0, 6, '2026-01');

        $this->assertSame(1750.0, $schedule[0]['amount']);
        $this->assertSame(1650.0, $schedule[5]['amount']);
    }

    public function test_instalments_fall_in_consecutive_months_from_the_start_period(): void
    {
        $schedule = $this->service->plan(6000.0, 4, '2026-11');

        $this->assertSame(
            ['2026-11', '2026-12', '2027-01', '2027-02'],
            array_column($schedule, 'due_period'),
        );
    }

    public function test_it_refuses_a_principal_of_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->plan(0.0, 6, '2026-01');
    }

    public function test_it_refuses_a_term_of_no_months(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->plan(1000.0, 0, '2026-01');
    }
}
