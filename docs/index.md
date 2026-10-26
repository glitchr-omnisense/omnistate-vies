# omnistate/vies

EU VAT numbers for [omnisense/omnistate](https://github.com/glitchr-omnisense/omnistate), checked
with the European Commission's [VIES](https://ec.europa.eu/taxation_customs/vies/) through its
REST API - no SOAP extension needed. VIES relays each member state's own register: whether a
number exists, whose it is, and - given your own VAT number - a consultation number, the proof
that the check was made that day.

| Operation | Request |
|---|---|
| `check($number, $requester)` | `POST https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number`, JSON body `{countryCode, vatNumber[, requesterMemberStateCode, requesterNumber]}` |

The address is the constant `Omnistate\Vies\Vies::URL`. There is no sandbox address in the code.

## Installation

```sh
composer require omnisense/omnistate omnistate/vies
```

Requires PHP 8.2, `omnisense/omnistate` (1.x) and `symfony/http-client` (6.4, 7 or 8). In a
Symfony application with `Omnistate\Bridge\Symfony\OmnistateBundle`, the register is registered
(tag `omnistate.vat_registry`) as soon as the package is installed; the bundle's
`omnistate.requester` sets your VAT number for every check.

## Credentials

None: VIES is open, without an account or a key. Your own VAT number, as the requester, is not a
credential: it only makes VIES answer a consultation number.

## Options

| Argument | Type | Default | |
|---|---|---|---|
| `http` | `Symfony\Contracts\HttpClient\HttpClientInterface` | required | the client to call with |
| `timeout` | float | `10` | seconds VIES has to answer (the bundle's `omnistate.timeout`) |

Per check: `check(string $number, ?string $requester = null)` - the number normalized
(`EL123456789`, not `gr 123 456 789`), and your own VAT number as the requester. Through the
facade, the facade's `requester` is used when the call names none.

## Capabilities

| Contract / question | Supported |
|---|---|
| `VatRegistryInterface::check()` | yes: the 27 member states (prefix `EL` for Greece) and `XI` (Northern Ireland) |
| companies, professionals, facilities, civil records, Internet | no |

`supports()` is true for a number whose two first letters are a key of
`Omnistate\Identifier\VatNumber::FORMATS` - the same list the facade checks the shape with. `name()`
is `vies`, the `source` of every `VatCheck`.

## Behaviour

**The request.** `countryCode` is the number's prefix, `vatNumber` the rest; with a requester,
`requesterMemberStateCode` and `requesterNumber` the same way. The number is not normalized here:
the facade does it (`Omnistate::vat()`), and refuses a number with no member state's shape before
any request.

**The answer.** `valid` is VIES's `valid` (`false` when it is missing and an error was given, see
below); `name` and `address` are VIES's, `---` read as nothing and the address's lines joined by
`, `; `checkedAt` is VIES's `requestDate`, else the moment of the call; `consultationNumber` is
`requestIdentifier`, `null` when empty.

**Unavailable.** VIES's error (the first `errorWrappers[].error`, else `userError`) is an
`UnavailableException` when it is one of `MS_UNAVAILABLE` (`retryAfter` 600 s), `TIMEOUT`,
`SERVICE_UNAVAILABLE`, `MS_MAX_CONCURRENT_REQ`, `GLOBAL_MAX_CONCURRENT_REQ`, `SERVER_BUSY`,
`IP_BLOCKED`; so is an answer with neither `valid` nor an error. Another error code is read as a
check whose `valid` is the answer's, `false` when missing.

**Caching.** None of its own: the facade's cache keeps checks, by number and requester.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnistate\Exception\UnavailableException;
use Omnistate\Vies\Vies;
use Symfony\Component\HttpClient\HttpClient;

$vies = new Vies(HttpClient::create());

var_dump($vies->supports('IE6388047V'), $vies->supports('NO123456789'));
foreach (['IE6388047V', 'DE000000000'] as $number) {
    try {
        $check = $vies->check($number);
        printf("%s %s, %s, %s, checked at %s, consultation %s\n", $check->number, $check->valid ? 'valid' : 'not valid', $check->name ?? '-', $check->address ?? '-', $check->checkedAt->format(DATE_ATOM), $check->consultationNumber ?? '-');
    } catch (UnavailableException $e) {
        printf("%s: VIES did not answer (%s), retry after %s s\n", $number, $e->getMessage(), $e->retryAfter ?? '-');
    }
}
```

```
bool(true)
bool(false)
IE6388047V valid, GOOGLE IRELAND LIMITED, 3RD FLOOR, GORDON HOUSE, BARROW STREET, DUBLIN 4, checked at 2026-10-25T14:18:04+00:00, consultation -
DE000000000 not valid, -, -, checked at 2026-10-25T14:18:04+00:00, consultation -
```

(run on 2026-10-25 in omnistate's Docker harness, against VIES; no requester, so no consultation
number)

Through the facade, with your VAT number for the consultation number:

```php
$omnistate = new Omnistate\Omnistate(vat: [$vies], requester: 'FR53901821074');
$check = $omnistate->vat('ie 6388047v');   // normalized, its shape checked, then VIES
```

In Symfony:

```yaml
omnistate:
    requester: '%env(OMNISTATE_REQUESTER)%'   # your own VAT number
```

## Errors

| VIES answers | The register |
|---|---|
| `valid: true` / `valid: false` | a `VatCheck` |
| an error among the seven above | `UnavailableException` (`VIES: MS_MAX_CONCURRENT_REQ.`); `retryAfter` 600 for `MS_UNAVAILABLE`, else none |
| no `valid` and no error | `UnavailableException` (`VIES: no answer.`) |
| HTTP 429 | `UnavailableException` (`VIES: too many calls.`), `retryAfter` from `Retry-After` (at least 1), else 60 |
| no answer, a body that is not JSON (an HTML error page) | `UnavailableException` (`VIES did not answer: ...`) |

> [!WARNING]
> Known defect: any VIES error outside these seven (`INVALID_INPUT`, `INVALID_REQUESTER_INFO`...) gives `valid: false` (`Vies.php:63-73`), where a refused check is not an invalid number: a refused requester makes a valid number look invalid.

A member state's service that does not answer is never a number that does not exist.

## Verified, and not

Tested offline: `Tests/ViesTest.php` (a valid number, its name and address, the request's body
with the requester; an unknown number with `---` read as nothing; `MS_UNAVAILABLE` with its 600 s;
VIES unreachable) on answers recorded in `Tests/Fixtures`, and `Tests/ViesContractTest.php`,
omnistate's conformance suite.

Run live on 2026-10-25 (the example above): two numbers checked; earlier the same day France's
service answered `MS_MAX_CONCURRENT_REQ`, read as unavailable. Not run: a check with a requester
(no consultation number seen live here).

Defect found in the code: `Vies.php:63-73` - only the seven error codes above are unavailable;
any other (`INVALID_INPUT`, `INVALID_REQUESTER_INFO`...) gives a `VatCheck` whose `valid` is
`false` when the answer has no `valid`: a requester VIES refuses can make a valid number read as
not valid.

## Responsibility

An independent, unofficial client, not affiliated with the European Commission. Whoever runs it
complies with VIES's terms ([the Commission's legal
notice](https://commission.europa.eu/legal-notice_en)): see the README's Responsibility section.
