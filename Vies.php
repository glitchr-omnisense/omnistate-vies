<?php

namespace Omnistate\Vies;

use Omnistate\Exception\UnavailableException;
use Omnistate\Identifier\VatNumber;
use Omnistate\Model\VatCheck;
use Omnistate\Registry\VatRegistryInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The European Commission's VIES: every member state's VAT register behind
 * one door. Its REST API - no SOAP extension needed. Given your own VAT
 * number, it answers a consultation number: the proof you checked your
 * client's number that day, to keep with a reverse-charge invoice.
 *
 * VIES only relays: a member state's service down (they have their hours)
 * is not a number that does not exist - that is an UnavailableException.
 */
final class Vies implements VatRegistryInterface
{
    public const URL = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';

    /** VIES's errors that mean "ask again later", not "no such number". */
    private const UNAVAILABLE = ['MS_UNAVAILABLE', 'TIMEOUT', 'SERVICE_UNAVAILABLE', 'MS_MAX_CONCURRENT_REQ', 'GLOBAL_MAX_CONCURRENT_REQ', 'SERVER_BUSY', 'IP_BLOCKED'];

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly float $timeout = 10,
    ) {
    }

    public function name(): string
    {
        return 'vies';
    }

    public function supports(string $number): bool
    {
        return isset(VatNumber::FORMATS[VatNumber::country($number)]);
    }

    public function check(string $number, ?string $requester = null): VatCheck
    {
        $query = ['countryCode' => VatNumber::country($number), 'vatNumber' => substr($number, 2)];
        if (null !== $requester) {
            $query += ['requesterMemberStateCode' => VatNumber::country($requester), 'requesterNumber' => substr($requester, 2)];
        }

        try {
            $response = $this->http->request('POST', self::URL, ['json' => $query, 'timeout' => $this->timeout]);
            if (429 === $response->getStatusCode()) {
                $retry = $response->getHeaders(false)['retry-after'][0] ?? null;

                throw new UnavailableException('VIES: too many calls.', is_numeric($retry) ? max(1, (int) $retry) : 60);
            }
            $answer = $response->toArray(false);
        } catch (ExceptionInterface $e) {
            throw new UnavailableException('VIES did not answer: '.$e->getMessage(), null, $e);
        }

        $error = $answer['errorWrappers'][0]['error'] ?? $answer['userError'] ?? null;
        if (\in_array($error, self::UNAVAILABLE, true) || (!\array_key_exists('valid', $answer) && null === $error)) {
            throw new UnavailableException(sprintf('VIES: %s.', $error ?? 'no answer'), 'MS_UNAVAILABLE' === $error ? 600 : null);
        }

        $clean = static fn (mixed $value): ?string => \is_string($value) && '' !== trim($value) && '---' !== trim($value) ? trim(preg_replace('/\s*\n\s*/', ', ', $value)) : null;
        $date = \is_string($answer['requestDate'] ?? null) ? new \DateTimeImmutable($answer['requestDate']) : new \DateTimeImmutable();

        return new VatCheck(
            $number,
            (bool) ($answer['valid'] ?? false),
            $date,
            $this->name(),
            $clean($answer['name'] ?? null),
            $clean($answer['address'] ?? null),
            $clean($answer['requestIdentifier'] ?? null),
        );
    }
}
