<?php

namespace Omnistate\Vies\Tests;

use Omnistate\Exception\OmnistateException;
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

    /**
     * A check VIES refuses is not an invalid number: the question refused (INVALID_INPUT,
     * INVALID_REQUESTER_INFO, VAT_BLOCKED) is an OmnistateException with VIES's code, any other
     * error an UnavailableException; only VIES's "valid" says whether the number exists. The
     * codes are VIES's fault strings (checkVatService.wsdl, and the REST API's errorWrappers):
     * https://ec.europa.eu/taxation_customs/vies/#/technical-information
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('errors')]
    public function testAnErrorIsNeverAnInvalidNumber(string $answer, int $status, bool $refused): void
    {
        $vies = new Vies(new MockHttpClient(new MockResponse($answer, ['http_code' => $status])));

        try {
            $check = $vies->check('FR53901821074', 'DE123456789');
            self::fail(sprintf('An error read as a check (valid: %s).', var_export($check->valid, true)));
        } catch (UnavailableException $e) {
            self::assertFalse($refused, 'a refused question, not an unavailable VIES: '.$e->getMessage());
        } catch (OmnistateException $e) {
            self::assertTrue($refused, 'an unavailable VIES, not a refused question: '.$e->getMessage());
            self::assertMatchesRegularExpression('/INVALID_INPUT|INVALID_REQUESTER_INFO|VAT_BLOCKED/', $e->getMessage());
        }
    }

    public static function errors(): iterable
    {
        yield 'INVALID_REQUESTER_INFO' => ['{"actionSucceed":false,"errorWrappers":[{"error":"INVALID_REQUESTER_INFO"}]}', 400, true];
        yield 'INVALID_INPUT' => ['{"actionSucceed":false,"errorWrappers":[{"error":"INVALID_INPUT","message":"The provided CountryCode is invalid or the VAT number is empty"}]}', 400, true];
        yield 'VAT_BLOCKED' => ['{"actionSucceed":false,"errorWrappers":[{"error":"VAT_BLOCKED"}]}', 200, true];
        yield 'INVALID_REQUESTER_INFO as userError' => ['{"valid":false,"userError":"INVALID_REQUESTER_INFO"}', 200, true];
        yield 'MS_MAX_CONCURRENT_REQ_TIME' => ['{"actionSucceed":false,"errorWrappers":[{"error":"MS_MAX_CONCURRENT_REQ_TIME"}]}', 500, false];
        yield 'GLOBAL_MAX_CONCURRENT_REQ_TIME' => ['{"actionSucceed":false,"errorWrappers":[{"error":"GLOBAL_MAX_CONCURRENT_REQ_TIME"}]}', 500, false];
        yield 'a code not known yet' => ['{"valid":false,"userError":"SOMETHING_NEW"}', 200, false];
    }

    public function testVIESsOwnVerdictIsTheAnswer(): void
    {
        $answer = static fn (bool $valid, string $verdict) => new MockResponse(json_encode(['countryCode' => 'FR', 'vatNumber' => '53901821074', 'requestDate' => '2026-10-29T09:00:00.000Z', 'valid' => $valid, 'userError' => $verdict, 'name' => '---', 'address' => '---']));

        self::assertTrue((new Vies(new MockHttpClient($answer(true, 'VALID'))))->check('FR53901821074')->valid);
        self::assertFalse((new Vies(new MockHttpClient($answer(false, 'INVALID'))))->check('FR53901821074')->valid);
    }
}
