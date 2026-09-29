# omnistate/vies

EU VAT numbers checked with the European Commission's [VIES](https://ec.europa.eu/taxation_customs/vies/),
for [glitchr/omnistate](../core). Its REST API: no SOAP extension needed.

```php
$check = (new Vies($httpClient))->check('FR53901821074', requester: 'FR...');
$check->valid;               // true
$check->name;                // "SARL GLITCH ART"
$check->consultationNumber;  // the proof you checked it that day - keep it with a reverse-charge invoice
```

A member state's service down (`MS_UNAVAILABLE`, they have their hours) throws `UnavailableException`:
it is not a number that does not exist. Numbers are checked for their shape first by `Omnistate::vat()`.
