# yii2-sentry

Yii2 Sentry integration with log target support for error tracking and monitoring.

## Requirements

- PHP >= 8.4
- Yii2 >= 2.0
- Sentry PHP SDK >= 4.9

## Installation

Install via Composer:

```bash
composer require nadar/yii2-sentry
```

### Verifying Installation

After installing and configuring the package, you can verify that Sentry is working correctly:

1. **Configure the Sentry component** in your `config/console.php` (for console apps) or `config/web.php` (for web apps)
2. **Add the test command** to your console config (see Console Test Command section below)
3. **Run the test command**:
   ```bash
   php yii sentry-test
   ```
4. **Check your Sentry dashboard** at https://sentry.io/ to see the test events

The test command will send multiple test events including exceptions, messages, and events with custom context to help you verify your integration is working correctly.

## Configuration

### Basic Configuration

Configure the Sentry component in your application config:

```php
return [
    'components' => [
        'sentry' => [
            'class' => 'Nadar\Sentry\Sentry',
            'dsn' => 'YOUR_SENTRY_DSN',
            'environment' => 'production',
            'release' => '1.0.0',
        ],
    ],
];
```

### Console Test Command (Optional)

For console applications, you can add the Sentry test command to verify your integration:

```php
return [
    'controllerMap' => [
        'sentry-test' => [
            'class' => 'Nadar\Sentry\SentryTestCommand',
        ],
    ],
];
```

Then test your Sentry integration by running:

```bash
php yii sentry-test
```

This will send various test events to Sentry including exceptions, messages with different severity levels, log events, and events with custom context data. Check your Sentry dashboard to verify the events appear correctly.

### Log Target Configuration

Add the Sentry log target to your log component:

```php
return [
    'components' => [
        'log' => [
            'targets' => [
                [
                    'class' => 'Nadar\Sentry\SentryTarget',
                    'levels' => ['error', 'warning'],
                    'except' => [
                        'yii\web\HttpException:404',
                        'yii\web\HttpException:403',
                    ],
                    'logVars' => ['_GET', '_POST', '_SESSION', '_SERVER'],
                    'extraCallback' => function ($extra, $message) {
                        // Add custom context data
                        $extra['custom_field'] = 'custom_value';
                        return $extra;
                    },
                ],
            ],
        ],
    ],
];
```

## Configuration Options

### Sentry Component Options

- **dsn** (required): Your Sentry DSN (Data Source Name)
- **environment**: Environment name (e.g., 'production', 'staging', 'development')
- **release**: Release version
- **sampleRate**: Sample rate for error events (0.0 to 1.0, default: 1.0)
- **tracesSampleRate**: Sample rate for performance monitoring (0.0 to 1.0, default: 0.0)
- **sendDefaultPii**: Whether to send default PII (Personally Identifiable Information)
- **maxBreadcrumbs**: Maximum breadcrumbs (default: 100)
- **clientOptions**: Additional client options for Sentry SDK
- **beforeSend**: Callback function called before sending events
- **extraCallback**: Global callback function to add extra data to all events (see below)

### Log Target Options

- **sentry**: The Sentry component ID (default: 'sentry')
- **levels**: Array of log levels to capture (e.g., ['error', 'warning'])
- **except**: Array of patterns to exclude from logging (e.g., ['yii\web\HttpException:404'])
- **logVars**: Array of context variables to log (e.g., ['_GET', '_POST', '_SERVER'])
- **extraCallback**: Callback function to add extra data to events (see below)

## Usage

### Testing Your Integration

The package includes a console command to test your Sentry integration. After configuration, run:

```bash
# Run all tests
php yii sentry-test

# Or run specific tests
php yii sentry-test/exception  # Test exception capture
php yii sentry-test/message    # Test message capture with different levels
php yii sentry-test/logging    # Test Yii2 log integration
php yii sentry-test/context    # Test custom context, tags, and user data
```

The test command will:
- Send test exceptions with stack traces
- Send messages with different severity levels (debug, info, warning, error, fatal)
- Test Yii2 logging integration
- Send events with custom context, user data, tags, and breadcrumbs

After running the command, check your Sentry dashboard at https://sentry.io/ to verify the events appear correctly.

### Manual Exception Capture

You can manually capture exceptions or messages:

```php
try {
    // Your code
} catch (\Exception $e) {
    Yii::$app->sentry->captureException($e);
}
```

### Manual Message Capture

```php
Yii::$app->sentry->captureMessage('Something went wrong', 'error');
```

### Using Yii2 Logger

The log target will automatically capture messages logged through Yii2's logger:

```php
Yii::error('An error occurred');
Yii::warning('A warning message');
```

## Extra Callbacks

### Overview

Both the Sentry component and SentryTarget support `extraCallback` functions to add custom context data to events. When both callbacks are defined, they are merged together with the SentryTarget callback taking precedence.

### Global Extra Callback (Sentry Component)

Define a global `extraCallback` in the Sentry component to add context data to ALL events:

```php
return [
    'components' => [
        'sentry' => [
            'class' => 'Nadar\Sentry\Sentry',
            'dsn' => 'YOUR_SENTRY_DSN',
            'extraCallback' => function ($extra, $message) {
                // Add global context to all events
                $extra['app_version'] = '1.0.0';
                $extra['server_id'] = gethostname();
                return $extra;
            },
        ],
    ],
];
```

### Target-Specific Extra Callback (SentryTarget)

Define a target-specific `extraCallback` in the SentryTarget to add context data to log events:

```php
return [
    'components' => [
        'log' => [
            'targets' => [
                [
                    'class' => 'Nadar\Sentry\SentryTarget',
                    'levels' => ['error', 'warning'],
                    'extraCallback' => function ($extra, $message) {
                        // Add log-specific context
                        $extra['user_id'] = Yii::$app->user->id ?? null;
                        return $extra;
                    },
                ],
            ],
        ],
    ],
];
```

### Callback Merging Behavior

When both callbacks are defined:

1. The Sentry component's `extraCallback` is applied first (global context)
2. The SentryTarget's `extraCallback` is applied second (can override global context)
3. If both callbacks set the same key, the SentryTarget value takes precedence

**Example:**

```php
// Sentry component callback
'extraCallback' => function ($extra, $message) {
    $extra['environment'] = 'global';
    $extra['version'] = '1.0.0';
    return $extra;
}

// SentryTarget callback
'extraCallback' => function ($extra, $message) {
    $extra['environment'] = 'production'; // This overrides the global value
    $extra['user_id'] = 123; // This is added
    return $extra;
}

// Result: ['environment' => 'production', 'version' => '1.0.0', 'user_id' => 123]
```

## License

MIT
