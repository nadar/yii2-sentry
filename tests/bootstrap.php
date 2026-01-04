<?php

// Ensure we're starting fresh
if (defined('YII_DEBUG')) {
    error_reporting(-1);
}

define('YII_DEBUG', true);
define('YII_ENV', 'test');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';
