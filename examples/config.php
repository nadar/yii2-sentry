<?php
/**
 * Example configuration for Yii2 Sentry integration
 * 
 * Add this to your application config file (e.g., config/web.php or config/main.php)
 */

return [
    'components' => [
        // Option 1: Configure Sentry component separately
        'sentry' => [
            'class' => 'Nadar\Sentry\Sentry',
            'dsn' => 'YOUR_SENTRY_DSN',
            'environment' => getenv('APP_ENV') ?: 'production',
            'release' => '1.0.0',
            'sampleRate' => 1.0,
            'tracesSampleRate' => 0.1,
            'sendDefaultPii' => false,
            'maxBreadcrumbs' => 100,
            'clientOptions' => [
                'server_name' => gethostname(),
            ],
        ],
        
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                // File log target (optional, for local debugging)
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
                
                // Sentry log target
                [
                    'class' => 'Nadar\Sentry\SentryTarget',
                    'levels' => ['error', 'warning'],
                    
                    // Exclude common exceptions that you don't want to track
                    'except' => [
                        'yii\web\HttpException:404',
                        'yii\web\HttpException:403',
                        'yii\web\HttpException:400',
                    ],
                    
                    // Context variables to include in reports
                    'logVars' => ['_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_SERVER'],
                    
                    // Custom callback to add extra context (optional)
                    'extraCallback' => function ($extra, $message) {
                        // Add user information if available
                        if (!Yii::$app->user->isGuest) {
                            $extra['user'] = [
                                'id' => Yii::$app->user->id,
                                'username' => Yii::$app->user->identity->username ?? 'unknown',
                            ];
                        }
                        
                        // Add request information
                        if (Yii::$app->has('request')) {
                            $extra['request'] = [
                                'method' => Yii::$app->request->method,
                                'url' => Yii::$app->request->absoluteUrl,
                                'user_ip' => Yii::$app->request->userIP,
                            ];
                        }
                        
                        return $extra;
                    },
                ],
            ],
        ],
    ],
];
