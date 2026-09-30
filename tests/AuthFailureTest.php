<?php declare(strict_types=1);

namespace Tests\CustomerGauge\Redshift;

use CustomerGauge\Redshift\AuthFailure;
use Exception;
use PDOException;
use PHPUnit\Framework\TestCase;

class AuthFailureTest extends TestCase
{
    public function test_access_denied_should_refresh_secret()
    {
        $e = new PDOException("SQLSTATE[28000] [1045] Access denied for user 'foo'@'%' (using password: YES)");

        $this->assertTrue(AuthFailure::shouldRefreshSecret($e));
    }

    public function test_password_authentication_failed_should_refresh_secret()
    {
        $e = new PDOException('SQLSTATE[08006] [7] FATAL: password authentication failed for user "tenant-user"');

        $this->assertTrue(AuthFailure::shouldRefreshSecret($e));
    }

    public function test_account_locked_should_not_refresh_secret()
    {
        $e = new PDOException('Account locked due to multiple failed login attempts');

        $this->assertFalse(AuthFailure::shouldRefreshSecret($e));
    }

    public function test_non_pdo_should_not_refresh_secret()
    {
        $this->assertFalse(AuthFailure::shouldRefreshSecret(new Exception('Access denied for user')));
    }
}
