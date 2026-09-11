<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Deliberately separate from the page's own Shield permission
 * (`View:EndOfServiceGratuityClosingVoucher`), which only gates whether the
 * page can be opened at all. `generate` gates the mutating action inside it —
 * a Finance user granted view-only access to the page must not be able to
 * press the button that rewrites the saved voucher.
 */
class EosgClosingVoucherPolicy
{
    use HandlesAuthorization;

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:EosgClosingVoucher');
    }

    public function generate(AuthUser $authUser): bool
    {
        return $authUser->can('Generate:EosgClosingVoucher');
    }
}
