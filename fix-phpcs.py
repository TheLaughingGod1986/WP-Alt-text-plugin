#!/usr/bin/env python3
"""
Fix non-auto-fixable PHPCS WordPress coding standard violations:
 - WordPress.PHP.YodaConditions: flip $var === 'literal' → 'literal' === $var
 - Universal.Operators.DisallowShortTernary: $a ?: $b → $a ? $a : $b
"""
import re
import sys
import os

# ── Yoda conditions ─────────────────────────────────────────────────────────
# Matches:  $variable_expr  (===|!==|==|!=)  static_literal
# Groups:   (1) left var expr  (2) operator+spaces  (3) literal
#
# Left side: $var, $obj->prop, $obj->prop->sub, $arr['key'], $arr[$key]
# Literal:   null/true/false (case-insensitive), integer/float, single/double quoted string

_VAR_EXPR = (
    r'\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*'   # $var
    r'(?:'
    r"(?:\[(?:['\"]?[a-zA-Z0-9_\-\s]+['\"]?|\$[a-zA-Z_][a-zA-Z0-9_]*)\])*"  # [key] or [$k]
    r'(?:->(?:[a-zA-Z_][a-zA-Z0-9_]*))*'              # ->prop chain
    r')*'
)

_LITERAL = (
    r'(?:'
    r'null|true|false|NULL|TRUE|FALSE'               # booleans/null
    r'|-?\d+(?:\.\d+)?'                              # numbers
    r"|'(?:[^'\\]|\\.)*'"                            # 'single quoted'
    r'|"(?:[^"\\]|\\.)*"'                            # "double quoted"
    r')'
)

YODA_RE = re.compile(
    r'(' + _VAR_EXPR + r')'
    r'(\s*(?:===|!==|==|!=)\s*)'
    r'(' + _LITERAL + r')',
)


def fix_yoda_in_line(line):
    """Swap $var OP literal → literal OP $var."""
    def _swap(m):
        left, op, right = m.group(1), m.group(2), m.group(3)
        # Normalise spacing around operator to single space each side
        op_stripped = op.strip()
        return f'{right} {op_stripped} {left}'
    return YODA_RE.sub(_swap, line)


# ── Short ternary (?:) ───────────────────────────────────────────────────────
# $expr ?: $default   →   $expr ? $expr : $default
# Only handles cases where the left side is a simple $var or $obj->prop
# (function-call left sides are left alone to avoid double-evaluation).

_SIMPLE_LEFT = (
    r'\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*'
    r'(?:'
    r"(?:\[(?:['\"]?[a-zA-Z0-9_\-\s]+['\"]?)\])*"
    r'(?:->(?:[a-zA-Z_][a-zA-Z0-9_]*))*'
    r')*'
)

SHORT_TERNARY_RE = re.compile(
    r'(' + _SIMPLE_LEFT + r')'   # (1) the left-side expression
    r'(\s*\?:\s*)'               # (2) ?: with optional spaces
)


def fix_short_ternary_in_line(line):
    """Replace $expr ?: $default with $expr ? $expr : $default."""
    def _expand(m):
        expr = m.group(1)
        return f'{expr} ? {expr} : '
    return SHORT_TERNARY_RE.sub(_expand, line)


# ── File processor ───────────────────────────────────────────────────────────

def fix_file(path):
    with open(path, 'r', encoding='utf-8', errors='replace') as f:
        original = f.read()

    lines = original.splitlines(keepends=True)
    new_lines = []
    changed = False

    in_comment = False
    for line in lines:
        # Track block comments so we don't touch /* ... */ content
        stripped = line.strip()
        if '/*' in line and '*/' not in line:
            in_comment = True
        if '*/' in line:
            in_comment = False

        # Skip comment lines and heredoc-ish strings (rough heuristic)
        if in_comment or stripped.startswith('*') or stripped.startswith('//') or stripped.startswith('#'):
            new_lines.append(line)
            continue

        new_line = line
        new_line = fix_yoda_in_line(new_line)
        new_line = fix_short_ternary_in_line(new_line)

        if new_line != line:
            changed = True
        new_lines.append(new_line)

    if changed:
        with open(path, 'w', encoding='utf-8') as f:
            f.writelines(new_lines)
        return True
    return False


def main():
    import glob
    import subprocess

    root = os.path.dirname(os.path.abspath(__file__))

    # Collect plugin PHP files
    php_files = []
    for dirpath, dirnames, filenames in os.walk(root):
        # Prune excluded dirs
        dirnames[:] = [
            d for d in dirnames
            if d not in ('node_modules', 'vendor', 'tests', '.wporg-svn', '.git')
        ]
        for fname in filenames:
            if fname.endswith('.php'):
                php_files.append(os.path.join(dirpath, fname))

    fixed = 0
    for path in sorted(php_files):
        if fix_file(path):
            fixed += 1
            print(f'  fixed: {os.path.relpath(path, root)}')

    print(f'\nTotal files modified: {fixed}')


if __name__ == '__main__':
    main()
