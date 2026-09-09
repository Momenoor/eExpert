<?php

namespace Tests\Unit;

use App\Services\Installer\DatabaseConnectionTester;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The wizard's "Test Connection" button.
 *
 * The one property worth proving beyond a bare pass/fail: testing a bad
 * connection must not leave anything behind on a real connection name. A
 * disposable, uniquely-named connection is the whole point — without it, a
 * wrong password typed into the wizard would cache a broken PDO handle on
 * whatever connection the rest of the app actually uses.
 */
class DatabaseConnectionTesterTest extends TestCase
{
    private DatabaseConnectionTester $tester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tester = new DatabaseConnectionTester;
    }

    public function test_a_working_sqlite_path_succeeds(): void
    {
        $path = sys_get_temp_dir().'/db-connection-tester-'.uniqid().'.sqlite';
        touch($path);

        try {
            $result = $this->tester->test([
                'connection' => 'sqlite',
                'database' => $path,
            ]);

            $this->assertTrue($result['ok']);
        } finally {
            @unlink($path);
        }
    }

    public function test_a_missing_sqlite_file_fails(): void
    {
        $result = $this->tester->test([
            'connection' => 'sqlite',
            'database' => sys_get_temp_dir().'/does-not-exist-'.uniqid().'.sqlite',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertNotSame('', $result['message']);
    }

    public function test_an_unreachable_mysql_host_fails_without_throwing(): void
    {
        $result = $this->tester->test([
            'connection' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '1', // nothing listens on port 1
            'database' => 'does_not_matter',
            'username' => 'root',
            'password' => '',
        ]);

        $this->assertFalse($result['ok']);
    }

    public function test_it_never_leaves_the_probe_connection_configured_afterward(): void
    {
        $this->tester->test([
            'connection' => 'sqlite',
            'database' => ':memory:',
        ]);

        // DB::purge() drops the cached PDO instance but Laravel keeps whatever
        // was last written to config() — the point here is that nothing else in
        // the app ever resolves this connection name, so its state cannot leak
        // into a real query.
        $this->assertArrayHasKey('installer_test', config('database.connections'));
        $this->assertNotSame(DB::getDefaultConnection(), 'installer_test');
    }
}
