<?php
/**
 * PHPUnit bootstrap for Australia Post Dolibarr Plugin
 */

// Autoload composer if available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Fallback stubs for Dolibarr functions when running in standalone test environment
if (!function_exists('getDolGlobalString')) {
    function getDolGlobalString($key, $default = '') {
        global $conf;
        if (isset($conf->global->$key)) {
            return $conf->global->$key;
        }
        return $default;
    }
}

if (!function_exists('getDolGlobalInt')) {
    function getDolGlobalInt($key, $default = 0) {
        $val = getDolGlobalString($key, (string)$default);
        return (int)$val;
    }
}

if (!function_exists('dol_syslog')) {
    function dol_syslog($message, $level = 0) {
        // No-op for tests
    }
}

if (!function_exists('price2num')) {
    function price2num($amount) {
        if (is_numeric($amount)) {
            return (float)$amount;
        }
        $clean = str_replace(array(' ', '$', ','), '', $amount);
        return (float)$clean;
    }
}

if (!function_exists('price')) {
    function price($amount, $html = 0) {
        return number_format((float)$amount, 2, '.', '');
    }
}

// Ensure mock $conf object exists
if (!isset($GLOBALS['conf'])) {
    $GLOBALS['conf'] = new stdClass();
    $GLOBALS['conf']->global = new stdClass();
}

// Include plugin classes
require_once __DIR__ . '/../lib/auspost.lib.php';
require_once __DIR__ . '/../class/auspostapi.class.php';
