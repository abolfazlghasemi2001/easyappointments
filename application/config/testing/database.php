<?php defined('BASEPATH') or exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| LOCAL TESTING DATABASE (sqlite)
| -------------------------------------------------------------------------
| Used when APP_ENV=testing (see composer.json "test" script). It reuses the
| local development database so that the test suite runs without a MySQL
| server; production keeps mysqli (application/config/database.php).
|
*/

require __DIR__ . '/../development/database.php';
