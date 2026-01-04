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
 * php yii sentry-test/exception
 * php yii sentry-test/message
 * php yii sentry-test/all
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
    }

    /**
     * Default action - runs all tests
     * 
     * @return int
     */
    public function actionIndex(): int
    {
        $this->stdout("=== Sentry Integration Test ===\n\n", \yii\helpers\Console::BOLD);
        
        $this->stdout("This command will send multiple test events to Sentry.\n");
        $this->stdout("Check your Sentry dashboard to verify they appear correctly.\n\n");
        
        // Run all test actions
        $this->actionException();
        $this->stdout("\n");
        $this->actionMessage();
        $this->stdout("\n");
        $this->actionLogging();
        $this->stdout("\n");
        $this->actionContext();
        
        $this->stdout("\n=== Test Complete ===\n", \yii\helpers\Console::BOLD);
        $this->stdout("Check your Sentry dashboard at: https://sentry.io/\n", \yii\helpers\Console::FG_GREEN);
        
        return ExitCode::OK;
    }

    /**
     * Test exception capture
     * 
     * Sends a test exception to Sentry with stack trace and context.
     * 
     * @return int
     */
    public function actionException(): int
    {
        $this->stdout("→ Testing exception capture...\n", \yii\helpers\Console::FG_CYAN);
        
        try {
            // Create a nested call stack for better demonstration
            $this->triggerNestedExceptions();
        } catch (\Exception $e) {
            $eventId = $this->sentry->captureException($e);
            $this->stdout("  ✓ Exception captured (Event ID: {$eventId})\n", \yii\helpers\Console::FG_GREEN);
        }
        
        return ExitCode::OK;
    }

    /**
     * Test message capture with different severity levels
     * 
     * Sends test messages to Sentry with various severity levels.
     * 
     * @return int
     */
    public function actionMessage(): int
    {
        $this->stdout("→ Testing message capture with different severity levels...\n", \yii\helpers\Console::FG_CYAN);
        
        $messages = [
            ['message' => 'This is a debug message from yii2-sentry', 'level' => 'debug'],
            ['message' => 'This is an info message from yii2-sentry', 'level' => 'info'],
            ['message' => 'This is a warning message from yii2-sentry', 'level' => 'warning'],
            ['message' => 'This is an error message from yii2-sentry', 'level' => 'error'],
            ['message' => 'This is a fatal message from yii2-sentry', 'level' => 'fatal'],
        ];
        
        foreach ($messages as $msg) {
            $eventId = $this->sentry->captureMessage($msg['message'], $msg['level']);
            $this->stdout("  ✓ {$msg['level']} message sent (Event ID: {$eventId})\n", \yii\helpers\Console::FG_GREEN);
        }
        
        return ExitCode::OK;
    }

    /**
     * Test Yii2 logging integration
     * 
     * Tests the SentryTarget by using Yii2's logging system.
     * 
     * @return int
     */
    public function actionLogging(): int
    {
        $this->stdout("→ Testing Yii2 log target integration...\n", \yii\helpers\Console::FG_CYAN);
        
        // These should be captured by SentryTarget if configured
        Yii::error('Test error log message via Yii logger');
        Yii::warning('Test warning log message via Yii logger');
        Yii::info('Test info log message via Yii logger');
        
        // Flush logs to ensure they're sent
        Yii::$app->log->getLogger()->flush(true);
        
        $this->stdout("  ✓ Log messages sent via Yii logger\n", \yii\helpers\Console::FG_GREEN);
        $this->stdout("  Note: These will only appear in Sentry if SentryTarget is configured\n", \yii\helpers\Console::FG_YELLOW);
        
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

    /**
     * Run all tests sequentially
     * 
     * @return int
     */
    public function actionAll(): int
    {
        return $this->actionIndex();
    }

    /**
     * Helper method to create nested exceptions for better stack trace demonstration
     * 
     * @throws \Exception
     */
    private function triggerNestedExceptions(): void
    {
        $this->levelOne();
    }

    /**
     * First level of nested call
     * 
     * @throws \Exception
     */
    private function levelOne(): void
    {
        $this->levelTwo();
    }

    /**
     * Second level of nested call
     * 
     * @throws \Exception
     */
    private function levelTwo(): void
    {
        $this->levelThree();
    }

    /**
     * Third level of nested call - throws the exception
     * 
     * @throws \Exception
     */
    private function levelThree(): void
    {
        throw new \Exception(
            'Test exception from yii2-sentry console command - This is a test!',
            500
        );
    }
}
