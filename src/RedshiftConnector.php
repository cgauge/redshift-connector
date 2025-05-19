<?php declare(strict_types=1);

namespace CustomerGauge\Redshift;

use Exception;
use Illuminate\Database\Connectors\PostgresConnector;
use PDOException;

final class RedshiftConnector extends PostgresConnector
{
    public function __construct(private PasswordResolver $resolver)
    {}

    public function createConnection($dsn, array $config, array $options)
    {
        // If the developer explicitly set the `password` attribute on the database
        // configuration, we'll go ahead and establish a regular connection. This
        // is useful for automation tests that bypass the connection process.
        if (! empty($config['password'])) {
            return parent::createConnection($dsn, $config, $options);
        }

        $execute = function (int $attempt) use ($dsn, $config, $options) {
            if (! isset($config['redshift']['secret'])) {
                throw new Exception('The secret name must be defined on database.{connection}.redshift.secret');
            }

            // The Password Resolver extension will keep a cache of the password.
            // If Laravel throws an exception because of wrong password, then
            // we can retry but ask the extension to refresh the cache.
            $freshSecret = $attempt > 1;

            $config['password'] = $this->resolver->resolve($config['redshift']['secret'], $freshSecret);

            return parent::createConnection($dsn, $config, $options);
        };

        $condition = fn (Exception $e) => $e instanceof PDOException && str_contains($e->getMessage(), 'Access denied for user');

        return retry(when: $condition, callback: $execute, times: 3);
    }
}
