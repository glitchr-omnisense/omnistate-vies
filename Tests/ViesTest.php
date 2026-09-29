<?php

namespace Omnistate\Vies\Tests;

use Omnistate\Exception\UnavailableException;
use Omnistate\Vies\Vies;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ViesTest extends TestCase
{
    public function testAValidNumberAndWhoseItIs(): void
    {
        $sent = null;
        $vies = new Vies(new MockHttpClient(function (string $method, string $url, array $options) use (&$sent) {
            $sent = json_decode($options['body'], true);

            return new MockResponse(file_get_contents(__DIR__.'/Fixtures/valid.json'));
        }));
        $check = $vies->check('FR53901821074', 'FR53901821074');

        self::assertTrue($check->valid);
        self::assertSame('SARL GLITCH ART', $check->name);
        self::assertSame('5 RUE VINCENT SCOTTO, 67400 ILLKIRCH GRAFFENSTADEN', $check->address, 'its lines joined');
        self::assertSame('FR', $check->country());
        self::assertSame(['countryCode' => 'FR', 'vatNumber' => '53901821074', 'requesterMemberStateCode' => 'FR', 'requesterNumber' => '53901821074'], $sent);
    }

    public function testAnUnknownNumber(): void
    {
        $check = (new Vies(new MockHttpClient(new MockResponse(file_get_contents(__DIR__.'/Fixtures/invalid.json')))))->check('DE000000000');

        self::assertFalse($check->valid);
        self::assertNull($check->name, '"---" is nothing');
    }

    public function testAMemberStateThatDoesNotAnswerIsNotAWrongNumber(): void
    {
        $vies = new Vies(new MockHttpClient(new MockResponse('{"actionSucceed":false,"errorWrappers":[{"error":"MS_UNAVAILABLE"}]}', ['http_code' => 200])));

        try {
            $vies->check('DE123456789');
            self::fail('unavailable');
        } catch (UnavailableException $e) {
            self::assertSame(600, $e->retryAfter);
        }
    }

    public function testVIESDown(): void
    {
        $this->expectException(UnavailableException::class);
        (new Vies(new MockHttpClient(new MockResponse('', ['error' => 'Could not resolve host']))))->check('DE123456789');
    }
}
