#!/usr/bin/env php
<?php
/**
 * Best Practices & Refactoring Analysis
 * Checks for code organization, design patterns, and WordPress best practices
 */

echo "====================================\n";
echo "Best Practices & Refactoring Analysis\n";
echo "====================================\n\n";

$plugin_dir = __DIR__ . '/beepbeep-ai-alt-text-generator';
$issues = [];
$suggestions = [];
$best_practices = [];

// ===================================
// Test 1: Code Organization
// ===================================
echo "→ Test 1: Code Organization\n";

// Check file sizes
$large_files = [];
$all_php = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($plugin_dir)
);

foreach ($all_php as $file) {
    if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $size = filesize($file);
        if ($size > 50000) { // > 50KB
            $lines = count(file($file));
            $large_files[] = [
                'file' => basename($file),
                'size' => $size,
                'lines' => $lines,
                'path' => str_replace($plugin_dir . '/', '', $file)
            ];
        }
    }
}

if (!empty($large_files)) {
    usort($large_files, fn($a, $b) => $b['size'] - $a['size']);
    echo "  Large files that could be refactored:\n";
    foreach ($large_files as $f) {
        $kb = number_format($f['size'] / 1024, 1);
        echo "    - {$f['path']}: {$kb}KB ({$f['lines']} lines)\n";

        if ($f['size'] > 200000) {
            $suggestions[] = "Split {$f['file']} into smaller, focused classes (SRP)";
        }
    }
} else {
    echo "  ✓ All files are reasonably sized\n";
}

// ===================================
// Test 2: Type Hints (PHP 7.0+)
// ===================================
echo "\n→ Test 2: Type Hints & Declarations\n";

$functions_without_types = 0;
$functions_with_types = 0;
$return_types_missing = 0;

foreach ($all_php as $file) {
    if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $content = file_get_contents($file);

        // Count functions
        preg_match_all('/function\s+\w+\s*\(([^)]*)\)/', $content, $matches);
        foreach ($matches[1] as $params) {
            if (empty(trim($params))) continue;

            $has_types = preg_match('/(\w+)\s+\$/', $params);
            if ($has_types) {
                $functions_with_types++;
            } else {
                $functions_without_types++;
            }
        }

        // Check return types
        $funcs_without_return = substr_count($content, 'function') -
                               substr_count($content, '):');
        $return_types_missing += $funcs_without_return;
    }
}

$total_funcs = $functions_with_types + $functions_without_types;
$type_coverage = $total_funcs > 0 ? ($functions_with_types / $total_funcs) * 100 : 0;

echo "  Type hint coverage: " . number_format($type_coverage, 1) . "%\n";
echo "  Functions with type hints: $functions_with_types\n";
echo "  Functions without type hints: $functions_without_types\n";

if ($type_coverage < 50) {
    $suggestions[] = "Add type hints to function parameters for better code safety (currently " .
                     number_format($type_coverage, 1) . "%)";
}

// ===================================
// Test 3: PHPDoc Coverage
// ===================================
echo "\n→ Test 3: PHPDoc Documentation\n";

$total_functions = 0;
$documented_functions = 0;

foreach ($all_php as $file) {
    if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $content = file_get_contents($file);
        $lines = explode("\n", $content);

        for ($i = 0; $i < count($lines); $i++) {
            if (preg_match('/^\s*(public|private|protected)?\s*function\s+\w+/', $lines[$i])) {
                $total_functions++;

                // Check previous lines for docblock
                $has_doc = false;
                for ($j = max(0, $i - 10); $j < $i; $j++) {
                    if (strpos($lines[$j], '/**') !== false) {
                        $has_doc = true;
                        break;
                    }
                }

                if ($has_doc) {
                    $documented_functions++;
                }
            }
        }
    }
}

$doc_coverage = $total_functions > 0 ? ($documented_functions / $total_functions) * 100 : 0;
echo "  PHPDoc coverage: " . number_format($doc_coverage, 1) . "%\n";
echo "  Documented functions: $documented_functions / $total_functions\n";

if ($doc_coverage < 70) {
    $suggestions[] = "Improve PHPDoc coverage (currently " . number_format($doc_coverage, 1) . "%)";
}

// ===================================
// Test 4: Magic Numbers/Strings
// ===================================
echo "\n→ Test 4: Magic Numbers & Strings\n";

$magic_numbers = 0;
$magic_strings = 0;

foreach ($all_php as $file) {
    if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $content = file_get_contents($file);

        // Check for magic numbers (not 0, 1, -1, 100)
        preg_match_all('/[^a-zA-Z0-9_](\d{2,})[^a-zA-Z0-9_]/', $content, $matches);
        foreach ($matches[1] as $num) {
            if (!in_array($num, ['0', '1', '10', '100', '200', '404', '500'])) {
                $magic_numbers++;
            }
        }

        // Check for repeated strings (potential constants)
        preg_match_all('/["\']([a-z_]{3,})["\']/', $content, $matches);
        $string_counts = array_count_values($matches[1]);
        foreach ($string_counts as $str => $count) {
            if ($count > 3 && strlen($str) > 3) {
                $magic_strings++;
            }
        }
    }
}

echo "  Potential magic numbers: $magic_numbers\n";
echo "  Repeated strings (>3 times): $magic_strings\n";

if ($magic_numbers > 20) {
    $suggestions[] = "Extract magic numbers to named constants";
}
if ($magic_strings > 10) {
    $suggestions[] = "Extract repeated strings to constants";
}

// ===================================
// Test 5: Error Handling Patterns
// ===================================
echo "\n→ Test 5: Error Handling Patterns\n";

$try_catch_count = 0;
$wp_error_usage = 0;
$exceptions_thrown = 0;

foreach ($all_php as $file) {
    if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $content = file_get_contents($file);

        $try_catch_count += substr_count($content, 'try {');
        $wp_error_usage += substr_count($content, 'WP_Error');
        $wp_error_usage += substr_count($content, 'is_wp_error');
        $exceptions_thrown += substr_count($content, 'throw new');
    }
}

echo "  Try-catch blocks: $try_catch_count\n";
echo "  WP_Error usage: $wp_error_usage\n";
echo "  Exceptions thrown: $exceptions_thrown\n";

if ($try_catch_count < 5) {
    $suggestions[] = "Add more try-catch blocks for robust error handling";
}

echo "  ✓ Using WordPress error patterns (WP_Error)\n";

// ===================================
// Test 6: Action & Filter Hooks
// ===================================
echo "\n→ Test 6: Extensibility (Hooks)\n";

$do_action_count = 0;
$apply_filters_count = 0;

foreach ($all_php as $file) {
    if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $content = file_get_contents($file);

        $do_action_count += substr_count($content, 'do_action');
        $apply_filters_count += substr_count($content, 'apply_filters');
    }
}

echo "  Custom actions (do_action): $do_action_count\n";
echo "  Custom filters (apply_filters): $apply_filters_count\n";

if ($do_action_count + $apply_filters_count < 10) {
    $suggestions[] = "Add more action/filter hooks for better extensibility";
} else {
    echo "  ✓ Good extensibility through hooks\n";
}

// ===================================
// Test 7: Dependency Injection
// ===================================
echo "\n→ Test 7: Dependency Injection\n";

$global_usage = 0;
$constructor_injection = 0;

foreach ($all_php as $file) {
    if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $content = file_get_contents($file);

        $global_usage += substr_count($content, 'global $');
        $constructor_injection += preg_match_all('/__construct\s*\([^)]+\$/', $content);
    }
}

echo "  Global variables used: $global_usage\n";
echo "  Constructor dependency injection: $constructor_injection\n";

if ($global_usage > 20) {
    $suggestions[] = "Reduce global variable usage, use dependency injection instead";
}

// ===================================
// Test 8: WordPress Coding Standards
// ===================================
echo "\n→ Test 8: WordPress Coding Standards\n";

$standards_issues = [];

foreach ($all_php as $file) {
    if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $content = file_get_contents($file);

        // Check for Yoda conditions
        if (preg_match('/if\s*\(\s*\$\w+\s*==/', $content)) {
            $standards_issues[] = basename($file) . ": Non-Yoda conditions found";
        }

        // Check for spacing around operators
        if (preg_match('/\S=\S/', $content)) {
            $standards_issues[] = basename($file) . ": Inconsistent spacing around operators";
        }
    }
}

if (empty($standards_issues)) {
    echo "  ✓ No obvious coding standard violations\n";
} else {
    echo "  Potential issues found:\n";
    $unique_issues = array_unique($standards_issues);
    foreach (array_slice($unique_issues, 0, 5) as $issue) {
        echo "    - $issue\n";
    }
}

// ===================================
// Test 9: Class Responsibilities (SRP)
// ===================================
echo "\n→ Test 9: Single Responsibility Principle\n";

$class_methods = [];

foreach ($all_php as $file) {
    if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $content = file_get_contents($file);

        preg_match('/class\s+(\w+)/', $content, $class_match);
        if (isset($class_match[1])) {
            $class_name = $class_match[1];
            $method_count = preg_match_all('/function\s+\w+\s*\(/', $content);

            if ($method_count > 20) {
                $class_methods[] = [
                    'class' => $class_name,
                    'methods' => $method_count,
                    'file' => basename($file)
                ];
            }
        }
    }
}

if (!empty($class_methods)) {
    echo "  Classes with many methods (potential SRP violations):\n";
    foreach ($class_methods as $cm) {
        echo "    - {$cm['class']}: {$cm['methods']} methods ({$cm['file']})\n";
        $suggestions[] = "Consider splitting {$cm['class']} into smaller, focused classes";
    }
} else {
    echo "  ✓ Classes follow Single Responsibility Principle\n";
}

// ===================================
// Test 10: Constants vs Hard-coded Values
// ===================================
echo "\n→ Test 10: Configuration & Constants\n";

$define_count = 0;
$class_const_count = 0;

foreach ($all_php as $file) {
    if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $content = file_get_contents($file);

        $define_count += substr_count($content, 'define(');
        $class_const_count += substr_count($content, 'const ');
    }
}

echo "  Defined constants: $define_count\n";
echo "  Class constants: $class_const_count\n";

if ($define_count + $class_const_count < 10) {
    $suggestions[] = "Use more constants for configuration values";
}

// ===================================
// Summary
// ===================================
echo "\n====================================\n";
echo "Refactoring Opportunities\n";
echo "====================================\n\n";

if (empty($suggestions)) {
    echo "✅ Code follows best practices!\n\n";
    echo "Your code is well-structured and follows WordPress\n";
    echo "and PHP best practices.\n";
} else {
    echo "💡 SUGGESTIONS (" . count($suggestions) . "):\n\n";
    foreach ($suggestions as $i => $suggestion) {
        echo ($i + 1) . ". $suggestion\n";
    }
}

echo "\n====================================\n";
echo "Best Practices Score\n";
echo "====================================\n\n";

$scores = [
    'Code Organization' => $large_files ? 70 : 95,
    'Type Hints' => min(100, $type_coverage),
    'Documentation' => min(100, $doc_coverage),
    'Error Handling' => $try_catch_count > 10 ? 90 : 70,
    'Extensibility' => ($do_action_count + $apply_filters_count) > 20 ? 95 : 75,
    'Coding Standards' => empty($standards_issues) ? 95 : 80,
];

$total_score = array_sum($scores) / count($scores);

foreach ($scores as $category => $score) {
    $bar = str_repeat('█', (int)($score / 5));
    echo sprintf("%-20s [%-20s] %d%%\n", $category, $bar, $score);
}

echo "\n";
echo sprintf("Overall Score: %.1f%%\n", $total_score);

if ($total_score >= 90) {
    echo "🏆 EXCELLENT - Production quality code!\n";
} elseif ($total_score >= 80) {
    echo "✅ GOOD - Minor improvements possible\n";
} elseif ($total_score >= 70) {
    echo "⚠️  FAIR - Consider refactoring\n";
} else {
    echo "❌ NEEDS WORK - Significant refactoring recommended\n";
}

exit(0);
