<?php

namespace Nadar\Sentry\Tests;

use Nadar\Sentry\Sentry;
use Nadar\Sentry\SentryTarget;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\EventId;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\State\Hub;
use Sentry\State\Scope;
use yii\log\Logger;

class SentryTargetTest extends TestCase
{
    private const TEST_DSN = 'https://public@sentry.io/1';
    
    /** @var array test messages */
    protected array $messages = [
        ['test', Logger::LEVEL_INFO, 'test', 1481513561.197593, []],
        ['test 2', Logger::LEVEL_INFO, 'test 2', 1481513572.867054, []]
    ];

    /** @var callable|null Previous error handler */
    private $previousErrorHandler = null;

    /** @var callable|null Previous exception handler */
    private $previousExceptionHandler = null;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Store current error handlers to restore them later
        $this->previousErrorHandler = set_error_handler(function() {});
        restore_error_handler();
        
        $this->previousExceptionHandler = set_exception_handler(function() {});
        restore_exception_handler();
        
        // Mock Yii application for tests
        if (!\Yii::$app) {
            $this->mockYiiApplication();
        }
    }

    protected function tearDown(): void
    {
        // Restore error handlers before parent tearDown
        // Remove all handlers added by Sentry
        while (true) {
            $handler = set_error_handler(function() {});
            restore_error_handler();
            if ($handler === null) {
                break;
            }
            restore_error_handler();
        }
        
        while (true) {
            $handler = set_exception_handler(function() {});
            restore_exception_handler();
            if ($handler === null) {
                break;
            }
            restore_exception_handler();
        }
        
        // Restore original handlers if they existed
        if ($this->previousErrorHandler !== null) {
            set_error_handler($this->previousErrorHandler);
        }
        
        if ($this->previousExceptionHandler !== null) {
            set_exception_handler($this->previousExceptionHandler);
        }
        
        // Reset Sentry state
        SentrySdk::init();
        
        // Destroy Yii application
        \Yii::$app = null;
        
        parent::tearDown();
    }

    protected function mockYiiApplication(): void
    {
        new \yii\web\Application([
            'id' => 'test-app',
            'basePath' => dirname(__DIR__),
            'components' => [
                'sentry' => [
                    'class' => Sentry::class,
                    'dsn' => self::TEST_DSN,
                ],
                'request' => [
                    'cookieValidationKey' => 'test',
                    'scriptFile' => __DIR__ . '/index.php',
                    'scriptUrl' => '/index.php',
                ],
            ],
        ]);
    }

    public function testCanInstantiate(): void
    {
        $target = new SentryTarget();
        $this->assertInstanceOf(SentryTarget::class, $target);
    }

    public function testInitializesSentryComponent(): void
    {
        $target = new SentryTarget();
        $target->init();
        
        $this->assertInstanceOf(Sentry::class, $target->sentry);
    }

    public function testGetSeverityConvertsYiiLevelsToSentryLevels(): void
    {
        $target = new SentryTarget();
        $target->init();
        
        $class = new ReflectionClass(SentryTarget::class);
        $method = $class->getMethod('getSeverity');
        $method->setAccessible(true);
        
        $this->assertEquals(Severity::error(), $method->invoke($target, Logger::LEVEL_ERROR));
        $this->assertEquals(Severity::warning(), $method->invoke($target, Logger::LEVEL_WARNING));
        $this->assertEquals(Severity::info(), $method->invoke($target, Logger::LEVEL_INFO));
        $this->assertEquals(Severity::debug(), $method->invoke($target, Logger::LEVEL_TRACE));
        $this->assertEquals(Severity::debug(), $method->invoke($target, Logger::LEVEL_PROFILE));
        $this->assertEquals(Severity::info(), $method->invoke($target, 999)); // Unknown level
    }

    /**
     * Returns configured SentryTarget object
     */
    protected function getConfiguredSentryTarget(): SentryTarget
    {
        $target = new SentryTarget();
        $target->exportInterval = 100;
        $target->setLevels(Logger::LEVEL_ERROR | Logger::LEVEL_WARNING | Logger::LEVEL_INFO);
        $target->init();
        
        return $target;
    }
}
