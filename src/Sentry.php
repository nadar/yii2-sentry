<?php

namespace Nadar\Sentry;

use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\State\HubInterface;
use yii\base\Component as BaseComponent;
use yii\base\InvalidConfigException;

/**
 * Sentry Component for Yii2
 * 
 * This component initializes the Sentry SDK and provides configuration options.
 * 
 * Configuration example:
 * ```php
 * 'components' => [
 *     'sentry' => [
 *         'class' => 'Nadar\Sentry\Sentry',
 *         'dsn' => 'your-sentry-dsn',
 *         'environment' => 'production',
 *         'release' => '1.0.0',
 *     ]
 * ]
 * ```
 * 
 * @author Basil Suter <git@nadar.io>
 * @since 1.0.0
 */
class Sentry extends BaseComponent
{
    /**
     * @var string Sentry DSN (Data Source Name)
     */
    public string $dsn;

    /**
     * @var string|null Environment name (e.g., 'production', 'staging', 'development')
     */
    public ?string $environment = null;

    /**
     * @var string|null Release version
     */
    public ?string $release = null;

    /**
     * @var float Sample rate for error events (0.0 to 1.0)
     */
    public float $sampleRate = 1.0;

    /**
     * @var float Sample rate for performance monitoring (0.0 to 1.0)
     */
    public float $tracesSampleRate = 0.0;

    /**
     * @var array Additional client options for Sentry SDK
     */
    public array $clientOptions = [];

    /**
     * @var bool Whether to send default PII (Personally Identifiable Information)
     */
    public bool $sendDefaultPii = false;

    /**
     * @var int Maximum breadcrumbs
     */
    public int $maxBreadcrumbs = 100;

    /**
     * @var callable|null Before send callback
     */
    public $beforeSend = null;

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function init(): void
    {
        parent::init();

        if (empty($this->dsn)) {
            throw new InvalidConfigException('The "dsn" property must be set.');
        }

        $this->initSentry();
    }

    /**
     * Initialize Sentry SDK
     */
    protected function initSentry(): void
    {
        $options = array_merge([
            'dsn' => $this->dsn,
            'sample_rate' => $this->sampleRate,
            'traces_sample_rate' => $this->tracesSampleRate,
            'send_default_pii' => $this->sendDefaultPii,
            'max_breadcrumbs' => $this->maxBreadcrumbs,
        ], $this->clientOptions);

        if ($this->environment !== null) {
            $options['environment'] = $this->environment;
        }

        if ($this->release !== null) {
            $options['release'] = $this->release;
        }

        if ($this->beforeSend !== null && is_callable($this->beforeSend)) {
            $options['before_send'] = $this->beforeSend;
        }

        \Sentry\init($options);
    }

    /**
     * Get the Sentry Hub instance
     * 
     * @return HubInterface
     */
    public function getHub(): HubInterface
    {
        return SentrySdk::getCurrentHub();
    }

    /**
     * Capture an exception
     * 
     * @param \Throwable $exception
     * @return string|null Event ID
     */
    public function captureException(\Throwable $exception): ?string
    {
        return SentrySdk::getCurrentHub()->captureException($exception);
    }

    /**
     * Capture a message
     * 
     * @param string $message
     * @param string|Severity|null $level
     * @return string|null Event ID
     */
    public function captureMessage(string $message, string|Severity|null $level = null): ?string
    {
        // Convert string level to Severity if needed
        if (is_string($level)) {
            $level = new Severity($level);
        }
        
        return SentrySdk::getCurrentHub()->captureMessage($message, $level);
    }
}
