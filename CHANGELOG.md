# Changelog

## Unreleased (1.x)

### Fixed
- A check VIES refuses is no longer a number that does not exist: `INVALID_INPUT`,
  `INVALID_REQUESTER_INFO` and `VAT_BLOCKED` are an `OmnistateException` naming the code, and any
  other error but VIES's verdicts `VALID` and `INVALID` (the `_TIME` limits, a code VIES adds) an
  `UnavailableException`. Only seven codes were read as unavailable before; any other gave a
  `VatCheck` with `valid` false, so a requester VIES refused made a valid number look invalid.
