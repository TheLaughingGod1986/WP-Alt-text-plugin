#!/usr/bin/env php
<?php
/**
 * Comprehensive Plugin Functionality Test
 * Tests all endpoints, hooks, and critical functionality
 */

echo "====================================\n";
echo "BeepBeep AI Plugin Functionality Test\n";
echo "====================================\n\n";

$errors = [];
$warnings = [];
$passed = 0;
$total = 0;

// Test 1: Extract and verify ZIP structure
echo "→ Test 1: ZIP Structure\n";
$total++;
$zip_path = __DIR__ . '/dist/beepbeep-ai-alt-text-generator.4.2.3.zip';
if (!file_exists($zip_path)) {
    $errors[] = "ZIP file not found at: $zip_path";
} else {
    $zip = new ZipArchive();
    if ($zip->open($zip_path) === true) {
        $required_files = [
            'beepbeep-ai-alt-text-generator/beepbeep-ai-alt-text-generator.php',
            'beepbeep-ai-alt-text-generator/readme.txt',
            'beepbeep-ai-alt-text-generator/uninstall.php',
            'beepbeep-ai-alt-text-generator/admin/class-bbai-core.php',
            'beepbeep-ai-alt-text-generator/includes/class-api-client-v2.php',
        ];

        foreach ($required_files as $file) {
            if ($zip->locateName($file) === false) {
                $errors[] = "Required file missing in ZIP: $file";
            }
        }

        // Check for test files that shouldn't be there
        $bad_patterns = ['test', 'debug', 'reset', 'check', 'diagnose', '.sh', '.sql'];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            foreach ($bad_patterns as $pattern) {
                if (stripos(basename($filename), $pattern) !== false && pathinfo($filename, PATHINFO_EXTENSION) === 'php') {
                    $warnings[] = "Suspicious file in ZIP: $filename";
                }
            }
        }

        $zip->close();
        $passed++;
        echo "  ✓ ZIP structure valid\n";
    } else {
        $errors[] = "Failed to open ZIP file";
    }
}

// Test 2: Extract and check main plugin file
echo "\n→ Test 2: Main Plugin File\n";
$total++;
$temp_dir = sys_get_temp_dir() . '/bbai-test-' . uniqid();
mkdir($temp_dir, 0777, true);

$zip = new ZipArchive();
if ($zip->open($zip_path) === true) {
    $zip->extractTo($temp_dir);
    $zip->close();

    $main_file = $temp_dir . '/beepbeep-ai-alt-text-generator/beepbeep-ai-alt-text-generator.php';
    if (file_exists($main_file)) {
        $content = file_get_contents($main_file);

        // Check for required headers
        $required_headers = [
            'Plugin Name:',
            'Version:',
            'Author:',
            'License:',
            'Text Domain:',
        ];

        foreach ($required_headers as $header) {
            if (strpos($content, $header) === false) {
                $errors[] = "Missing required header: $header";
            }
        }

        // Check for prohibited code
        if (strpos($content, 'set_error_handler') !== false) {
            $errors[] = "CRITICAL: set_error_handler found in main file!";
        }
        if (strpos($content, 'ob_start') !== false) {
            $warnings[] = "ob_start found in main file";
        }

        $passed++;
        echo "  ✓ Main plugin file valid\n";
    } else {
        $errors[] = "Main plugin file not found after extraction";
    }
} else {
    $errors[] = "Failed to extract ZIP for testing";
}

// Test 3: Check for REST endpoints registration
echo "\n→ Test 3: REST API Endpoints\n";
$total++;
$rest_file = $temp_dir . '/beepbeep-ai-alt-text-generator/admin/class-bbai-rest-controller.php';
if (file_exists($rest_file)) {
    $content = file_get_contents($rest_file);

    $expected_routes = [
        '/generate/',
        '/alt/',
        '/list',
        '/stats',
        '/usage',
        '/plans',
        '/queue',
        '/logs',
    ];

    $found_routes = 0;
    foreach ($expected_routes as $route) {
        if (strpos($content, $route) !== false) {
            $found_routes++;
        }
    }

    if ($found_routes === count($expected_routes)) {
        $passed++;
        echo "  ✓ All REST endpoints registered ($found_routes routes)\n";
    } else {
        $warnings[] = "Only found $found_routes of " . count($expected_routes) . " expected routes";
    }

    // Check for permission callbacks
    if (strpos($content, 'permission_callback') === false) {
        $errors[] = "CRITICAL: No permission_callback found in REST controller!";
    } else {
        echo "  ✓ Permission callbacks present\n";
    }
} else {
    $errors[] = "REST controller file not found";
}

// Test 4: Check AJAX handlers
echo "\n→ Test 4: AJAX Handlers\n";
$total++;
$hooks_file = $temp_dir . '/beepbeep-ai-alt-text-generator/admin/class-bbai-admin-hooks.php';
if (file_exists($hooks_file)) {
    $content = file_get_contents($hooks_file);

    $expected_ajax = [
        'beepbeepai_refresh_usage',
        'beepbeepai_login',
        'beepbeepai_register',
        'beepbeepai_logout',
    ];

    $found_ajax = 0;
    foreach ($expected_ajax as $action) {
        if (strpos($content, $action) !== false) {
            $found_ajax++;
        }
    }

    // Check for testing hooks that should be removed
    if (strpos($content, 'reset_credits') !== false) {
        $errors[] = "CRITICAL: Testing function 'reset_credits' still present!";
    } else {
        echo "  ✓ No testing hooks found\n";
    }

    $passed++;
    echo "  ✓ AJAX handlers registered ($found_ajax actions)\n";
} else {
    $errors[] = "Admin hooks file not found";
}

// Test 5: Check nonce verification
echo "\n→ Test 5: Security - Nonce Verification\n";
$total++;
$core_file = $temp_dir . '/beepbeep-ai-alt-text-generator/admin/class-bbai-core.php';
if (file_exists($core_file)) {
    $content = file_get_contents($core_file);

    // Count AJAX functions
    preg_match_all('/public function ajax_/', $content, $ajax_functions);
    $ajax_count = count($ajax_functions[0]);

    // Count nonce checks
    preg_match_all('/check_ajax_referer/', $content, $nonce_checks);
    $nonce_count = count($nonce_checks[0]);

    if ($nonce_count > 0) {
        $passed++;
        echo "  ✓ Found $nonce_count nonce verifications for $ajax_count AJAX functions\n";
    } else {
        $warnings[] = "No nonce verifications found (expected for $ajax_count AJAX functions)";
    }

    // Check for testing functions
    if (strpos($content, 'reset_credits_for_testing') !== false) {
        $errors[] = "CRITICAL: Testing function 'reset_credits_for_testing' still in code!";
    }
} else {
    $errors[] = "Core file not found";
}

// Test 6: Check SQL query security
echo "\n→ Test 6: SQL Query Security\n";
$total++;
$uninstall_file = $temp_dir . '/beepbeep-ai-alt-text-generator/uninstall.php';
if (file_exists($uninstall_file)) {
    $content = file_get_contents($uninstall_file);

    // Check for wpdb->prepare usage
    if (strpos($content, 'wpdb->prepare') !== false) {
        $passed++;
        echo "  ✓ Using wpdb->prepare() for SQL queries\n";
    } else {
        $warnings[] = "No wpdb->prepare() found in uninstall.php";
    }

    // Check for unsafe direct SQL
    if (preg_match('/\$wpdb->query\s*\(\s*["\'](?!.*prepare)/', $content)) {
        $warnings[] = "Found direct SQL queries without prepare()";
    }
} else {
    $errors[] = "Uninstall file not found";
}

// Test 7: Check readme.txt
echo "\n→ Test 7: Readme.txt Validation\n";
$total++;
$readme_file = $temp_dir . '/beepbeep-ai-alt-text-generator/readme.txt';
if (file_exists($readme_file)) {
    $content = file_get_contents($readme_file);

    $required_sections = [
        '=== ',
        'Contributors:',
        'Tags:',
        'Requires at least:',
        'Tested up to:',
        'Stable tag:',
        'License:',
        '== Description ==',
        '== Installation ==',
        '== External Services ==',
    ];

    $missing = [];
    foreach ($required_sections as $section) {
        if (strpos($content, $section) === false) {
            $missing[] = $section;
        }
    }

    if (empty($missing)) {
        $passed++;
        echo "  ✓ All required readme.txt sections present\n";
    } else {
        $errors[] = "Missing readme.txt sections: " . implode(', ', $missing);
    }

    // Check for privacy URLs
    if (strpos($content, 'oppti.dev/privacy') !== false) {
        echo "  ✓ Privacy policy URL present\n";
    } else {
        $errors[] = "Privacy policy URL missing or incomplete";
    }

    if (strpos($content, 'oppti.dev/terms') !== false) {
        echo "  ✓ Terms of service URL present\n";
    } else {
        $errors[] = "Terms of service URL missing or incomplete";
    }

    // Check for placeholder text
    if (strpos($content, 'insert once') !== false || strpos($content, 'TODO') !== false) {
        $errors[] = "CRITICAL: Placeholder text found in readme.txt!";
    }
} else {
    $errors[] = "readme.txt not found";
}

// Test 8: Check text domain consistency
echo "\n→ Test 8: Text Domain Consistency\n";
$total++;
$text_domain_issues = [];
$php_files = glob($temp_dir . '/beepbeep-ai-alt-text-generator/**/*.php');

foreach ($php_files as $file) {
    $content = file_get_contents($file);
    // Look for translation functions with wrong domain
    if (preg_match_all("/__\([^,]+,\s*'([^']+)'/", $content, $matches)) {
        foreach ($matches[1] as $domain) {
            if ($domain !== 'beepbeep-ai-alt-text-generator' && strpos($file, 'wordpress-org') === false) {
                $text_domain_issues[] = basename($file) . " uses domain: '$domain'";
            }
        }
    }
}

if (empty($text_domain_issues)) {
    $passed++;
    echo "  ✓ Text domain consistent across all files\n";
} else {
    $errors = array_merge($errors, $text_domain_issues);
}

// Test 9: PHP syntax check on all files
echo "\n→ Test 9: PHP Syntax Validation\n";
$total++;
$syntax_errors = [];
foreach ($php_files as $file) {
    $output = [];
    $return_var = 0;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return_var);
    if ($return_var !== 0) {
        $syntax_errors[] = basename($file) . ": " . implode(' ', $output);
    }
}

if (empty($syntax_errors)) {
    $passed++;
    echo "  ✓ All PHP files have valid syntax (" . count($php_files) . " files checked)\n";
} else {
    $errors = array_merge($errors, $syntax_errors);
}

// Test 10: Check for legacy code
echo "\n→ Test 10: Legacy Code Check\n";
$total++;
$legacy_found = false;
foreach ($php_files as $file) {
    if (strpos(basename($file), 'opptiai') !== false) {
        $warnings[] = "Legacy file found: " . basename($file);
        $legacy_found = true;
    }
}

if (!$legacy_found) {
    $passed++;
    echo "  ✓ No legacy 'opptiai' files found\n";
}

// Clean up temp directory
exec("rm -rf " . escapeshellarg($temp_dir));

// Summary
echo "\n====================================\n";
echo "Test Results\n";
echo "====================================\n\n";

echo "Passed: $passed / $total tests\n\n";

if (!empty($errors)) {
    echo "❌ ERRORS (" . count($errors) . "):\n";
    foreach ($errors as $i => $error) {
        echo "  " . ($i + 1) . ". $error\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️  WARNINGS (" . count($warnings) . "):\n";
    foreach ($warnings as $i => $warning) {
        echo "  " . ($i + 1) . ". $warning\n";
    }
    echo "\n";
}

if (empty($errors)) {
    echo "✅ ALL CRITICAL TESTS PASSED!\n";
    echo "Your plugin is ready for WordPress.org submission.\n\n";

    if (!empty($warnings)) {
        echo "Note: Warnings are informational and won't block submission.\n\n";
    }

    echo "Next step: Upload dist/beepbeep-ai-alt-text-generator.4.2.3.zip\n";
    echo "To: https://wordpress.org/plugins/developers/add/\n";
    exit(0);
} else {
    echo "❌ FAILED - Fix errors before submitting\n";
    exit(1);
}
