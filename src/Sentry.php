<?php

namespace Nadar\Sentry;

use Sentry\Event;
use Sentry\EventHint;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\UserDataBag;
use Yii;
use yii\base\Component as BaseComponent;
use yii\base\InvalidConfigException;
use yii\web\Application;

use function Sentry\init;

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
     * @var float Sample rate for error events (0.0 to 1.0)
     */
    public float $sampleRate = 1.0;

    /**
     * @var float Sample rate for performance monitoring (0.0 to 1.0)
     */
    public float $tracesSampleRate = 0.0;

    /**
     * @var array Additional client options for Sentry SDK, see https://docs.sentry.io/platforms/php/configuration/options/ for all options
     * @see https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/
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
     * @var callable|null Callback function to add extra data to the event
     * The callback signature: function() { return ['extra1' => 'value1']; }
     * This will be applied globally to all events sent through this component.
     */
    public $extraCallback = null;

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
            'default_integrations' => false, // disable default integrations to avoid conflicts, as Yii has its own error handling (https://docs.sentry.io/platforms/php/integrations/#default-integrations)
            'dsn' => $this->dsn,
            'sample_rate' => $this->sampleRate,
            'traces_sample_rate' => $this->tracesSampleRate,
            'send_default_pii' => $this->sendDefaultPii,
            'max_breadcrumbs' => $this->maxBreadcrumbs,
            'environment' => YII_ENV,
            'release' => Yii::$app->version ?? null,
        ], $this->clientOptions);

        $options['before_send'] = function (Event $event): ?Event {

            $extra = $event->getExtra() ?? [];
            if ($this->extraCallback && is_callable($this->extraCallback)) {
                $extraCallbackData = call_user_func($this->extraCallback);
                if (is_array($extraCallbackData)) {
                    $extra = array_merge($extra, $extraCallbackData);
                } else {
                    $extra = array_merge($extra, ['extra_callback_data' => var_export($extraCallbackData, true)]);
                }
            }
            $extra = array_merge($extra, $this->getAppExtras());
            $event->setExtra($extra);
            $event->setTags(array_merge($event->getTags(), $this->getAppTags()));

            // merge data user bag or add new if not empty
            $appUser = $this->getAppUserDataBag();
            if ($appUser) {
                if ($event->getUser()) {
                    $event->getUser()->merge($appUser);
                } else {
                    $event->setUser($appUser);
                }
            }
            return $event;
        };

        init($options);
    }

    public function getAppTags() : array
    {
        return array_filter([
            'yii.version' => Yii::getVersion(),
        ]);
    }

    public function getAppExtras() : array
    {
        return [
            'app' => array_filter([
                'name' => Yii::$app->name ?? null,
                'id' => Yii::$app->id ?? null,
            ]),
            'routing' => array_filter([
                'controller' => Yii::$app?->controller?->id ?? null,
                'action' => Yii::$app?->controller?->action?->id ?? null,
                'requested_route' => Yii::$app?->requestedRoute ?? null,
                'requested_params' => Yii::$app?->requestedParams ?? null,
            ])
        ];
    }

    public function getAppUserDataBag() : UserDataBag|false
    {
        if (!Yii::$app instanceof Application) {
            return false;
        }

        $userId = null;
        try {
            if (Yii::$app->has('user') && !Yii::$app->user->isGuest) {
                $userId = Yii::$app->user->id;
            }
        } catch (\Throwable $e) {
            
        }
        return new UserDataBag(
            ipAddress: Yii::$app?->request?->userIP,
            id: $userId,
        );
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
