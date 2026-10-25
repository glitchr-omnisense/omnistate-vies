# omnistate/vies

EU VAT numbers checked with the European Commission's [VIES](https://ec.europa.eu/taxation_customs/vies/),
for [omniscient/omnistate](../core). Its REST API: no SOAP extension needed.

```php
$check = (new Vies($httpClient))->check('FR53901821074', requester: 'FR...');
$check->valid;               // true
$check->name;                // "SARL GLITCH ART"
$check->consultationNumber;  // the proof you checked it that day - keep it with a reverse-charge invoice
```

No framework needed: the package requires `omniscient/omnistate` and `symfony/http-client`.
`$httpClient` is the HTTP client to call with - the application's, `HttpClient::create()` in
plain PHP, a `MockHttpClient` in a test.

A member state's service down (`MS_UNAVAILABLE`, they have their hours) throws `UnavailableException`:
it is not a number that does not exist. Numbers are checked for their shape first by `Omnistate::vat()`.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by the European Commission. It reaches their service on behalf of whoever runs it, and it
does not redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT
licence covers this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with VIES's terms ([the
Commission's legal notice](https://commission.europa.eu/legal-notice_en)), for obtaining whatever
they require (an account, a key, a licence or subscription, an attribution or a citation, a rate
limit to respect), and for any use made of what it returns. The authors publish it free of charge,
make no commercial use of it, and accept no liability for how it is used (see [LICENSE](LICENSE)).

## Documentation

[docs/](docs/index.md): the service, the options, what it supports, how it maps the answers, errors, what was verified.

## License

MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
