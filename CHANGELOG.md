# Changelog

## Unreleased (1.x)

### Added
- `policy.json`, the register's usage policy in omnisense/contracts' schema (named in
  `composer.json`, `extra.omnisense.policy`), given by `usagePolicy()` (`DeclaresUsagePolicyInterface`):
  limits unknown (`rateLimits: null`); the rights unknown until the terms are read again. Requires
  `omnisense/contracts`.
- A 429 is thrown with `$status` 429: through the facade, the next calls wait for its `retryAfter`.
- PHPStan at level max, its baseline what the package had on 2026-11-10.

### Fixed
- A check VIES refuses is no longer a number that does not exist: `INVALID_INPUT`,
  `INVALID_REQUESTER_INFO` and `VAT_BLOCKED` are an `OmnistateException` naming the code, and any
  other error but VIES's verdicts `VALID` and `INVALID` (the `_TIME` limits, a code VIES adds) an
  `UnavailableException`. Only seven codes were read as unavailable before; any other gave a
  `VatCheck` with `valid` false, so a requester VIES refused made a valid number look invalid.
