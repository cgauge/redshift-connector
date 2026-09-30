<?php declare(strict_types=1);

namespace CustomerGauge\Redshift\Resolvers;

interface PasswordSource
{
    public function resolve(string $secret, bool $fresh);

    /**
     * @return array{password: string, versionId: string}|null
     */
    public function resolveStage(string $secret, string $stage): ?array;
}
