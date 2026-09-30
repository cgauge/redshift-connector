<?php declare(strict_types=1);

namespace Tests\CustomerGauge\Redshift;

use CustomerGauge\Redshift\Resolvers\PasswordSource;
use CustomerGauge\Redshift\SecretPasswordChain;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SecretPasswordChainTest extends TestCase
{
    public function test_cache_password_connects_without_api_calls()
    {
        $pdo = $this->createMock(PDO::class);
        $resolver = $this->createMock(PasswordSource::class);
        $logger = $this->createMock(LoggerInterface::class);

        $resolver->expects($this->once())
            ->method('resolve')
            ->with('secret', false)
            ->willReturn('cached');

        $resolver->expects($this->never())->method('resolveStage');
        $logger->expects($this->never())->method('warning');

        $connects = 0;

        $result = (new SecretPasswordChain($resolver, $logger))->connect(
            'secret',
            function (string $password) use (&$connects, $pdo) {
                $connects++;
                $this->assertSame('cached', $password);

                return $pdo;
            },
        );

        $this->assertSame($pdo, $result);
        $this->assertSame(1, $connects);
    }

    public function test_stale_cache_then_fresh_awscurrent_connects()
    {
        $pdo = $this->createMock(PDO::class);
        $resolver = $this->createMock(PasswordSource::class);
        $logger = $this->createMock(LoggerInterface::class);

        $resolver->expects($this->once())
            ->method('resolve')
            ->with('secret', false)
            ->willReturn('stale');

        $resolver->expects($this->once())
            ->method('resolveStage')
            ->with('secret', 'AWSCURRENT')
            ->willReturn(['password' => 'current', 'versionId' => 'v-current']);

        $logger->expects($this->once())
            ->method('warning')
            ->with($this->callback(function (string $message) {
                return str_contains($message, 'secret')
                    && str_contains($message, 'AWSCURRENT')
                    && ! str_contains($message, 'current')
                    && ! str_contains($message, 'stale');
            }));

        $passwords = [];

        $result = (new SecretPasswordChain($resolver, $logger))->connect(
            'secret',
            function (string $password) use (&$passwords, $pdo) {
                $passwords[] = $password;

                if ($password === 'stale') {
                    throw new PDOException('password authentication failed');
                }

                return $pdo;
            },
        );

        $this->assertSame($pdo, $result);
        $this->assertSame(['stale', 'current'], $passwords);
    }

    public function test_duplicate_awscurrent_is_skipped_and_awspending_connects_once()
    {
        $pdo = $this->createMock(PDO::class);
        $resolver = $this->createMock(PasswordSource::class);
        $logger = $this->createMock(LoggerInterface::class);

        $resolver->expects($this->once())
            ->method('resolve')
            ->with('secret', false)
            ->willReturn('old');

        $resolver->expects($this->exactly(2))
            ->method('resolveStage')
            ->willReturnMap([
                ['secret', 'AWSCURRENT', ['password' => 'old', 'versionId' => 'v-current']],
                ['secret', 'AWSPENDING', ['password' => 'pending', 'versionId' => 'v-pending']],
            ]);

        $logger->expects($this->once())
            ->method('warning')
            ->with($this->callback(function (string $message) {
                return str_contains($message, 'secret')
                    && str_contains($message, 'AWSPENDING')
                    && ! str_contains($message, 'old')
                    && ! str_contains($message, 'pending');
            }));

        $passwords = [];

        $result = (new SecretPasswordChain($resolver, $logger))->connect(
            'secret',
            function (string $password) use (&$passwords, $pdo) {
                $passwords[] = $password;

                if ($password === 'old') {
                    throw new PDOException('password authentication failed');
                }

                return $pdo;
            },
        );

        $this->assertSame($pdo, $result);
        $this->assertSame(['old', 'pending'], $passwords);
    }

    public function test_missing_awspending_rethrows_original_auth_exception_without_extra_connect()
    {
        $resolver = $this->createMock(PasswordSource::class);
        $logger = $this->createMock(LoggerInterface::class);
        $original = new PDOException('password authentication failed');

        $resolver->expects($this->once())
            ->method('resolve')
            ->with('secret', false)
            ->willReturn('old');

        $resolver->expects($this->exactly(2))
            ->method('resolveStage')
            ->willReturnMap([
                ['secret', 'AWSCURRENT', ['password' => 'old', 'versionId' => 'v-current']],
                ['secret', 'AWSPENDING', null],
            ]);

        $connects = 0;

        try {
            (new SecretPasswordChain($resolver, $logger))->connect(
                'secret',
                function () use (&$connects, $original) {
                    $connects++;

                    throw $original;
                },
            );

            $this->fail('Expected the original auth exception to be rethrown.');
        } catch (PDOException $e) {
            $this->assertSame($original, $e);
            $this->assertSame(1, $connects);
        }
    }

    public function test_awspending_with_same_version_id_as_awscurrent_is_not_attempted()
    {
        $resolver = $this->createMock(PasswordSource::class);
        $logger = $this->createMock(LoggerInterface::class);
        $original = new PDOException('password authentication failed');

        $resolver->expects($this->once())
            ->method('resolve')
            ->with('secret', false)
            ->willReturn('old');

        $resolver->expects($this->exactly(2))
            ->method('resolveStage')
            ->willReturnMap([
                ['secret', 'AWSCURRENT', ['password' => 'old', 'versionId' => 'same-id']],
                ['secret', 'AWSPENDING', ['password' => 'other-password', 'versionId' => 'same-id']],
            ]);

        $passwords = [];

        try {
            (new SecretPasswordChain($resolver, $logger))->connect(
                'secret',
                function (string $password) use (&$passwords, $original) {
                    $passwords[] = $password;

                    throw $original;
                },
            );

            $this->fail('Expected the original auth exception to be rethrown.');
        } catch (PDOException $e) {
            $this->assertSame($original, $e);
            $this->assertSame(['old'], $passwords);
        }
    }

    public function test_lockout_stops_without_further_candidates_or_api_calls()
    {
        $resolver = $this->createMock(PasswordSource::class);
        $logger = $this->createMock(LoggerInterface::class);

        $resolver->expects($this->once())
            ->method('resolve')
            ->with('secret', false)
            ->willReturn('cached');

        $resolver->expects($this->never())->method('resolveStage');
        $logger->expects($this->never())->method('warning');

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('Account locked due to multiple failed login attempts');

        (new SecretPasswordChain($resolver, $logger))->connect(
            'secret',
            function () {
                throw new PDOException('Account locked due to multiple failed login attempts');
            },
        );
    }

    public function test_non_auth_pdo_exception_stops_without_further_candidates()
    {
        $resolver = $this->createMock(PasswordSource::class);
        $logger = $this->createMock(LoggerInterface::class);

        $resolver->expects($this->once())
            ->method('resolve')
            ->with('secret', false)
            ->willReturn('cached');

        $resolver->expects($this->never())->method('resolveStage');

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('timeout expired');

        (new SecretPasswordChain($resolver, $logger))->connect(
            'secret',
            function () {
                throw new PDOException('SQLSTATE[HYT00] [7] timeout expired');
            },
        );
    }
}
