<?php
$root = realpath(__DIR__ . '/..');
$domain = 'beepbeep-ai-alt-text-generator';
$entries = [];

$singleFunctions = ['__','_e','esc_html__','esc_html_e','esc_attr__','esc_attr_e'];
$contextFunctions = ['_x','_ex','esc_html_x','esc_attr_x'];
$pluralFunctions = ['_n','_n_noop','_ngettext'];
$pluralContextFunctions = ['_nx','_nx_noop'];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $relativePath = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    $code = file_get_contents($path);
    if ($code === false) {
        continue;
    }

    $patterns = [];

    $funcList = implode('|', array_map('preg_quote', $singleFunctions));
    $patterns[] = [
        'type' => 'single',
        'regex' => '/(?P<func>' . $funcList . ')\s*\(\s*(?P<quote>[\"\"])' .
            '(?P<msg>(?:\\.|(?!' . '\\' . '?\k<quote>).)*)' . '\k<quote>\s*,\s*(?P=quote)' .
            $domain . '\k<quote>/s'
    ];

    $funcList = implode('|', array_map('preg_quote', $contextFunctions));
    $patterns[] = [
        'type' => 'context',
        'regex' => '/(?P<func>' . $funcList . ')\s*\(\s*(?P<quote>[\"\"])' .
            '(?P<msg>(?:\\.|(?!' . '\\' . '?\k<quote>).)*)' . '\k<quote>\s*,\s*(?P<contextQuote>[\"\"])' .
            '(?P<context>(?:\\.|(?!' . '\\' . '?\k<contextQuote>).)*)' . '\k<contextQuote>\s*,\s*(?P=quote)' .
            $domain . '\k<quote>/s'
    ];

    $funcList = implode('|', array_map('preg_quote', $pluralFunctions));
    $patterns[] = [
        'type' => 'plural',
        'regex' => '/(?P<func>' . $funcList . ')\s*\(\s*(?P<quote>[\"\"])' .
            '(?P<singular>(?:\\.|(?!' . '\\' . '?\k<quote>).)*)' . '\k<quote>\s*,\s*(?P=quote)' .
            '(?P<plural>(?:\\.|(?!' . '\\' . '?\k<quote>).)*)' . '\k<quote>\s*,\s*[^,]+,\s*(?P=quote)' .
            $domain . '\k<quote>/s'
    ];

    $funcList = implode('|', array_map('preg_quote', $pluralContextFunctions));
    $patterns[] = [
        'type' => 'plural_context',
        'regex' => '/(?P<func>' . $funcList . ')\s*\(\s*(?P<quote>[\"\"])' .
            '(?P<singular>(?:\\.|(?!' . '\\' . '?\k<quote>).)*)' . '\k<quote>\s*,\s*(?P=quote)' .
            '(?P<plural>(?:\\.|(?!' . '\\' . '?\k<quote>).)*)' . '\k<quote>\s*,\s*(?P<contextQuote>[\"\"])' .
            '(?P<context>(?:\\.|(?!' . '\\' . '?\k<contextQuote>).)*)' . '\k<contextQuote>\s*,\s*[^,]+,\s*(?P=quote)' .
            $domain . '\k<quote>/s'
    ];

    foreach ($patterns as $pattern) {
        if (!preg_match_all($pattern['regex'], $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            continue;
        }
        foreach ($matches as $match) {
            $line = substr_count(substr($code, 0, $match[0][1]), "\n") + 1;
            switch ($pattern['type']) {
                case 'single':
                    $msgid = stripcslashes($match['msg'][0]);
                    add_entry($entries, $msgid, null, null, $relativePath, $line);
                    break;
                case 'context':
                    $msgid = stripcslashes($match['msg'][0]);
                    $context = stripcslashes($match['context'][0]);
                    add_entry($entries, $msgid, null, $context, $relativePath, $line);
                    break;
                case 'plural':
                    $singular = stripcslashes($match['singular'][0]);
                    $plural = stripcslashes($match['plural'][0]);
                    add_entry($entries, $singular, $plural, null, $relativePath, $line);
                    break;
                case 'plural_context':
                    $singular = stripcslashes($match['singular'][0]);
                    $plural = stripcslashes($match['plural'][0]);
                    $context = stripcslashes($match['context'][0]);
                    add_entry($entries, $singular, $plural, $context, $relativePath, $line);
                    break;
            }
        }
    }
}

if (!is_dir($root . '/languages')) {
    mkdir($root . '/languages', 0777, true);
}

$output = $root . '/languages/' . $domain . '.pot';
$fh = fopen($output, 'w');
if (!$fh) {
    fwrite(STDERR, "Unable to write POT file\n");
    exit(1);
}

$header = "msgid \"\"\n" .
    "msgstr \"\"\n" .
    "Project-Id-Version: Alt Text AI - Image SEO Automation\n" .
    "Report-Msgid-Bugs-To: https://oppti.dev\n" .
    "POT-Creation-Date: " . gmdate('Y-m-d H:i:s') . "+0000\n" .
    "PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\n" .
    "Last-Translator: FULL NAME <EMAIL@ADDRESS>\n" .
    "Language-Team: LANGUAGE <LL@li.org>\n" .
    "Language: \n" .
    "MIME-Version: 1.0\n" .
    "Content-Type: text/plain; charset=UTF-8\n" .
    "Content-Transfer-Encoding: 8bit\n" .
    "Plural-Forms: nplurals=INTEGER; plural=EXPRESSION;\n" .
    "X-Generator: scripts/make-pot.php\n\n";

fwrite($fh, $header);

ksort($entries, SORT_STRING);

foreach ($entries as $entry) {
    if (!empty($entry['references'])) {
        fwrite($fh, '#: ' . implode(' ', $entry['references']) . "\n");
    }
    if ($entry['context'] !== null) {
        fwrite($fh, 'msgctxt ' . format_po_string($entry['context']) . "\n");
    }
    fwrite($fh, 'msgid ' . format_po_string($entry['msgid']) . "\n");
    if ($entry['msgid_plural'] !== null) {
        fwrite($fh, 'msgid_plural ' . format_po_string($entry['msgid_plural']) . "\n");
        fwrite($fh, "msgstr[0] \"\"\nmsgstr[1] \"\"\n\n");
    } else {
        fwrite($fh, "msgstr \"\"\n\n");
    }
}

fclose($fh);
echo "Generated POT at {$output}\n";

function add_entry(&$entries, $msgid, $msgid_plural, $context, $file, $line) {
    if ($msgid === '') {
        return;
    }
    $key = ($context ?? '') . "\0" . $msgid . "\0" . ($msgid_plural ?? '');
    if (!isset($entries[$key])) {
        $entries[$key] = [
            'msgid' => $msgid,
            'msgid_plural' => $msgid_plural,
            'context' => $context,
            'references' => []
        ];
    }
    $entries[$key]['references'][] = $file . ':' . $line;
}

function format_po_string($string) {
    $escaped = addcslashes($string, "\0\\\"\n\r");
    $escaped = str_replace(["\r\n", "\n"], "\\n\n", $escaped);
    return '"' . $escaped . '"';
}
