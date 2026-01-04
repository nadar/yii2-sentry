<?php

namespace Nadar\Sentry;

use Sentry\Severity;
use Sentry\State\Scope;
use yii\base\InvalidConfigException;
use yii\di\Instance;
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
 *             'levels' => ['error', 'warning'],
 *             'except' => [
 *                 'yii\web\HttpException:404',
 *             ],
 *             'logVars' => ['_GET', '_POST', '_SERVER'],
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
     * @var string|Sentry
     */
    public string|Sentry $sentry = 'sentry';

    /**
     * @var callable|null Callback function to add extra data to the event
     * The callback signature: function($message, $extra) { return $extra; }
     */
    public $extraCallback = null;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        
        $this->sentry = Instance::ensure($this->sentry, Sentry::class);
    }

    /**
     * @inheritdoc
     */
    public function export(): void
    {
        foreach ($this->messages as $message) {
            $this->processMessage($message);
        }
    }

    /**
     * Process a single log message
     * 
     * @param array $message The log message
     */
    protected function processMessage(array $message): void
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

        // Apply Sentry component's extraCallback first (if defined)
        if ($this->sentry->extraCallback !== null && is_callable($this->sentry->extraCallback)) {
            $extra = call_user_func($this->sentry->extraCallback, $message, $extra);
        }

        // Apply SentryTarget's extraCallback second (takes precedence)
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
    protected function sendToSentry(mixed $text, Severity $severity, array $extra, string $category): void
    {
        \Sentry\withScope(function (Scope $scope) use ($text, $severity, $extra, $category) {
            $scope->setContext('extra', $extra);
            $scope->setTag('category', $category);
            $scope->setLevel($severity);

            if ($text instanceof \Throwable) {
                \Sentry\captureException($text);
            } else {
                // Handle array or object messages
                if (is_array($text) || is_object($text)) {
                    $text = print_r($text, true);
                }
                
                \Sentry\captureMessage((string) $text, $severity);
            }
        });
    }

    /**
     * Convert Yii log level to Sentry severity
     * 
     * @param int $level Yii log level
     * @return Severity
     */
    protected function getSeverity(int $level): Severity
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
    protected function getContextMessage(): array
    {
        $context = [];
        $allowedVars = ['_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_SERVER'];
        
        foreach ((array) $this->logVars as $var) {
            // Only allow safe global variables
            if (!in_array($var, $allowedVars, true)) {
                continue;
            }

            // Use Yii's request object for request-related data when available
            if (\Yii::$app->has('request') && in_array($var, ['_GET', '_POST', '_FILES', '_COOKIE'], true)) {
                $request = \Yii::$app->request;
                switch ($var) {
                    case '_GET':
                        $context[$var] = $request->get();
                        break;
                    case '_POST':
                        $context[$var] = $request->post();
                        break;
                    case '_COOKIE':
                        $context[$var] = $request->cookies->toArray();
                        break;
                    case '_FILES':
                        if (isset($GLOBALS[$var]) && !empty($GLOBALS[$var])) {
                            // Files need to be accessed from $_FILES directly
                            $context[$var] = $GLOBALS[$var];
                        }
                        break;
                }
            } elseif (isset($GLOBALS[$var]) && !empty($GLOBALS[$var])) {
                $context[$var] = $GLOBALS[$var];
            }
        }

        return $context;
    }

    /**
     * @inheritdoc
     */
    public function collect($messages, $final): void
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
