<?php

/**
 * Simple test to verify autoloader works correctly
 * Run this from command line: php test-autoloader.php
 */

// Simulate WordPress constants
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Load the autoloader
require_once __DIR__ . '/src/Autoloader.php';

// Initialize autoloader
WooApp\Autoloader::init(__DIR__ . '/src');

echo "=== WooApp Autoloader Test ===\n\n";

// Test 1: Load Plugin class
try {
    $class = 'WooApp\Plugin';
    class_exists($class) ? printf("✓ %s loaded successfully\n", $class) : printf("✗ Failed to load %s\n", $class);
} catch (Exception $e) {
    printf("✗ Error loading Plugin: %s\n", $e->getMessage());
}

// Test 2: Load Container class
try {
    $class = 'WooApp\Core\Container';
    class_exists($class) ? printf("✓ %s loaded successfully\n", $class) : printf("✗ Failed to load %s\n", $class);
} catch (Exception $e) {
    printf("✗ Error loading Container: %s\n", $e->getMessage());
}

// Test 3: Load AbstractService class
try {
    $class = 'WooApp\Core\AbstractService';
    class_exists($class) ? printf("✓ %s loaded successfully\n", $class) : printf("✗ Failed to load %s\n", $class);
} catch (Exception $e) {
    printf("✗ Error loading AbstractService: %s\n", $e->getMessage());
}

// Test 4: Load Security class
try {
    $class = 'WooApp\Common\Security';
    class_exists($class) ? printf("✓ %s loaded successfully\n", $class) : printf("✗ Failed to load %s\n", $class);
} catch (Exception $e) {
    printf("✗ Error loading Security: %s\n", $e->getMessage());
}

// Test 5: Load VersionChecker class
try {
    $class = 'WooApp\Common\VersionChecker';
    class_exists($class) ? printf("✓ %s loaded successfully\n", $class) : printf("✗ Failed to load %s\n", $class);
} catch (Exception $e) {
    printf("✗ Error loading VersionChecker: %s\n", $e->getMessage());
}

// Test 6: Load Admin class
try {
    $class = 'WooApp\Admin\Admin';
    class_exists($class) ? printf("✓ %s loaded successfully\n", $class) : printf("✗ Failed to load %s\n", $class);
} catch (Exception $e) {
    printf("✗ Error loading Admin: %s\n", $e->getMessage());
}

// Test 7: Load REST class
try {
    $class = 'WooApp\API\REST';
    class_exists($class) ? printf("✓ %s loaded successfully\n", $class) : printf("✗ Failed to load %s\n", $class);
} catch (Exception $e) {
    printf("✗ Error loading REST: %s\n", $e->getMessage());
}

// Test 8: Load Hooks class
try {
    $class = 'WooApp\Core\Hooks';
    class_exists($class) ? printf("✓ %s loaded successfully\n", $class) : printf("✗ Failed to load %s\n", $class);
} catch (Exception $e) {
    printf("✗ Error loading Hooks: %s\n", $e->getMessage());
}

echo "\n=== All tests completed ===\n";
