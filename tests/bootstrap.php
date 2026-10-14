<?php

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'test');

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';

new yii\console\Application([
	'id' => 'gsftp-test',
	'basePath' => __DIR__,
	'vendorPath' => dirname(__DIR__) . '/vendor',
]);
