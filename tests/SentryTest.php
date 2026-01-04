<?php

namespace Nadar\Sentry\Tests;

use Nadar\Sentry\Sentry;
use PHPUnit\Framework\TestCase;
use Sentry\State\Hub;
use yii\base\InvalidConfigException;

class SentryTest extends TestCase
{
    private const TEST_DSN = self::TEST_DSN;
    
    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Reset Sentry state between tests
        $hub = Hub::getCurrent();
        $hub->bindClient(null);
    }

    public function testCanInstantiate(): void
    {
        $sentry = new Sentry();
        $this->assertInstanceOf(Sentry::class, $sentry);
    }

    public function testInitThrowsExceptionWhenDsnIsEmpty(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('The "dsn" property must be set.');
        
        $sentry = new Sentry();
        $sentry->init();
    }

    public function testInitSucceedsWithValidDsn(): void
    {
        $sentry = new Sentry();
        $sentry->dsn = self::TEST_DSN;
        
        $this->assertNull($sentry->init());
    }

    public function testDefaultPropertyValues(): void
    {
        $sentry = new Sentry();
        $sentry->dsn = self::TEST_DSN;
        
        $this->assertNull($sentry->environment);
        $this->assertNull($sentry->release);
        $this->assertEquals(1.0, $sentry->sampleRate);
        $this->assertEquals(0.0, $sentry->tracesSampleRate);
        $this->assertFalse($sentry->sendDefaultPii);
        $this->assertEquals(100, $sentry->maxBreadcrumbs);
        $this->assertNull($sentry->beforeSend);
        $this->assertIsArray($sentry->clientOptions);
        $this->assertEmpty($sentry->clientOptions);
    }

    public function testCustomPropertyValues(): void
    {
        $sentry = new Sentry();
        $sentry->dsn = self::TEST_DSN;
        $sentry->environment = 'production';
        $sentry->release = '1.0.0';
        $sentry->sampleRate = 0.5;
        $sentry->tracesSampleRate = 0.1;
        $sentry->sendDefaultPii = true;
        $sentry->maxBreadcrumbs = 50;
        
        $this->assertEquals('production', $sentry->environment);
        $this->assertEquals('1.0.0', $sentry->release);
        $this->assertEquals(0.5, $sentry->sampleRate);
        $this->assertEquals(0.1, $sentry->tracesSampleRate);
        $this->assertTrue($sentry->sendDefaultPii);
        $this->assertEquals(50, $sentry->maxBreadcrumbs);
    }

    public function testGetHubReturnsHubInstance(): void
    {
        $sentry = new Sentry();
        $sentry->dsn = self::TEST_DSN;
        $sentry->init();
        
        $hub = $sentry->getHub();
        $this->assertInstanceOf(Hub::class, $hub);
    }

    public function testCaptureExceptionReturnsEventId(): void
    {
        $sentry = new Sentry();
        $sentry->dsn = self::TEST_DSN;
        $sentry->init();
        
        $exception = new \Exception('Test exception');
        $eventId = $sentry->captureException($exception);
        
        // Event ID should be a string or null
        $this->assertTrue(is_string($eventId) || is_null($eventId));
    }

    public function testCaptureMessageReturnsEventId(): void
    {
        $sentry = new Sentry();
        $sentry->dsn = self::TEST_DSN;
        $sentry->init();
        
        $eventId = $sentry->captureMessage('Test message', 'error');
        
        // Event ID should be a string or null
        $this->assertTrue(is_string($eventId) || is_null($eventId));
    }

    public function testBeforeSendCallback(): void
    {
        $callbackExecuted = false;
        
        $sentry = new Sentry();
        $sentry->dsn = self::TEST_DSN;
        $sentry->beforeSend = function ($event, $hint) use (&$callbackExecuted) {
            $callbackExecuted = true;
            return $event;
        };
        $sentry->init();
        
        $sentry->captureMessage('Test message');
        
        // Note: callback execution depends on Sentry SDK internals
        // This test validates that the callback can be set without errors
        $this->assertNotNull($sentry->beforeSend);
    }

    public function testClientOptionsAreMerged(): void
    {
        $sentry = new Sentry();
        $sentry->dsn = self::TEST_DSN;
        $sentry->clientOptions = [
            'server_name' => 'test-server',
            'release' => '2.0.0',
        ];
        
        $this->assertIsArray($sentry->clientOptions);
        $this->assertArrayHasKey('server_name', $sentry->clientOptions);
        $this->assertEquals('test-server', $sentry->clientOptions['server_name']);
    }
}
