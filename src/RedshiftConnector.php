<?php declare(strict_types=1);

namespace CustomerGauge\Redshift;

use CustomerGauge\Redshift\Resolvers\PasswordResolver;
use CustomerGauge\Redshift\Resolvers\TemporaryCredentialResolver;
use Illuminate\Database\Connectors\PostgresConnector;
use PDOException;
use Exception;

final class RedshiftConnector extends PostgresConnector
{
    public function __construct(
        private PasswordResolver $password,
        private TemporaryCredentialResolver $temporary
    ) {}

    public function createConnection($dsn, array $config, array $options)
    {
        // When the `temporary_credential` option is enabled in the database configuration,
        // the application connects to Amazon Redshift using short-lived credentials,
        // typically obtained through AWS IAM authentication mechanisms.

        $temporaryCredential = $config['redshift']['temporary_credential'] ?? null;

        if (is_array($temporaryCredential)) {
            $credentials = $this->temporary->resolve($temporaryCredential);

            $config['username'] = $credentials['username'];
            
            $config['password'] = $credentials['password'];
        }

        // If the developer explicitly set the `password` attribute on the database
        // configuration, we'll go ahead and establish a regular connection. This
        // is useful for automation tests that bypass the connection process.

        if (! empty($config['password'])) {
            return $this->customConnection($dsn, $config, $options);
        }

        $execute = function (int $attempt) use ($dsn, $config, $options) {
            if (! isset($config['redshift']['secret'])) {
                throw new Exception('The secret name must be defined on database.{connection}.redshift.secret');
            }

            // The Password Resolver extension will keep a cache of the password.
            // If Laravel throws an exception because of wrong password, then
            // we can retry but ask the extension to refresh the cache.

            $freshSecret = $attempt > 1;

            $config['password'] = $this->password->resolve($config['redshift']['secret'], $freshSecret);

            return $this->customConnection($dsn, $config, $options);
        };

        $condition = fn (Exception $e) => $e instanceof PDOException && str_contains($e->getMessage(), 'Access denied for user');

        return retry(when: $condition, callback: $execute, times: 3);
    }

    private function customConnection($dsn, array $config, array $options): \PDO
    {
        $connection = parent::createConnection($dsn, $config, $options);

        $timezone = $config['timezone'] ?? null;

        // Redshift does not support connect on timezone using DSN,
        // only through SET timezone {timezone}
        if ($timezone) {
            $connection->exec("SET timezone TO $timezone");
        }

        return $connection;
    }
}
