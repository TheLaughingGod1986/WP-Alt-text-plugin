#!/usr/bin/env php
<?php
/**
 * API Connectivity & Integration Test
 * Tests all external service connections and critical workflows
 */

echo "====================================\n";
echo "API Connectivity & Integration Test\n";
echo "====================================\n\n";

$results = [];
$passed = 0;
$failed = 0;

// Test 1: Backend API Connectivity
echo "→ Test 1: Backend API (Oppti)\n";
$backend_url = 'https://alttext-ai-backend.onrender.com';
$ch = curl_init($backend_url . '/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($http_code >= 200 && $http_code < 300) {
    echo "  ✓ Backend API reachable (HTTP $http_code)\n";
    $results['backend_api'] = 'PASS';
    $passed++;
} elseif ($http_code === 404) {
    echo "  ⚠️  Backend API reachable but /health endpoint not found (trying root)\n";

    // Try root endpoint
    $ch = curl_init($backend_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 500) {
        echo "  ✓ Backend API is online (HTTP $http_code)\n";
        $results['backend_api'] = 'PASS';
        $passed++;
    } else {
        echo "  ✗ Backend API unreachable (HTTP $http_code)\n";
        $results['backend_api'] = 'FAIL';
        $failed++;
    }
} else {
    echo "  ✗ Backend API unreachable: $error (HTTP $http_code)\n";
    $results['backend_api'] = 'FAIL';
    $failed++;
}

// Test 2: OpenAI API Accessibility
echo "\n→ Test 2: OpenAI API\n";
$ch = curl_init('https://api.openai.com/v1/models');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 401 is expected (no API key), means API is reachable
if ($http_code === 401 || ($http_code >= 200 && $http_code < 300)) {
    echo "  ✓ OpenAI API reachable (HTTP $http_code - API key required, as expected)\n";
    $results['openai_api'] = 'PASS';
    $passed++;
} else {
    echo "  ✗ OpenAI API unreachable (HTTP $http_code)\n";
    $results['openai_api'] = 'FAIL';
    $failed++;
}

// Test 3: Stripe API Accessibility
echo "\n→ Test 3: Stripe Checkout\n";
$ch = curl_init('https://api.stripe.com/v1/payment_intents');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 401 is expected (no API key), means API is reachable
if ($http_code === 401) {
    echo "  ✓ Stripe API reachable (HTTP $http_code - API key required, as expected)\n";
    $results['stripe_api'] = 'PASS';
    $passed++;
} else {
    echo "  ⚠️  Stripe API response: HTTP $http_code (expected 401)\n";
    $results['stripe_api'] = 'WARN';
}

// Test 4: Privacy & Terms URLs
echo "\n→ Test 4: Legal URLs\n";
$urls_to_check = [
    'Privacy Policy' => 'https://oppti.dev/privacy',
    'Terms of Service' => 'https://oppti.dev/terms',
];

foreach ($urls_to_check as $name => $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        echo "  ✓ $name accessible (HTTP $http_code)\n";
        $passed++;
    } else {
        echo "  ✗ $name NOT accessible (HTTP $http_code)\n";
        echo "    URL: $url\n";
        $failed++;
    }
}

// Test 5: Plugin Class Structure
echo "\n→ Test 5: Plugin Class Structure\n";
$temp_dir = sys_get_temp_dir() . '/bbai-structure-test-' . uniqid();
mkdir($temp_dir, 0777, true);

$zip = new ZipArchive();
if ($zip->open(__DIR__ . '/dist/beepbeep-ai-alt-text-generator.4.2.3.zip') === true) {
    $zip->extractTo($temp_dir);
    $zip->close();

    // Check critical classes exist
    $critical_classes = [
        'admin/class-bbai-core.php' => [
            'class Core',
            'function generate_and_save',
            'function get_api_client',
            'function ajax_login',
            'function ajax_register',
        ],
        'includes/class-api-client-v2.php' => [
            'class API_Client_V2',
            'function get_token',
            'function set_token',
            'function get_license_key',
        ],
        'admin/class-bbai-rest-controller.php' => [
            'class REST_Controller',
            'function register_routes',
            'function handle_generate_single',
            'function can_edit_media',
        ],
        'includes/class-usage-tracker.php' => [
            'class Usage_Tracker',
        ],
    ];

    $structure_ok = true;
    foreach ($critical_classes as $file => $methods) {
        $full_path = $temp_dir . '/beepbeep-ai-alt-text-generator/' . $file;
        if (!file_exists($full_path)) {
            echo "  ✗ Missing critical file: $file\n";
            $structure_ok = false;
            continue;
        }

        $content = file_get_contents($full_path);
        foreach ($methods as $method) {
            if (strpos($content, $method) === false) {
                echo "  ✗ Missing method '$method' in $file\n";
                $structure_ok = false;
            }
        }
    }

    if ($structure_ok) {
        echo "  ✓ All critical classes and methods present\n";
        $passed++;
    } else {
        $failed++;
    }

    // Clean up
    exec("rm -rf " . escapeshellarg($temp_dir));
} else {
    echo "  ✗ Failed to open ZIP for structure test\n";
    $failed++;
}

// Test 6: Database Table Creation Code
echo "\n→ Test 6: Database Schema Definitions\n";
$activator_file = $temp_dir . '-activator';
mkdir($activator_file, 0777, true);

$zip = new ZipArchive();
if ($zip->open(__DIR__ . '/dist/beepbeep-ai-alt-text-generator.4.2.3.zip') === true) {
    $zip->extractTo($activator_file);
    $zip->close();

    $includes_path = $activator_file . '/beepbeep-ai-alt-text-generator/includes';
    $db_tables_found = 0;

    // Check for table creation code
    $files_to_check = glob($includes_path . '/*.php');
    foreach ($files_to_check as $file) {
        $content = file_get_contents($file);
        if (strpos($content, 'CREATE TABLE') !== false) {
            $db_tables_found++;
        }
    }

    if ($db_tables_found > 0) {
        echo "  ✓ Database schema creation code found ($db_tables_found tables)\n";
        $passed++;
    } else {
        echo "  ⚠️  No database table creation code found\n";
    }

    exec("rm -rf " . escapeshellarg($activator_file));
}

// Summary
echo "\n====================================\n";
echo "Summary\n";
echo "====================================\n\n";

echo "Passed: $passed tests\n";
echo "Failed: $failed tests\n\n";

if ($failed === 0) {
    echo "✅ ALL API CONNECTIVITY TESTS PASSED!\n";
    echo "All external services are reachable and plugin structure is valid.\n\n";
    exit(0);
} else {
    echo "⚠️  SOME TESTS FAILED\n";
    echo "Review failed tests above. Critical: Backend API and Legal URLs must be accessible.\n\n";

    if (isset($results['backend_api']) && $results['backend_api'] === 'FAIL') {
        echo "❌ CRITICAL: Backend API is not reachable!\n";
        echo "   This will prevent login, usage tracking, and alt text generation.\n";
        echo "   Verify https://alttext-ai-backend.onrender.com is online.\n\n";
    }

    exit(1);
}
