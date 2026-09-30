<?php declare(strict_types=1);

namespace CustomerGauge\Redshift;

use CustomerGauge\Redshift\Resolvers\PasswordSource;
use PDO;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final class SecretPasswordChain
{
    public function __construct(
        private PasswordSource $resolver,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param callable(string): PDO $connect
     */
    public function connect(string $secret, callable $connect): PDO
    {
        $tried = [];
        $lastAuthFailure = null;
        $firstCandidate = true;

        foreach ($this->candidates($secret) as $candidate) {
            if (in_array($candidate['password'], $tried, true)) {
                $firstCandidate = false;
                continue;
            }

            $tried[] = $candidate['password'];

            try {
                $pdo = $connect($candidate['password']);
            } catch (Throwable $e) {
                if (! AuthFailure::shouldRefreshSecret($e)) {
                    throw $e;
                }

                $lastAuthFailure = $e;
                $firstCandidate = false;
                continue;
            }

            if (! $firstCandidate) {
                $this->logger->warning(sprintf(
                    'Redshift connected using a fallback secret version. secret=%s stage=%s',
                    $secret,
                    $candidate['stage'],
                ));
            }

            return $pdo;
        }

        throw $lastAuthFailure ?? new RuntimeException('Unable to resolve a Redshift password candidate.');
    }

    /**
     * @return \Generator<int, array{password: string, stage: string}>
     */
    private function candidates(string $secret): \Generator
    {
        yield [
            'password' => $this->resolver->resolve($secret, false),
            'stage' => 'AWSCURRENT',
        ];

        $current = $this->resolver->resolveStage($secret, 'AWSCURRENT');

        if ($current !== null) {
            yield [
                'password' => $current['password'],
                'stage' => 'AWSCURRENT',
            ];
        }

        $pending = $this->resolver->resolveStage($secret, 'AWSPENDING');

        if ($pending === null) {
            return;
        }

        if ($current !== null && $pending['versionId'] === $current['versionId']) {
            return;
        }

        yield [
            'password' => $pending['password'],
            'stage' => 'AWSPENDING',
        ];
    }
}
