<?php

/**
 * Gets environment variables
 *
 * @return array
 */
function get_env_config()
{
    static $config = null;

    if (!is_null($config)) {
        return $config;
    }

    $configPath = __DIR__ . '/../config.php';
    if (file_exists($configPath)) {
        $config = require $configPath;
    } else {
        $config = [];
    }

    return $config;
}

/**
 * Gets the current environment
 *
 * @return string
 */
function get_tests_env()
{
    return array_get(get_env_config(), 'env', '_');
}

/**
 * @return string
 */
function get_tests_base_uri()
{
    return array_get(get_env_config(), 'base_uri');
}

/**
 * @param string $key
 *
 * @return mixed
 */
function get_tests_db($key)
{
    return array_get(get_env_config(), 'database.'. $key);
}
