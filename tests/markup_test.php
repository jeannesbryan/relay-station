<?php
// Markup test: static checks on the HTML forms and their submit wiring.
//
// WHY THIS EXISTS: v8.0.1 was a hotfix for a button that did nothing. The cause
// was a <form> nested inside another <form> - invalid HTML that the parser
// resolves silently by closing the OUTER form early, orphaning every control
// after it. Nothing about that is visible in a linter, in `php -l`, or in a
// template review; the damage only appears when a real browser parses the page.
// It also took two further buttons down with it, unreported.
//
// These checks read the source instead, so the same shape cannot come back.
//
// Run: php tests/markup_test.php

$ROOT = dirname(__DIR__);

$pass = 0; $fail = 0;

function check($label, $cond, $extra = '')
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  PASS  $label\n"; }
    else { $fail++; echo "  FAIL  $label" . ($extra !== '' ? "   [$extra]" : '') . "\n"; }
}

// Every PHP file that emits HTML.
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $rel = str_replace($ROOT . '/', '', $f->getPathname());
    if (substr($rel, -4) !== '.php') { continue; }
    if (strpos($rel, 'tests/') === 0) { continue; }
    if (strpos($rel, 'vendor/') === 0) { continue; }
    $files[] = $rel;
}
sort($files);

echo "== no nested forms ==\n";
$nestedTotal = 0;
$orphanTotal = 0;
$unbalanced = [];

foreach ($files as $rel) {
    $src = file_get_contents($ROOT . '/' . $rel);

    // Strip HTML comments first: a comment that merely MENTIONS a form tag is
    // documentation, not markup, and must not be counted. (An earlier revision
    // of the v8.0.2 fix tripped exactly this way.)
    $scan = preg_replace('/<!--.*?-->/s', '', $src);

    $depth = 0; $stack = []; $nested = 0; $orphan = 0;
    if (preg_match_all('/<form\b[^>]*>|<\/form>/i', $scan, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as $hit) {
            $tok = $hit[0];
            $line = substr_count(substr($scan, 0, $hit[1]), "\n") + 1;
            if (stripos($tok, '</') === 0) {
                if ($stack) { array_pop($stack); $depth--; }
                else { $orphan++; echo "        orphan </form> at $rel:$line\n"; }
            } else {
                if ($depth > 0) { $nested++; echo "        nested form at $rel:$line\n"; }
                $stack[] = $line; $depth++;
            }
        }
    }
    $nestedTotal += $nested;
    $orphanTotal += $orphan;
    if ($stack) { $unbalanced[] = $rel . ' (unclosed at line ' . implode(',', $stack) . ')'; }
}

check('no form element is nested inside another', $nestedTotal === 0, "$nestedTotal nested");
check('no orphan closing form tag', $orphanTotal === 0, "$orphanTotal orphan");
check('every form tag is closed', $unbalanced === [], implode('; ', $unbalanced));

echo "\n== form=\"...\" references resolve ==\n";
$badRefs = [];
$refCount = 0;
foreach ($files as $rel) {
    $src = preg_replace('/<!--.*?-->/s', '', file_get_contents($ROOT . '/' . $rel));

    // Ids of every form declared in this file.
    $ids = [];
    if (preg_match_all('/<form\b[^>]*\bid=["\']([^"\']+)["\']/i', $src, $mm)) {
        $ids = $mm[1];
    }
    // Every form="" attribute (buttons outside their form use this).
    if (preg_match_all('/\bform=["\']([^"\']+)["\']/i', $src, $mm)) {
        foreach ($mm[1] as $ref) {
            $refCount++;
            if (!in_array($ref, $ids, true)) {
                // The target may legitimately live in another file (a partial),
                // so only flag it when NO file declares it at all.
                $foundElsewhere = false;
                foreach ($files as $other) {
                    $osrc = preg_replace('/<!--.*?-->/s', '', file_get_contents($ROOT . '/' . $other));
                    if (preg_match('/<form\b[^>]*\bid=["\']' . preg_quote($ref, '/') . '["\']/i', $osrc)) {
                        $foundElsewhere = true; break;
                    }
                }
                if (!$foundElsewhere) { $badRefs[] = "$rel -> $ref"; }
            }
        }
    }
}
check('every form="" attribute points at a real form id', $badRefs === [], implode('; ', $badRefs));
echo "        ($refCount form=\"\" references checked)\n";

echo "\n== submit buttons have a form owner ==\n";
// A submit button is fine if it is inside a form element, or carries a form=""
// attribute. Anything else is inert in a browser - which is precisely what the
// APPLY_CONFIGURATION button was in v8.0.
$inert = [];
$submitCount = 0;
foreach ($files as $rel) {
    $src = preg_replace('/<!--.*?-->/s', '', file_get_contents($ROOT . '/' . $rel));

    foreach ($files as $other) { /* ids may be declared anywhere */ }

    // Walk the file, tracking form depth, and note submit buttons.
    if (preg_match_all('/<form\b[^>]*>|<\/form>|<button\b[^>]*>/i', $src, $m, PREG_OFFSET_CAPTURE)) {
        $depth = 0;
        foreach ($m[0] as $hit) {
            $tok = $hit[0];
            $line = substr_count(substr($src, 0, $hit[1]), "\n") + 1;
            if (stripos($tok, '</form') === 0) { $depth = max(0, $depth - 1); continue; }
            if (stripos($tok, '<form') === 0) { $depth++; continue; }

            if (stripos($tok, 'type="submit"') === false && stripos($tok, "type='submit'") === false) {
                continue;
            }
            $submitCount++;
            $hasFormAttr = (bool) preg_match('/\bform=["\'][^"\']+["\']/i', $tok);
            if ($depth === 0 && !$hasFormAttr) {
                $inert[] = "$rel:$line";
            }
        }
    }
}
check('no inert submit button (inside a form or has form="")', $inert === [], implode('; ', $inert));
echo "        ($submitCount submit buttons checked)\n";

echo "\n==================================================================\n";
printf(" MARKUP: %d passed, %d failed\n", $pass, $fail);
echo "==================================================================\n";
exit($fail === 0 ? 0 : 1);
