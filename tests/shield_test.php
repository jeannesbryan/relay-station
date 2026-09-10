<?php
// Regression test: core/ssl_shield.php must not be switchable by a header.
//
// v7.3 trusted HTTP_X_FORWARDED_PROTO / HTTP_X_FORWARDED_SSL outright, so an
// attacker speaking plain HTTP could send `X-Forwarded-Proto: https` and turn
// the entire HTTPS enforcement off. Each spoof case below must stay blocked.
//
// Run: php tests/shield_test.php

$SEC   = __DIR__ . '/../core/security.php';
$SHIELD = __DIR__ . '/../core/ssl_shield.php';
$pass = 0; $fail = 0;

function verdict($server) {
    global $SEC, $SHIELD;
    $q = '$_SERVER = ' . var_export(array_merge(
            ['SCRIPT_NAME' => '/api_inbox.php', 'REQUEST_METHOD' => 'POST'], $server), true) . ';'
       . 'require ' . var_export($SEC, true) . ';'
       . 'require ' . var_export($SHIELD, true) . ';'
       . 'echo "PASSED_THROUGH";';
    $out = (string) shell_exec(PHP_BINARY . ' -r ' . escapeshellarg($q) . ' 2>/dev/null');
    if (strpos($out, 'SHIELD REFLECTED') !== false) return 'blocked';
    if (strpos($out, 'PASSED_THROUGH') !== false) return 'allowed';
    return 'unknown';
}

function check($server, $expected, $label) {
    global $pass, $fail;
    $got = verdict($server);
    if ($got === $expected) { $pass++; echo "  PASS  $label\n"; }
    else { $fail++; echo "  FAIL  $label   (expected $expected, got $got)\n"; }
}

echo "==================================================================\n";
echo(" ssl_shield.php - header spoofing resistance\n");
echo "==================================================================\n";

// Must be blocked: no genuine HTTPS signal anywhere, only forged ones.
check([], 'blocked', 'plain HTTP with no headers');
check(['HTTP_X_FORWARDED_PROTO' => 'https', 'REMOTE_ADDR' => '203.0.113.5'],
      'blocked', 'SPOOF: X-Forwarded-Proto=https from a non-Cloudflare peer');
check(['HTTP_X_FORWARDED_SSL' => 'on', 'REMOTE_ADDR' => '203.0.113.5'],
      'blocked', 'SPOOF: X-Forwarded-SSL=on from a non-Cloudflare peer');
check(['HTTP_CF_VISITOR' => '{"scheme":"https"}', 'REMOTE_ADDR' => '203.0.113.5'],
      'blocked', 'SPOOF: CF-Visitor=https from a non-Cloudflare peer');
// A peer inside a Cloudflare range IS trusted to forward the scheme. This is by
// design: REMOTE_ADDR is the TCP peer and cannot be set by the client, so a
// header from a genuine edge node is as good a signal as we can get.
check(['HTTP_X_FORWARDED_PROTO' => 'https', 'REMOTE_ADDR' => '104.16.0.9'],
      'allowed', 'trusted: header from a peer inside a Cloudflare range');

// Must be allowed: a genuine HTTPS signal.
check(['HTTPS' => 'on'], 'allowed', 'genuine HTTPS via the HTTPS server variable');
check(['SERVER_PORT' => 443], 'allowed', 'genuine HTTPS via SERVER_PORT 443');
check(['HTTP_X_FORWARDED_PROTO' => 'https', 'REMOTE_ADDR' => '173.245.48.10'],
      'allowed', 'genuine Cloudflare edge peer forwarding https');

echo "\n==================================================================\n";
printf(" RESULT: %d passed, %d failed\n", $pass, $fail);
echo "==================================================================\n";
exit($fail === 0 ? 0 : 1);
