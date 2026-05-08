<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

putenv('APP_ENV=test');
putenv('APP_DEBUG=1');

$_SERVER['APP_ENV'] = 'test';
$_ENV['APP_ENV'] = 'test';
$_SERVER['APP_DEBUG'] = '1';
$_ENV['APP_DEBUG'] = '1';

$_SERVER['TEST_DATABASE_URL'] ??= 'postgresql://app:app@127.0.0.1:55432/app_test?serverVersion=16&charset=utf8';
$_ENV['TEST_DATABASE_URL'] ??= $_SERVER['TEST_DATABASE_URL'];

umask(0000);
