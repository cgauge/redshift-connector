<?php declare(strict_types=1);

namespace CustomerGauge\Redshift\Resolvers;

use Aws\RedshiftServerless\RedshiftServerlessClient;

final class TemporaryCredentialResolver
{
    public function __construct(private RedshiftServerlessClient $client)
    {}

    public function resolve(array $config): array
    {
        $validated = $this->validate($config);

        $response = $this->client->getCredentialsAsync($validated)->wait();

        return [
            'username' => $response['dbUser'],
            'password' => $response['dbPassword'],
        ];
    }

    private function validate(array $config): array
    {
        if (! isset($config['workgroupName']) || empty($config['workgroupName'])) {
            throw new \InvalidArgumentException('The workgroupName must be defined on database.{connection}.redshift.temporary_credential.workgroupName');
        }

        return array_filter($config);
    }
}
