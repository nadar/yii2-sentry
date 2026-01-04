<?php

namespace Nadar\Sentry;

use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\Severity;
use Sentry\State\Hub;
use Sentry\State\Scope;
use yii\base\InvalidConfigException;
use yii\log\Logger;
use yii\log\Target;

/**
 * SentryTarget sends log messages to Sentry.
 * 
 * Configuration example:
 * ```php
 * 'log' => [
 *     'targets' => [
 *         [
 *             'class' => 'Nadar\Sentry\SentryTarget',
 *             'dsn' => 'your-sentry-dsn',
 *             'levels' => ['error', 'warning'],
 *             'except' => [
 *                 'yii\web\HttpException:404',
 *             ],
 *             'logVars' => ['_GET', '_POST', '_SERVER'],
 *             'clientOptions' => [
 *                 'environment' => 'production',
 *             ],
 *             'extraCallback' => function ($message, $extra) {
 *                 // Add custom data
 *                 $extra['custom_key'] = 'custom_value';
 *                 return $extra;
 *             },
 *         ],
 *     ],
 * ],
 * ```
 * 
 * @author Basil Suter <git@nadar.io>
 * @since 1.0.0
 */
class SentryTarget extends Target
{
    /**
     * @var string Sentry DSN (Data Source Name)
     */
    public $dsn;

    /**
     * @var array Additional client options for Sentry SDK
     */
    public $clientOptions = [];

    /**
     * @var callable|null Callback function to add extra data to the event
     * The callback signature: function($message, $extra) { return $extra; }
     */
    public $extraCallback;

    /**
     * @var Component|null Reference to Sentry component
     */
    protected $component;

    /**
     * @var bool Whether Sentry has been initialized
     */
    protected $initialized = false;

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();

        // Try to get Sentry component from Yii application
        if (\Yii::$app->has('sentry')) {
            $this->component = \Yii::$app->get('sentry');
            $this->initialized = true;
        } elseif (!empty($this->dsn)) {
            // Initialize Sentry directly if DSN is provided
            $this->initSentry();
            $this->initialized = true;
        } else {
            throw new InvalidConfigException('Either "dsn" property must be set or "sentry" component must be configured.');
        }
    }

    /**
     * Initialize Sentry SDK
     */
    protected function initSentry()
    {
        $options = array_merge([
            'dsn' => $this->dsn,
        ], $this->clientOptions);

        \Sentry\init($options);
    }

    /**
     * @inheritdoc
     */
    public function export()
    {
        if (!$this->initialized) {
            return;
        }

        foreach ($this->messages as $message) {
            $this->processMessage($message);
        }
    }

    /**
     * Process a single log message
     * 
     * @param array $message The log message
     */
    protected function processMessage($message)
    {
        list($text, $level, $category, $timestamp, $traces) = $message;

        // Convert Yii log level to Sentry severity
        $severity = $this->getSeverity($level);

        // Prepare context data
        $extra = [
            'category' => $category,
            'timestamp' => $timestamp,
            'log_level' => Logger::getLevelName($level),
        ];

        // Add log vars (request data, environment, etc.)
        if (!empty($this->logVars)) {
            $extra['log_vars'] = $this->getContextMessage();
        }

        // Add stack traces if available
        if (!empty($traces)) {
            $extra['traces'] = $traces;
        }

        // Apply extra callback if defined
        if ($this->extraCallback !== null && is_callable($this->extraCallback)) {
            $extra = call_user_func($this->extraCallback, $message, $extra);
        }

        // Send to Sentry
        $this->sendToSentry($text, $severity, $extra, $category);
    }

    /**
     * Send event to Sentry
     * 
     * @param mixed $text Log message or exception
     * @param Severity $severity
     * @param array $extra Extra context data
     * @param string $category Log category
     */
    protected function sendToSentry($text, $severity, $extra, $category)
    {
        \Sentry\configureScope(function (Scope $scope) use ($extra, $category, $severity) {
            $scope->setContext('extra', $extra);
            $scope->setTag('category', $category);
            $scope->setLevel($severity);
        });

        if ($text instanceof \Throwable) {
            \Sentry\captureException($text);
        } else {
            // Handle array or object messages
            if (is_array($text) || is_object($text)) {
                $text = print_r($text, true);
            }
            
            \Sentry\captureMessage((string) $text, $severity);
        }
    }

    /**
     * Convert Yii log level to Sentry severity
     * 
     * @param int $level Yii log level
     * @return Severity
     */
    protected function getSeverity($level)
    {
        switch ($level) {
            case Logger::LEVEL_ERROR:
                return Severity::error();
            case Logger::LEVEL_WARNING:
                return Severity::warning();
            case Logger::LEVEL_INFO:
                return Severity::info();
            case Logger::LEVEL_TRACE:
            case Logger::LEVEL_PROFILE:
            case Logger::LEVEL_PROFILE_BEGIN:
            case Logger::LEVEL_PROFILE_END:
                return Severity::debug();
            default:
                return Severity::info();
        }
    }

    /**
     * Generates the context information to be logged.
     * Overrides parent method to return the data instead of formatting it as string.
     * 
     * @return array the context information
     */
    protected function getContextMessage()
    {
        $context = [];
        
        foreach ((array) $this->logVars as $var) {
            if (!empty($GLOBALS[$var])) {
                $context[$var] = $GLOBALS[$var];
            }
        }

        return $context;
    }

    /**
     * @inheritdoc
     */
    public function collect($messages, $final)
    {
        $this->messages = array_merge(
            $this->messages,
            $this->filterMessages($messages, $this->getLevels(), $this->categories, $this->except)
        );
        
        $count = count($this->messages);
        
        if ($count > 0 && ($final || $this->exportInterval > 0 && $count >= $this->exportInterval)) {
            $this->export();
            $this->messages = [];
        }
    }
}
