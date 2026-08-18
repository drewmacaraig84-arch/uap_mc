<?php
/**
 * Register Redirect
 * 
 * This file has been moved to /auth/register.php
 * This file redirects to the new location for backward compatibility.
 */
require_once __DIR__ . '/includes/config.php';
header('Location: ' . BASE_URL . '/auth/register.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit;

