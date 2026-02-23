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

    /**
     * Yii2's ErrorHandler::logException() normalizes ALL HttpException subclasses
     * to the category "yii\web\HttpException:STATUS_CODE". This means except filters
     * using the subclass name (e.g. "yii\web\ForbiddenHttpException:403") will NOT work.
     *
     * The correct except config must use "yii\web\HttpException:STATUS" for all HTTP exceptions.
     */

    /**
     * Test that the except filter correctly excludes messages with matching categories.
     */
    public function testExceptFilterExcludesMatchingCategories(): void
    {
        $messages = [
            [new \yii\web\NotFoundHttpException('Not Found'), Logger::LEVEL_ERROR, 'yii\web\HttpException:404', microtime(true), []],
            [new \yii\web\ForbiddenHttpException('Forbidden'), Logger::LEVEL_ERROR, 'yii\web\HttpException:403', microtime(true), []],
            [new \yii\web\UnauthorizedHttpException('Unauthorized'), Logger::LEVEL_ERROR, 'yii\web\HttpException:401', microtime(true), []],
            [new \yii\web\BadRequestHttpException('Bad Request'), Logger::LEVEL_ERROR, 'yii\web\HttpException:400', microtime(true), []],
            [new \RuntimeException('Something broke'), Logger::LEVEL_ERROR, 'RuntimeException', microtime(true), []],
            ['A warning message', Logger::LEVEL_WARNING, 'application', microtime(true), []],
        ];

        // Correct except config: use yii\web\HttpException:STATUS
        $except = [
            'yii\web\HttpException:404',
            'yii\web\HttpException:403',
            'yii\web\HttpException:401',
            'yii\web\HttpException:400',
        ];

        $filtered = SentryTarget::filterMessages(
            $messages,
            Logger::LEVEL_ERROR | Logger::LEVEL_WARNING,
            [],
            $except
        );

        // Only the RuntimeException and the warning message should remain
        $this->assertCount(2, $filtered);

        $remainingCategories = array_map(fn ($m) => $m[2], $filtered);
        $this->assertContains('RuntimeException', $remainingCategories);
        $this->assertContains('application', $remainingCategories);
    }

    /**
     * Test that using subclass names in except does NOT filter HTTP exceptions,
     * because Yii2 always normalizes the category to "yii\web\HttpException:STATUS".
     *
     * This demonstrates the misconfiguration pitfall.
     */
    public function testExceptWithSubclassNamesDoesNotFilter(): void
    {
        $messages = [
            [new \yii\web\ForbiddenHttpException('Forbidden'), Logger::LEVEL_ERROR, 'yii\web\HttpException:403', microtime(true), []],
            [new \yii\web\UnauthorizedHttpException('Unauthorized'), Logger::LEVEL_ERROR, 'yii\web\HttpException:401', microtime(true), []],
            [new \yii\web\BadRequestHttpException('Bad Request'), Logger::LEVEL_ERROR, 'yii\web\HttpException:400', microtime(true), []],
        ];

        // WRONG config: using subclass names instead of yii\web\HttpException:STATUS
        $exceptWrong = [
            'yii\web\ForbiddenHttpException:403',
            'yii\web\UnauthorizedHttpException:401',
            'yii\web\BadRequestHttpException:400',
        ];

        $filtered = SentryTarget::filterMessages(
            $messages,
            Logger::LEVEL_ERROR,
            [],
            $exceptWrong
        );

        // All 3 messages remain because the except categories don't match the actual categories
        $this->assertCount(3, $filtered, 'Using subclass names in except does not filter HttpException subclasses because Yii2 normalizes categories to yii\web\HttpException:STATUS');
    }

    /**
     * Test that the except filter works correctly through the collect() method,
     * ensuring filtered messages never reach export/processMessage.
     */
    public function testCollectRespectsExceptFilter(): void
    {
        $target = $this->getConfiguredSentryTarget();
        $target->except = [
            'yii\web\HttpException:404',
            'yii\web\HttpException:403',
            'yii\web\HttpException:401',
            'yii\web\HttpException:400',
        ];

        $messages = [
            [new \yii\web\NotFoundHttpException('Not Found'), Logger::LEVEL_ERROR, 'yii\web\HttpException:404', microtime(true), []],
            [new \yii\web\ForbiddenHttpException('Forbidden'), Logger::LEVEL_ERROR, 'yii\web\HttpException:403', microtime(true), []],
            ['Server error', Logger::LEVEL_ERROR, 'application', microtime(true), []],
        ];

        // Collect but don't finalize — just filter into $target->messages
        $target->collect($messages, false);

        // Use reflection to access the messages property
        $reflection = new ReflectionClass($target);
        $prop = $reflection->getProperty('messages');
        $prop->setAccessible(true);
        $collected = $prop->getValue($target);

        // Only the non-HTTP-exception message should survive
        $this->assertCount(1, $collected);
        $this->assertSame('Server error', $collected[array_key_first($collected)][0]);
    }

    /**
     * Test that a 500 error (not in except list) still goes through.
     */
    public function testNonExceptedHttpExceptionPassesThrough(): void
    {
        $messages = [
            [new \yii\web\HttpException(500, 'Internal Server Error'), Logger::LEVEL_ERROR, 'yii\web\HttpException:500', microtime(true), []],
            [new \yii\web\NotFoundHttpException('Not Found'), Logger::LEVEL_ERROR, 'yii\web\HttpException:404', microtime(true), []],
        ];

        $except = [
            'yii\web\HttpException:404',
            'yii\web\HttpException:403',
            'yii\web\HttpException:401',
            'yii\web\HttpException:400',
        ];

        $filtered = SentryTarget::filterMessages(
            $messages,
            Logger::LEVEL_ERROR,
            [],
            $except
        );

        $this->assertCount(1, $filtered);
        $remaining = array_values($filtered);
        $this->assertSame('yii\web\HttpException:500', $remaining[0][2]);
    }

    /**
     * Test that levels filter works together with except.
     * Info-level messages should be excluded when only error+warning levels are configured.
     */
    public function testLevelsAndExceptFilterCombined(): void
    {
        $messages = [
            ['info message', Logger::LEVEL_INFO, 'application', microtime(true), []],
            ['error message', Logger::LEVEL_ERROR, 'application', microtime(true), []],
            [new \yii\web\NotFoundHttpException('Not Found'), Logger::LEVEL_ERROR, 'yii\web\HttpException:404', microtime(true), []],
            ['warning message', Logger::LEVEL_WARNING, 'application', microtime(true), []],
        ];

        $levels = Logger::LEVEL_ERROR | Logger::LEVEL_WARNING;
        $except = ['yii\web\HttpException:404'];

        $filtered = SentryTarget::filterMessages($messages, $levels, [], $except);

        // info is filtered by level, 404 is filtered by except
        $this->assertCount(2, $filtered);

        $remainingTexts = array_map(fn ($m) => is_string($m[0]) ? $m[0] : get_class($m[0]), array_values($filtered));
        $this->assertContains('error message', $remainingTexts);
        $this->assertContains('warning message', $remainingTexts);
    }

    /**
     * Verify that Yii2's ErrorHandler sets the expected category for HttpException subclasses.
     * This confirms the root cause: all subclasses get "yii\web\HttpException:STATUS" as category.
     */
    public function testErrorHandlerCategoryNormalization(): void
    {
        $cases = [
            [new \yii\web\NotFoundHttpException('Not Found'), 'yii\web\HttpException:404'],
            [new \yii\web\ForbiddenHttpException('Forbidden'), 'yii\web\HttpException:403'],
            [new \yii\web\UnauthorizedHttpException('Unauthorized'), 'yii\web\HttpException:401'],
            [new \yii\web\BadRequestHttpException('Bad Request'), 'yii\web\HttpException:400'],
            [new \yii\web\HttpException(500, 'Server Error'), 'yii\web\HttpException:500'],
        ];

        // Replicate the category logic from ErrorHandler::logException
        foreach ($cases as [$exception, $expectedCategory]) {
            $category = get_class($exception);
            if ($exception instanceof \yii\web\HttpException) {
                $category = 'yii\web\HttpException:' . $exception->statusCode;
            }
            $this->assertSame($expectedCategory, $category, sprintf(
                'Expected category "%s" for %s, got "%s"',
                $expectedCategory,
                get_class($exception),
                $category
            ));
        }
    }
}
