<?php declare(strict_types=1);

namespace CustomerGauge\Redshift;

use Illuminate\Database\Connection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\ServiceProvider;

final class RedshiftServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->registerRedshiftDriver();
    }

    private function registerRedshiftDriver(): void
    {
        // We register an `redshift` driver which extends the Postgres drivers from Laravel.
        // Laravel does not support Redshift natively, but it does support Postgres.
        $factory = function ($connection, $database, $prefix, $config) {
            return new PostgresConnection($connection, $database, $prefix, $config);
        };

        Connection::resolverFor('redshift', $factory);

        $this->app->bind('db.connector.redshift', RedshiftConnector::class);
    }
}
