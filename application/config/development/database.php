<?php defined('BASEPATH') or exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| LOCAL DEVELOPMENT DATABASE (sqlite)
| -------------------------------------------------------------------------
| CodeIgniter loads this file *instead* of application/config/database.php
| whenever APP_ENV=development, so it must define the complete connection
| group (that is why the base file is required first and only the driver
| specific values are overridden below).
|
| This keeps the checkout runnable in sandboxes and CI containers that do not
| provide a MySQL server. Production deployments (APP_ENV=production) keep
| using mysqli as defined in application/config/database.php.
|
*/

require __DIR__ . '/../database.php';

$db['default']['dbdriver'] = 'sqlite3';
$db['default']['database'] = dirname(__DIR__, 3) . '/storage/easyappointments.sqlite';
$db['default']['dbprefix'] = 'ea_';
$db['default']['char_set'] = 'utf8';
