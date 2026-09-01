<?php

namespace Tests\Unit;

use App\Support\Sql;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sql::daysSince() used to ask the DATABASE for "today" (SQLite's date('now'),
 * MySQL's CURDATE()) — each engine's own clock, in whatever timezone THAT
 * engine defaults to, independent of config('app.timezone'). SQLite's date
 * functions always run in UTC; the app runs in Asia/Muscat (UTC+4). For the
 * four hours after local midnight, Asia/Muscat's calendar date is already a
 * day ahead of UTC's, so every "days since" figure came back one short for
 * that whole window — not a rare edge case, but a guaranteed daily one.
 *
 * The same class of drift threatens MySQL in production: CURDATE() follows
 * MySQL's own session `time_zone`, a separate setting from Laravel's that a
 * host can leave unpinned.
 */
class SqlDaysSinceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_days_since_uses_the_apps_timezone_not_the_databases_own_clock(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'this pins the sqlite branch specifically');

        // 00:30 on 2026-09-02 in Asia/Muscat is still 2026-09-01 in UTC — the
        // exact daily window the bug lived in.
        Carbon::setTestNow(Carbon::parse('2026-09-02 00:30:00', 'Asia/Muscat'));

        DB::statement('CREATE TEMPORARY TABLE t_days_since (d DATE)');
        DB::table('t_days_since')->insert(['d' => '2026-08-18']);

        $days = DB::table('t_days_since')
            ->selectRaw(Sql::daysSince('d').' as days')
            ->value('days');

        // From the app's 2026-09-02, not the database's UTC 2026-09-01.
        $this->assertSame(15, (int) $days);
    }

    public function test_days_since_is_zero_for_a_date_of_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-02 00:30:00', 'Asia/Muscat'));

        DB::statement('CREATE TEMPORARY TABLE t_days_since_today (d DATE)');
        DB::table('t_days_since_today')->insert(['d' => now()->toDateString()]);

        $days = DB::table('t_days_since_today')
            ->selectRaw(Sql::daysSince('d').' as days')
            ->value('days');

        $this->assertSame(0, (int) $days);
    }
}
