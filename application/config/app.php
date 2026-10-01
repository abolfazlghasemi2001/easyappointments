<?php defined('BASEPATH') or exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| App Configuration
|--------------------------------------------------------------------------
|
| Declare some of the global config values of Easy!Appointments.
|
*/

$config['version'] = '1.6.0'; // This must be changed manually.

$app_url = getenv('APP_URL');
$config['url'] = $app_url !== false && $app_url !== '' ? $app_url : Config::BASE_URL;

$app_debug = getenv('APP_DEBUG');
$config['debug'] = $app_debug === false ? Config::DEBUG_MODE : filter_var($app_debug, FILTER_VALIDATE_BOOLEAN);

$asset_version = getenv('APP_ASSET_VERSION');
$config['cache_busting_token'] = $asset_version !== false && $asset_version !== '' ? $asset_version : 'TSJ83';
