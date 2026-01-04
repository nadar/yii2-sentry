# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2026-01-04

### Added
- Initial release of Yii2 Sentry integration
- `Nadar\Sentry\Component` - Yii2 component for Sentry SDK initialization
- `Nadar\Sentry\SentryTarget` - Yii2 log target for sending logs to Sentry
- Support for Sentry PHP SDK versions 4.x and 5.x
- Configuration options:
  - `dsn` - Sentry Data Source Name
  - `environment` - Environment name
  - `release` - Release version
  - `sampleRate` - Error sampling rate
  - `tracesSampleRate` - Performance monitoring sampling rate
  - `sendDefaultPii` - PII settings
  - `maxBreadcrumbs` - Breadcrumb limit
  - `clientOptions` - Additional Sentry client options
  - `beforeSend` - Callback before sending events
- Log target features:
  - `except` - Patterns to exclude from logging
  - `logVars` - Context variables to include in logs
  - `extraCallback` - Callback for adding custom context
- Comprehensive documentation and examples
- MIT License
