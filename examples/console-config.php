<?php
/**
 * Example console configuration for Yii2 Sentry integration
 * 
 * Add this to your console application config file (e.g., config/console.php)
 */

return [
    'id' => 'app-console',
    'basePath' => dirname(__DIR__),
    
    'components' => [
        // Sentry component configuration
        'sentry' => [
            'class' => 'Nadar\Sentry\Sentry',
            'dsn' => 'YOUR_SENTRY_DSN', // Replace with your actual DSN
            'environment' => getenv('APP_ENV') ?: 'production',
            'release' => '1.0.0', // Your application version
            'sampleRate' => 1.0, // Capture 100% of errors
            'tracesSampleRate' => 0.1, // Capture 10% of performance traces
            'sendDefaultPii' => false,
            'maxBreadcrumbs' => 100,
            
            // Global extra callback (optional)
            'extraCallback' => function ($message, $extra) {
                // Add server information to all events
                $extra['server_hostname'] = gethostname();
                $extra['php_version'] = PHP_VERSION;
                return $extra;
            },
        ],
        
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                // File log target for local debugging
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                    'logFile' => '@runtime/logs/console.log',
                ],
                
                // Sentry log target for error tracking
                [
                    'class' => 'Nadar\Sentry\SentryTarget',
                    'levels' => ['error', 'warning'],
                    
                    // Optionally exclude certain errors
                    'except' => [
                        // Example: 'yii\base\InvalidArgumentException',
                    ],
                    
                    // Context variables to include (console-specific)
                    'logVars' => ['_SERVER'],
                    
                    // Custom callback to add extra context (optional)
                    'extraCallback' => function ($message, $extra) {
                        // Add command-line arguments
                        global $argv;
                        if (isset($argv)) {
                            $extra['cli_args'] = implode(' ', $argv);
                        }
                        
                        // Add current working directory
                        $extra['cwd'] = getcwd();
                        
                        return $extra;
                    },
                ],
            ],
        ],
    ],
    
    // Register the Sentry test command
    'controllerMap' => [
        'sentry-test' => [
            'class' => 'Nadar\Sentry\SentryTestCommand',
            // Optional: specify a different sentry component ID
            // 'sentry' => 'sentry',
        ],
    ],
];
