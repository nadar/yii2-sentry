<?php

namespace Nadar\Sentry;

use yii\console\Controller;
use yii\console\ExitCode;
use Yii;

/**
 * Sentry Test Command
 * 
 * This console command helps test your Sentry integration by triggering
 * various types of exceptions and messages with different configurations.
 * 
 * Configuration example:
 * ```php
 * 'controllerMap' => [
 *     'sentry-test' => [
 *         'class' => 'Nadar\Sentry\SentryTestCommand',
 *     ],
 * ]
 * ```
 * 
 * Usage:
 * ```
 * php yii sentry-test
 * ```
 * 
 * @author Basil Suter <git@nadar.io>
 * @since 1.0.0
 */
class SentryTestCommand extends Controller
{
    /**
     * @var string|Sentry The Sentry component ID or instance
     */
    public string|Sentry $sentry = 'sentry';

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        
        // Ensure sentry component is loaded
        if (is_string($this->sentry)) {
            if (!Yii::$app->has($this->sentry)) {
                $this->stdout("Error: Sentry component '{$this->sentry}' is not configured.\n", \yii\helpers\Console::FG_RED);
                $this->stdout("Please add the Sentry component to your application configuration.\n");
                exit(ExitCode::CONFIG);
            }
            $this->sentry = Yii::$app->get($this->sentry);
        }

        $this->sentry->extraCallback = function () {
            // Add command name to extra data
            $extra = [];
            $extra['extra_callback_sentry_test_command'] = 'sentry-test';
            $extra['message_exported'] = 'from_sentry_test_command';
            return $extra;
        };
    }

    /**
     * Default action - runs all tests
     * 
     * @return int
     */
    public function actionIndex(): int
    {
        $this->stdout("=== Sentry Integration Test ===\n\n", \yii\helpers\Console::BOLD);
        
        $this->actionContext();
        
        $this->stdout("\n=== Test Complete ===\n", \yii\helpers\Console::BOLD);
        $this->stdout("Check your Sentry dashboard at: https://sentry.io/\n", \yii\helpers\Console::FG_GREEN);
        
        return ExitCode::OK;
    }

    /**
     * Test event with custom context and user data
     * 
     * Demonstrates how to send events with additional context information.
     * 
     * @return int
     */
    public function actionContext(): int
    {
        $this->stdout("→ Testing exception with custom context...\n", \yii\helpers\Console::FG_CYAN);
        
        // Configure scope with custom context
        $hub = $this->sentry->getHub();
        $hub->configureScope(function (\Sentry\State\Scope $scope): void {
            // Set user information
            $scope->setUser([
                'id' => 'test-user-123',
                'username' => 'test_user',
                'email' => 'test@example.com',
                'ip_address' => '127.0.0.1',
            ]);
            
            // Set custom tags
            $scope->setTag('test_type', 'console_command');
            $scope->setTag('command', 'sentry-test');
            $scope->setTag('environment_test', 'true');
            
            // Set custom context
            $scope->setContext('test_info', [
                'framework' => 'Yii2',
                'library' => 'nadar/yii2-sentry',
                'test_time' => date('Y-m-d H:i:s'),
                'php_version' => PHP_VERSION,
            ]);
            
            // Add breadcrumbs
            $scope->addBreadcrumb(
                new \Sentry\Breadcrumb(
                    \Sentry\Breadcrumb::LEVEL_INFO,
                    \Sentry\Breadcrumb::TYPE_DEFAULT,
                    'test',
                    'Starting Sentry test command'
                )
            );
            
            $scope->addBreadcrumb(
                new \Sentry\Breadcrumb(
                    \Sentry\Breadcrumb::LEVEL_INFO,
                    \Sentry\Breadcrumb::TYPE_DEFAULT,
                    'test',
                    'Configuring test context'
                )
            );
        });
        
        // Capture exception with all the context
        try {
            throw new \RuntimeException(
                'Test exception with custom context, tags, user data, and breadcrumbs',
                12345
            );
        } catch (\Exception $e) {
            $eventId = $this->sentry->captureException($e);
            $this->stdout("  ✓ Exception with context captured (Event ID: {$eventId})\n", \yii\helpers\Console::FG_GREEN);
            $this->stdout("  ✓ User data: test-user-123\n", \yii\helpers\Console::FG_GREEN);
            $this->stdout("  ✓ Tags: test_type, command, environment_test\n", \yii\helpers\Console::FG_GREEN);
            $this->stdout("  ✓ Custom context: test_info\n", \yii\helpers\Console::FG_GREEN);
            $this->stdout("  ✓ Breadcrumbs: 2 items\n", \yii\helpers\Console::FG_GREEN);
        }
        
        return ExitCode::OK;
    }

}
