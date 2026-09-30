<?php defined('BASEPATH') or exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Hooks
| -------------------------------------------------------------------------
| This file lets you define "hooks" to extend CI without hacking the core
| files.  Please see the user guide for info:
|
|	http://codeigniter.com/user_guide/general/hooks.html
|
*/

/**
 * Add security headers to all responses.
 */
$hook['post_controller_constructor'][] = [
    'class' => '',
    'function' => 'add_security_headers',
    'filename' => 'security_headers.php',
    'filepath' => 'hooks',
    'params' => [],
];

/**
 * Expose the Persian (Jalali) localization settings to the frontend (calendar type, Persian digits, text direction,
 * first day of the week).
 */
$hook['post_controller_constructor'][] = [
    'class' => '',
    'function' => 'load_localization_script_vars',
    'filename' => 'localization.php',
    'filepath' => 'hooks',
    'params' => [],
];

/**
 * Probabilistically clean up old storage files (runs ~1% of requests).
 */
$hook['post_system'][] = [
    'class' => '',
    'function' => 'storage_cleanup',
    'filename' => 'storage_cleanup.php',
    'filepath' => 'hooks',
    'params' => [],
];

/* End of file hooks.php */
/* Location: ./application/config/hooks.php */
