<?php declare(strict_types=1);

namespace CustomerGauge\Redshift;

use PDOException;
use Throwable;

final class AuthFailure
{
    public static function shouldRefreshSecret(Throwable $e): bool
    {
        if (! $e instanceof PDOException) {
            return false;
        }

        $message = $e->getMessage();

        if (str_contains($message, 'Account locked due to multiple failed login attempts')) {
            return false;
        }

        return str_contains($message, 'Access denied for user')
            || str_contains($message, 'password authentication failed');
    }
}
