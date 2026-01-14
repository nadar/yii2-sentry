<?php

namespace Nadar\Sentry;

use Sentry\Severity;
use Sentry\State\Scope;
use yii\di\Instance;
use yii\helpers\ArrayHelper;
use yii\helpers\StringHelper;
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
     * @inheritdoc
     */
    protected function getContextMessage()
    {
        // this mandatory override to avoid context message being appended to log
        return '';
    }

    /**
     * Process a single log message
     * 
     * @param array $message The log message
     */
    protected function processMessage(array $message): void
    {
        list($text, $level, $category, $timestamp, $traces) = $message;

        $severity = match($level) {
            Logger::LEVEL_ERROR => Severity::error(),
            Logger::LEVEL_WARNING => Severity::warning(),
            Logger::LEVEL_INFO => Severity::info(),
            Logger::LEVEL_TRACE => Severity::debug(),
            Logger::LEVEL_PROFILE, Logger::LEVEL_PROFILE_BEGIN, Logger::LEVEL_PROFILE_END => false,
            default => false,
        };

        if ($severity === false) {
            // Skip profiling logs
            return;
        }


        // Prepare context data
        $extra = [];

        if (is_array($text)) {
            // if array has message or msg key extract that and put the rest in extra (but with
            // the array kes from $text )
            if (isset($text['message'])) {
                $extra = array_merge($extra, $text);
                $text = $text['message'];
            } elseif (isset($text['msg'])) {
                $extra = array_merge($extra, $text);
                $text = $text['msg'];
            }
        }

        // Add stack traces if available
        if (!empty($traces)) {
            $extra['traces'] = $traces;
        }

        $context = [
            'log_level' => Logger::getLevelName($level),
            'category' => $category,
            'globals' => $this->getContextData(),
        ];

        // Send to Sentry
        $this->sendToSentry($text, $severity, $extra, $category, $context);
    }

    /**
     * Send event to Sentry
     * 
     * @param mixed $text Log message or exception
     * @param Severity $severity
     * @param array $extra Extra context data
     * @param string $category Log category
     */
    protected function sendToSentry(mixed $text, Severity $severity, array $extra, string $category, array $context): void
    {
        \Sentry\withScope(function (Scope $scope) use ($text, $severity, $extra, $category, $context) {
            $scope->setContext('Yii-Log', $context);
            $scope->setTag('category', $category);
            $scope->setLevel($severity);
            $scope->setExtras($extra);

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
     * Generates the context information to be logged.
     * Returns the data as an array for Sentry.
     * 
     * @return array the context information
     */
    protected function getContextData(): array
    {
        $context = ArrayHelper::filter($GLOBALS, $this->logVars);
        $items = ArrayHelper::flatten($context);
        foreach ($this->maskVars as $var) {
            foreach ($items as $key => $value) {
                if (StringHelper::matchWildcard($var, $key, ['caseSensitive' => false])) {
                    ArrayHelper::setValue($context, $key, '***');
                }
            }
        }
        return $context;
    }
}
