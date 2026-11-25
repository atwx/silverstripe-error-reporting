# Silverstripe error reporting

Reports the real errors of a Silverstripe site to Sentry or GlitchTip, and nothing else:

- only log entries of level **error** or worse (uncaught exceptions, PHP fatals, `$logger->error()`)
- no client errors (`HTTPResponse_Exception` with a status below 500, e.g. 404)
- known noise is dropped (deprecations, "Allowed hosts has not been set", session warnings, …)
- one **fingerprint per cause**: exception type, the message without numbers/ids/paths and the
  innermost frame of the project's own code, so the same bug is one issue across requests and releases
- Silverstripe 6: exceptions of sake tasks and commands are reported too (symfony/console catches
  them before Silverstripe's error handler sees them; a `console.error` subscriber passes them on)
- every event is tagged with `site`, `installation` (the path in the hosting register) and
  `framework` (Silverstripe version); the release is the deployment directory name

Works with Silverstripe 4.10+, 5 and 6 (PHP 7.4+). It replaces `phptek/sentry` (declared as conflict).

## Installation

```bash
composer require atwx/silverstripe-error-reporting
```

Set the DSN in `.env`:

```
SENTRY_DSN="https://<key>@glitchtip.example.com/<project>"
```

Without `SENTRY_DSN` (or `SS_SENTRY_DSN`) nothing is reported. Only the environment types `live` and
`test` report by default.

## Configuration

```yaml
Atwx\ErrorReporting\ErrorReporting:
  enabled_environments: [live]
  # add patterns (regular expressions) or remove a default by setting it to null
  ignore_messages:
    my_noise: '/Some harmless message/'
    session_file: null
  ignore_exceptions:
    - App\Exceptions\ExpectedException
  tags:
    customer: example
```

Optional environment variables: `SENTRY_RELEASE` overrides the release name.

## Logging your own errors

Use the default logger; anything at level error or worse is reported:

```php
Injector::inst()->get(LoggerInterface::class)->error('Sync failed: ' . $fault, ['exception' => $e]);
```

Warnings and info messages are not reported — keep audit logs and similar in a file log.

## Tests

```bash
composer install && vendor/bin/phpunit
```
