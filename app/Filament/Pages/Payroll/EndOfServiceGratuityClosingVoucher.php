<?php

namespace App\Filament\Pages\Payroll;

use App\Services\EndOfServiceGratuityClosingVoucherService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use UnitEnum;

/**
 * The annual EOSG closing voucher, on its own page rather than hanging off a
 * Payroll Run — gratuity is now posted once a year, not once a month, so it
 * has no single run to belong to.
 *
 * Gated purely by this page's own Shield permission (HasPageShield), never by
 * `View:PayrollRun` — a Finance user who should see only the gratuity total,
 * not any employee's monthly salary detail, can be granted this permission
 * alone.
 */
class EndOfServiceGratuityClosingVoucher extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.payroll.eosg-closing-voucher';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Financial';

    protected static ?int $navigationSort = 3;

    public int $year;

    public static function getNavigationLabel(): string
    {
        return __('EOSG Closing Voucher');
    }

    public function getTitle(): string
    {
        return __('EOSG Closing Voucher');
    }

    public function mount(): void
    {
        $this->year = (int) now()->year;
    }

    /**
     * @return array{
     *     period: string,
     *     debits: list<array{account: string, detail: string|null, amount: float}>,
     *     credits: list<array{account: string, detail: string|null, amount: float}>,
     *     total_debit: float,
     *     total_credit: float,
     *     balanced: bool,
     *     employee_count: int,
     * }
     */
    #[Computed]
    public function voucher(): array
    {
        return app(EndOfServiceGratuityClosingVoucherService::class)->forYear($this->year);
    }

    /**
     * Years an office would plausibly want to close: this one, and the ten
     * before it. There is nothing to accrue before the payroll module existed.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function selectableYears(): array
    {
        $current = (int) now()->year;

        return range($current, $current - 10);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label(__('Print Voucher'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->visible(fn (): bool => $this->voucher()['employee_count'] > 0)
                ->url(fn (): string => route('payroll.eosg-closing-voucher.print', ['year' => $this->year]))
                ->openUrlInNewTab(),
        ];
    }
}
