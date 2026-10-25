<?php

namespace Omnistate\Vies\Tests;

use Omnistate\Vies\Vies;
use Omnistate\Testing\RegistryContractTests;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Omnistate's conformance suite, on the register's recorded answers (Tests/Fixtures). */
final class ViesContractTest extends TestCase
{
    use RegistryContractTests;

    protected function registry(HttpClientInterface $http): object
    {
        return new Vies($http);
    }

    protected function recordedAnswers(): array
    {
        return ['*' => static fn (string $method, string $url) => new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/'.match (true) {
            default => 'valid.json',
        }), ['response_headers' => ['content-type' => 'application/json']])];
    }
}
