<?php
/**
 * Minimal configuration example for Yii2 Sentry integration
 * 
 * This example shows how to configure the log target without using the Sentry component
 */

return [
    'components' => [
        'log' => [
            'targets' => [
                [
                    'class' => 'Nadar\Sentry\SentryTarget',
                    'dsn' => 'YOUR_SENTRY_DSN', // Required when not using component
                    'levels' => ['error', 'warning'],
                    'except' => [
                        'yii\web\HttpException:404',
                    ],
                    'logVars' => ['_GET', '_POST', '_SERVER'],
                    'clientOptions' => [
                        'environment' => 'production',
                        'release' => '1.0.0',
                    ],
                ],
            ],
        ],
    ],
];
