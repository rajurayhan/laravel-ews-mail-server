# Changelog

## 2.0.0 - 2026-09-08

Breaking rewrite. The package no longer talks to Exchange Web Services.

### Added
- Microsoft Graph `sendMail` transport registered as the `microsoft-graph` Laravel mailer
- Entra ID client-credentials authentication with token caching
- `mergeConfigFrom()` so the package works before config is published
- PHP 8.2+ and Laravel 11–13 constraints
- PHPUnit + Orchestra Testbench coverage and GitHub Actions
- MIT `LICENSE`

### Changed
- Credentials come from environment variables, not a published PHP array of placeholders
- Send failures throw `ExchangeMailException` instead of logging and returning `null`
- TLS is verified (the v1 default that disabled certificate checks is gone)

### Deprecated
- `ExchangeMailServer::sendEmail()` — still works, now backed by Graph. Use `Mail::mailer('microsoft-graph')` instead.

### Removed
- `php-ews/php-ews` and username/password EWS
- SSL verification bypass
- The `ci-audit.yml` workflow

### Upgrade
See [UPGRADE.md](UPGRADE.md).
