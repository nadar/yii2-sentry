<?php
/**
 * Usage examples for manual exception and message capture
 */

// Example 1: Capture an exception using the component
try {
    // Some code that might throw an exception
    throw new \Exception('Something went wrong');
} catch (\Exception $e) {
    Yii::$app->sentry->captureException($e);
    // Handle the exception...
}

// Example 2: Capture a message using the component
Yii::$app->sentry->captureMessage('User performed an important action', 'info');

// Example 3: Using Yii2's logger (will be automatically sent to Sentry)
Yii::error('An error occurred in the application');
Yii::warning('This is a warning message');
Yii::info('Informational message');

// Example 4: Log with category
Yii::error('Database connection failed', 'application.db');

// Example 5: Log an exception using Yii2 logger
try {
    // Some code
} catch (\Exception $e) {
    Yii::error($e, 'application.exception');
}

// Example 6: Using direct Sentry SDK functions (after initialization)
\Sentry\captureMessage('Direct message to Sentry');

// Example 7: Adding context to Sentry
\Sentry\configureScope(function (\Sentry\State\Scope $scope) {
    $scope->setUser([
        'id' => Yii::$app->user->id,
        'email' => Yii::$app->user->identity->email ?? null,
    ]);
    $scope->setTag('controller', Yii::$app->controller->id);
    $scope->setTag('action', Yii::$app->controller->action->id);
});

// Example 8: Add breadcrumb
\Sentry\addBreadcrumb(
    new \Sentry\Breadcrumb(
        \Sentry\Breadcrumb::LEVEL_INFO,
        \Sentry\Breadcrumb::TYPE_DEFAULT,
        'user.action',
        'User clicked the button'
    )
);
