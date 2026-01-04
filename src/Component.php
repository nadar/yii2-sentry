<?php

namespace Nadar\Sentry;

use Sentry\ClientBuilder;
use Sentry\State\Hub;
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
 *         'class' => 'Nadar\Sentry\Component',
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
class Component extends BaseComponent
{
    /**
     * @var string Sentry DSN (Data Source Name)
     */
    public $dsn;

    /**
     * @var string|null Environment name (e.g., 'production', 'staging', 'development')
     */
    public $environment;

    /**
     * @var string|null Release version
     */
    public $release;

    /**
     * @var float Sample rate for error events (0.0 to 1.0)
     */
    public $sampleRate = 1.0;

    /**
     * @var float Sample rate for performance monitoring (0.0 to 1.0)
     */
    public $tracesSampleRate = 0.0;

    /**
     * @var array Additional client options for Sentry SDK
     */
    public $clientOptions = [];

    /**
     * @var bool Whether to send default PII (Personally Identifiable Information)
     */
    public $sendDefaultPii = false;

    /**
     * @var int Maximum breadcrumbs
     */
    public $maxBreadcrumbs = 100;

    /**
     * @var callable|null Before send callback
     */
    public $beforeSend;

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function init()
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
    protected function initSentry()
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

        $client = ClientBuilder::create($options)->getClient();
        Hub::getCurrent()->bindClient($client);
    }

    /**
     * Get the Sentry Hub instance
     * 
     * @return Hub
     */
    public function getHub()
    {
        return Hub::getCurrent();
    }

    /**
     * Capture an exception
     * 
     * @param \Throwable $exception
     * @return string|null Event ID
     */
    public function captureException($exception)
    {
        return Hub::getCurrent()->captureException($exception);
    }

    /**
     * Capture a message
     * 
     * @param string $message
     * @param string|null $level
     * @return string|null Event ID
     */
    public function captureMessage($message, $level = null)
    {
        return Hub::getCurrent()->captureMessage($message, $level);
    }
}
