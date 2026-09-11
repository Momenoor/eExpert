<?php

namespace Tests\Unit;

use App\Enums\SalaryComponent;
use Tests\TestCase;

class SalaryComponentTest extends TestCase
{
    public function test_basic_and_every_allowance_but_car_post_to_the_same_salaries_expense_account(): void
    {
        foreach ([
            SalaryComponent::BASIC,
            SalaryComponent::HOUSING,
            SalaryComponent::TRANSPORT,
            SalaryComponent::UTILITIES,
            SalaryComponent::OTHER,
        ] as $component) {
            $this->assertSame('Salaries Expense', $component->glAccount());
        }
    }

    public function test_car_allowance_posts_to_its_own_account(): void
    {
        $this->assertSame('Car Allowance Expense', SalaryComponent::CAR->glAccount());
    }

    public function test_car_allowance_is_still_an_allowance(): void
    {
        $this->assertTrue(SalaryComponent::CAR->isAllowance());
        $this->assertContains(SalaryComponent::CAR, SalaryComponent::allowances());
    }
}
