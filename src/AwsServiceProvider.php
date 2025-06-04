<?php declare(strict_types=1);

namespace CustomerGauge\Redshift;


use Illuminate\Support\ServiceProvider;
use Aws\SecretsManager\SecretsManagerClient;
use Aws\RedshiftServerless\RedshiftServerlessClient;

final class AwsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(SecretsManagerClient::class, function () {
            $config = $this->app['config']->get('aws');

            return new SecretsManagerClient([
                'version' => '2017-10-17',
                'region' => $config['region'],
            ]);
        });

        $this->app->singleton(RedshiftServerlessClient::class, function () {
            $config = $this->app['config']->get('aws');

            return new RedshiftServerlessClient([
                'version' => '2021-04-21',
                'region' => $config['region'],
            ]);
        });
    }
}