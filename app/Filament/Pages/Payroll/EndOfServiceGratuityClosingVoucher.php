<?php

namespace App\Filament\Pages\Payroll;

use App\Services\EndOfServiceGratuityClosingVoucherService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Livewire\Attributes\Computed;

/**
 * The annual EOSG closing voucher, on its own page rather than hanging off a
 * Payroll Run — gratuity is now posted once a year, not once a month, so it
 * has no single run to belong to.
 *
 * Built entirely from the standard Filament schema (a live `Select` filter
 * plus an embedded view), like every other custom page in this app, rather
 * than a hand-rolled Blade view with a raw `<select>` — only the printable
 * voucher (`journal-voucher-print.blade.php`, via the print action below)
 * stays exactly as it was.
 *
 * Gated purely by this page's own Shield permission (HasPageShield), never by
 * `View:PayrollRun` — a Finance user who should see only the gratuity total,
 * not any employee's monthly salary detail, can be granted this permission
 * alone.
 */
class EndOfServiceGratuityClosingVoucher extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 3;

    /**
     * @var array{year?: int}
     */
    public ?array $filters = [];

    public static function getNavigationLabel(): string
    {
        return __('EOSG Closing Voucher');
    }

    public function getTitle(): string
    {
        return __('EOSG Closing Voucher');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Financial');
    }

    public function mount(): void
    {
        $this->filtersForm->fill(['year' => (int) now()->year]);
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('year')
                    ->label(__('Closing Year'))
                    ->options(fn (): array => array_combine($this->selectableYears(), $this->selectableYears()))
                    ->selectablePlaceholder(false)
                    ->live()
                    ->required(),
            ])
            ->statePath('filters');
    }

    private function year(): int
    {
        return (int) ($this->filters['year'] ?? now()->year);
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
        return app(EndOfServiceGratuityClosingVoucherService::class)->forYear($this->year());
    }

    /**
     * Years an office would plausibly want to close: this one, and the ten
     * before it. There is nothing to accrue before the payroll module existed.
     *
     * @return array<int, int>
     */
    public function selectableYears(): array
    {
        $current = (int) now()->year;

        return range($current, $current - 10);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Closing Year'))
                ->schema([
                    Form::make([EmbeddedSchema::make('filtersForm')]),
                ]),

            Section::make(__('Journal Voucher'))
                ->visible(fn (): bool => $this->voucher()['employee_count'] > 0)
                ->schema([
                    View::make('filament.payroll.journal-voucher')
                        ->viewData(fn (): array => ['voucher' => $this->voucher()]),
                ]),

            Section::make(__('Journal Voucher'))
                ->visible(fn (): bool => $this->voucher()['employee_count'] === 0)
                ->schema([
                    Text::make(fn (): string => __(
                        'No gratuity was accrued for :year among employees applicable for EOSG.',
                        ['year' => $this->year()],
                    ))->color('gray'),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label(__('Print Voucher'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->visible(fn (): bool => $this->voucher()['employee_count'] > 0)
                ->url(fn (): string => route('payroll.eosg-closing-voucher.print', ['year' => $this->year()]))
                ->openUrlInNewTab(),
        ];
    }
}
