# yii2-sentry

Yii2 Sentry integration with log target support for error tracking and monitoring.

## Installation

Install via Composer:

```bash
composer require nadar/yii2-sentry
```

## Configuration

### Basic Configuration

Configure the Sentry component in your application config:

```php
return [
    'components' => [
        'sentry' => [
            'class' => 'Nadar\Sentry\Component',
            'dsn' => 'YOUR_SENTRY_DSN',
            'environment' => 'production',
            'release' => '1.0.0',
        ],
    ],
];
```

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
                    'clientOptions' => [
                        'environment' => 'production',
                    ],
                    'extraCallback' => function ($message, $extra) {
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

Alternatively, you can configure the log target without the component by providing the DSN directly:

```php
return [
    'components' => [
        'log' => [
            'targets' => [
                [
                    'class' => 'Nadar\Sentry\SentryTarget',
                    'dsn' => 'YOUR_SENTRY_DSN',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
    ],
];
```

## Configuration Options

### Component Options

- **dsn** (required): Your Sentry DSN (Data Source Name)
- **environment**: Environment name (e.g., 'production', 'staging', 'development')
- **release**: Release version
- **sampleRate**: Sample rate for error events (0.0 to 1.0, default: 1.0)
- **tracesSampleRate**: Sample rate for performance monitoring (0.0 to 1.0, default: 0.0)
- **sendDefaultPii**: Whether to send default PII (Personally Identifiable Information)
- **maxBreadcrumbs**: Maximum breadcrumbs (default: 100)
- **clientOptions**: Additional client options for Sentry SDK
- **beforeSend**: Callback function called before sending events

### Log Target Options

- **dsn**: Your Sentry DSN (required if sentry component is not configured)
- **levels**: Array of log levels to capture (e.g., ['error', 'warning'])
- **except**: Array of patterns to exclude from logging (e.g., ['yii\web\HttpException:404'])
- **logVars**: Array of context variables to log (e.g., ['_GET', '_POST', '_SERVER'])
- **clientOptions**: Additional client options for Sentry SDK
- **extraCallback**: Callback function to add extra data to events

## Usage

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

## Requirements

- PHP >= 7.4
- Yii2 >= 2.0
- Sentry PHP SDK >= 4.0

## License

MIT
