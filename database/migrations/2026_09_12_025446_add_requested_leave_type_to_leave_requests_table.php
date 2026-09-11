<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_requests', 'requested_leave_type')) {
                // What the employee thinks this absence is, stated at submission —
                // purely informational, and the default an office-side email
                // approval adopts. The approver's own split (LeaveRequestPeriod,
                // decided at approval time) is still the figure that actually
                // counts against a balance or payroll.
                $table->string('requested_leave_type')->nullable()->after('comment');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            if (Schema::hasColumn('leave_requests', 'requested_leave_type')) {
                $table->dropColumn('requested_leave_type');
            }
        });
    }
};
