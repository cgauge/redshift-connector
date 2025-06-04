<?php declare(strict_types=1);

namespace Tests\CustomerGauge\Redshift;

use Aws\Result;
use Aws\RedshiftServerless\RedshiftServerlessClient;
use CustomerGauge\Redshift\Resolvers\TemporaryCredentialResolver;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Promise\Promise;

class TemporaryCredentialResolverTest extends TestCase
{
    public function test_workgroup_name_is_a_mandatory_configuration()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->expectExceptionMessage('The workgroupName must be defined on database.{connection}.redshift.temporary_credential.workgroupName');

        $client = $this->createMock(RedshiftServerlessClient::class);

        $resolver = new TemporaryCredentialResolver($client);

        $resolver->resolve([]);
    }

    public function test_retrieve_temporary_credentials_for_redshift_serverless()
    {
        $client = $this->createMock(RedshiftServerlessClient::class);

        $client->expects($this->once())
            ->method('__call')
            ->with('getCredentialsAsync', [['workgroupName' => 'test-workgroup']])
            ->willReturn($this->createMockPromise([
                'dbUser' => 'testUser',
                'dbPassword' => 'testPassword',
            ]));

        $resolver = new TemporaryCredentialResolver($client);

        $config = ['workgroupName' => 'test-workgroup', 'durationSeconds' => null];

        $credentials = $resolver->resolve($config);

        $this->assertEquals('testUser', $credentials['username']);

        $this->assertEquals('testPassword', $credentials['password']);
    }

    private function createMockPromise(array $data): Promise
    {
        $result = new Result($data);

        $promise = new Promise;

        $promise->resolve($result);

        return $promise;
    }
}
