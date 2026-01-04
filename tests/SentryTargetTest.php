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


    public function testCollectAddsMessagesToTarget(): void
    {
        $target = new SentryTarget();
        $target->init();
        
        $target->collect($this->messages, false);
        
        $this->assertCount(count($this->messages), $target->messages);
    }

    public function testCollectExportsAndClearsOnFinal(): void
    {
        $target = $this->getConfiguredSentryTarget();
        
        $target->collect($this->messages, true);
        
        $this->assertEmpty($target->messages);
    }

    public function testExportProcessesMessages(): void
    {
        $target = $this->getConfiguredSentryTarget();
        
        $target->collect($this->messages, false);
        $this->assertCount(count($this->messages), $target->messages);
        
        $target->export();
        
        // Messages should still be there after export (cleared only on collect with final=true)
        $this->assertCount(count($this->messages), $target->messages);
    }

    public function testExceptionPassing(): void
    {
        $target = $this->getConfiguredSentryTarget();
        
        $exception = new \RuntimeException('Package loss detected');
        $logData = [
            'message' => 'This exception was caught, but still needs to be reported',
            'exception' => $exception,
            'something_extra' => ['foo' => 'bar'],
        ];
        
        $messageWasSent = false;
        
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureException')
            ->willReturnCallback(function (\Throwable $ex, ?Scope $scope = null, ?EventHint $hint = null) use ($exception, &$messageWasSent): ?EventId {
                $messageWasSent = true;
                $this->assertSame($exception, $ex);
                
                return EventId::generate();
            });
        
        SentrySdk::getCurrentHub()->bindClient($client);
        
        $target->collect([[$exception, Logger::LEVEL_ERROR, 'application', 1481513561.197593, []]], true);
        $this->assertTrue($messageWasSent);
    }

    public function testMessageConversion(): void
    {
        $target = $this->getConfiguredSentryTarget();
        
        $expectedMessage = 'Test log message';
        $messageWasSent = false;
        
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureMessage')
            ->willReturnCallback(function (string $message, ?Severity $level = null, ?Scope $scope = null, ?EventHint $hint = null) use ($expectedMessage, &$messageWasSent): ?EventId {
                $messageWasSent = true;
                $this->assertEquals($expectedMessage, $message);
                
                return EventId::generate();
            });
        
        SentrySdk::getCurrentHub()->bindClient($client);
        
        $target->collect([[$expectedMessage, Logger::LEVEL_INFO, 'application', 1481513561.197593, []]], true);
        $this->assertTrue($messageWasSent);
    }

    public function testArrayMessageConversion(): void
    {
        $target = $this->getConfiguredSentryTarget();
        
        $arrayMessage = ['key' => 'value', 'foo' => 'bar'];
        $messageWasSent = false;
        
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureMessage')
            ->willReturnCallback(function (string $message, ?Severity $level = null, ?Scope $scope = null, ?EventHint $hint = null) use (&$messageWasSent): ?EventId {
                $messageWasSent = true;
                // Array should be converted to string
                $this->assertStringContainsString('key', $message);
                $this->assertStringContainsString('value', $message);
                
                return EventId::generate();
            });
        
        SentrySdk::getCurrentHub()->bindClient($client);
        
        $target->collect([[$arrayMessage, Logger::LEVEL_INFO, 'application', 1481513561.197593, []]], true);
        $this->assertTrue($messageWasSent);
    }

    public function testProcessMessageWithTraces(): void
    {
        $target = $this->getConfiguredSentryTarget();
        
        $traces = [
            ['file' => 'test.php', 'line' => 10, 'function' => 'testFunction'],
        ];
        
        $messageWithTraces = ['test', Logger::LEVEL_ERROR, 'application', time(), $traces];
        
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureMessage')
            ->willReturnCallback(function (string $message, ?Severity $level = null, ?Scope $scope = null, ?EventHint $hint = null): ?EventId {
                return EventId::generate();
            });
        
        SentrySdk::getCurrentHub()->bindClient($client);
        
        $target->collect([$messageWithTraces], true);
        
        $this->assertTrue(true); // If we get here without errors, the test passes
    }

    public function testExportIntervalBehavior(): void
    {
        $target = $this->getConfiguredSentryTarget();
        $target->exportInterval = 2; // Export after 2 messages
        
        // Add first message - should not export yet
        $target->collect([['test 1', Logger::LEVEL_INFO, 'test', time(), []]], false);
        $this->assertCount(1, $target->messages);
        
        // Add second message - should export and clear
        $target->collect([['test 2', Logger::LEVEL_INFO, 'test', time(), []]], false);
        $this->assertEmpty($target->messages); // Should be cleared after export
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
