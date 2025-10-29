<?php
// Read the current file
$content = file_get_contents('ai-alt-gpt.php');

// Replace wp_redirect with JavaScript approach
$content = str_replace(
    'wp_redirect($result[\'url\']);',
    '// Use JavaScript instead of redirect for CSP compliance
        echo "<script>window.open(\"" . $result["url"] . "\", \"_blank\");</script>";
        exit;',
    $content
);

// Write back to file
file_put_contents('ai-alt-gpt.php', $content);
echo "Updated checkout handling\n";
