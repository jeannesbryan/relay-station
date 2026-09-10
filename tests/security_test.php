<?php
// Adversarial test harness for core/security.php
// Run with: /tmp/bin/php /tmp/test_security.php

$SEC = __DIR__ . '/../core/security.php';
require_once $SEC;

$pass = 0; $fail = 0;
function ok($cond, $label, $extra = '') {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  PASS  $label\n"; }
    else { $fail++; echo "  FAIL  $label" . ($extra ? "   [$extra]" : '') . "\n"; }
}

echo "==================================================================\n";
echo " 1. CIDR CONTAINMENT (relay_ip_in_cidr)\n";
echo "==================================================================\n";
// Cloudflare 104.16.0.0/13 covers 104.16.0.0 - 104.23.255.255
ok(relay_ip_in_cidr('104.16.0.1', '104.16.0.0/13') === true,  '104.16.0.1 inside 104.16.0.0/13');
ok(relay_ip_in_cidr('104.23.255.255', '104.16.0.0/13') === true, '104.23.255.255 inside 104.16.0.0/13 (last)');
ok(relay_ip_in_cidr('104.24.0.0', '104.16.0.0/13') === false, '104.24.0.0 OUTSIDE 104.16.0.0/13 (first after)');
ok(relay_ip_in_cidr('104.15.255.255', '104.16.0.0/13') === false, '104.15.255.255 OUTSIDE (last before)');
ok(relay_ip_in_cidr('173.245.48.5', '173.245.48.0/20') === true, '173.245.48.5 inside /20');
ok(relay_ip_in_cidr('173.245.63.255', '173.245.48.0/20') === true, '173.245.63.255 inside /20 (last)');
ok(relay_ip_in_cidr('173.245.64.0', '173.245.48.0/20') === false, '173.245.64.0 outside /20');
ok(relay_ip_in_cidr('8.8.8.8', '104.16.0.0/13') === false, 'unrelated IP outside');
ok(relay_ip_in_cidr('2606:4700::1', '2606:4700::/32') === true, '2606:4700::1 inside 2606:4700::/32');
ok(relay_ip_in_cidr('2400:cb00::1', '2400:cb00::/32') === true, '2400:cb00::1 inside /32');
ok(relay_ip_in_cidr('2001:db8::1', '2606:4700::/32') === false, '2001:db8::1 outside');
ok(relay_ip_in_cidr('104.16.0.1', '2606:4700::/32') === false, 'v4 vs v6 CIDR => false (no crash)');
ok(relay_ip_in_cidr('not-an-ip', '104.16.0.0/13') === false, 'garbage input => false');
ok(relay_ip_in_cidr('1.2.3.4', '1.2.3.4') === false, 'CIDR without slash => false');
ok(relay_ip_in_cidr('2.2.2.2', '2.2.2.2/31') === true,  '/31 lower address matches');
ok(relay_ip_in_cidr('2.2.2.3', '2.2.2.2/31') === true,  '/31 upper address matches');
ok(relay_ip_in_cidr('2.2.2.4', '2.2.2.2/31') === false, '/31 next address does NOT match');

echo "\n==================================================================\n";
echo " 2. CLOUDFLARE RECOGNITION\n";
echo "==================================================================\n";
foreach (['173.245.48.1','104.16.0.1','172.64.5.5','131.0.72.1','2606:4700::1'] as $ip) {
    ok(relay_is_cloudflare_ip($ip) === true, "recognised as Cloudflare: $ip");
}
foreach (['8.8.8.8','127.0.0.1','192.168.1.1','1.1.1.1','2001:db8::1'] as $ip) {
    ok(relay_is_cloudflare_ip($ip) === false, "NOT Cloudflare: $ip");
}

echo "\n==================================================================\n";
echo " 3. CLIENT IP SPOOFING (the brute-force / rate-limit bypass)\n";
echo "==================================================================\n";
function fake_request(array $server) {
    $_SERVER = array_merge(['SCRIPT_NAME' => '/index.php', 'REQUEST_METHOD' => 'GET'], $server);
    return relay_client_ip();
}
$got = fake_request(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_CF_CONNECTING_IP' => '1.2.3.4', 'HTTP_X_FORWARDED_FOR' => '5.6.7.8']);
ok($got === '203.0.113.9', 'spoofed CF-Connecting-IP IGNORED from non-CF peer', "got $got");
$got = fake_request(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '5.6.7.8, 9.9.9.9']);
ok($got === '203.0.113.9', 'spoofed X-Forwarded-For IGNORED from non-CF peer', "got $got");
$a = fake_request(['REMOTE_ADDR'=>'198.51.100.7','HTTP_X_FORWARDED_FOR'=>'1.1.1.1']);
$b = fake_request(['REMOTE_ADDR'=>'198.51.100.7','HTTP_X_FORWARDED_FOR'=>'2.2.2.2']);
ok($a === $b && $a === '198.51.100.7', 'rotating XFF cannot change the rate-limit identity');
$got = fake_request(['REMOTE_ADDR' => '173.245.48.10', 'HTTP_CF_CONNECTING_IP' => '8.8.4.4']);
ok($got === '8.8.4.4', 'real CF peer: CF-Connecting-IP honoured', "got $got");
$got = fake_request(['REMOTE_ADDR' => '173.245.48.10', 'HTTP_X_FORWARDED_FOR' => '8.8.4.4, 173.245.48.10']);
ok($got === '8.8.4.4', 'real CF peer: left-most XFF entry used', "got $got");
$got = fake_request(['REMOTE_ADDR' => '173.245.48.10', 'HTTP_CF_CONNECTING_IP' => 'not-an-ip']);
ok($got === '173.245.48.10', 'CF peer with malformed header falls back to REMOTE_ADDR', "got $got");
ok(fake_request([]) === '0.0.0.0', 'missing REMOTE_ADDR => 0.0.0.0 (no crash)');
ok(relay_normalize_ip('1.2.3.4:5678') === '1.2.3.4', 'IPv4:port normalised');
ok(relay_normalize_ip('[2606:4700::1]:443') === '2606:4700::1', '[IPv6]:port normalised');
ok(relay_normalize_ip('::ffff:1.2.3.4') === '1.2.3.4', 'IPv4-mapped IPv6 normalised');

echo "\n==================================================================\n";
echo " 4. SSRF — relay_ip_is_public\n";
echo "==================================================================\n";
$must_block = [
    '127.0.0.1' => 'loopback', '127.1.2.3' => 'loopback range',
    '10.0.0.1' => 'private 10/8', '172.16.5.5' => 'private 172.16/12',
    '192.168.1.1' => 'private 192.168/16', '169.254.169.254' => 'cloud metadata',
    '0.0.0.0' => 'unspecified', '100.64.0.1' => 'CGNAT',
    '224.0.0.1' => 'multicast', '240.0.0.1' => 'reserved',
    '::1' => 'IPv6 loopback', 'fc00::1' => 'IPv6 unique-local',
    'fe80::1' => 'IPv6 link-local', 'ff00::1' => 'IPv6 multicast',
];
foreach ($must_block as $ip => $why) {
    ok(relay_ip_is_public($ip) === false, "blocked ($why): $ip");
}
foreach (['8.8.8.8','1.1.1.1','104.16.0.1','2606:4700::1'] as $ip) {
    ok(relay_ip_is_public($ip) === true, "allowed (public): $ip");
}

echo "\n==================================================================\n";
echo " 5. SSRF — relay_url_is_safe\n";
echo "==================================================================\n";
$blocked = [
    'http://example.com/'                 => 'plain HTTP scheme',
    'ftp://example.com/'                  => 'non-HTTP scheme',
    'file:///etc/passwd'                  => 'file scheme',
    'https://127.0.0.1/'                  => 'loopback literal',
    'https://localhost/'                  => 'localhost name',
    'https://169.254.169.254/latest/meta-data/' => 'metadata endpoint',
    'https://10.0.0.5/'                   => 'private literal',
    'https://[::1]/'                      => 'IPv6 loopback literal',
    'https://example.com:8080/'           => 'non-standard port',
    'https://example.com:22/'             => 'port 22',
    'https://user:pass@example.com/'      => 'embedded credentials',
    'https://foo.local/'                  => '.local name',
    'https://svc.internal/'               => '.internal name',
    'https:///'                           => 'no host',
    ''                                    => 'empty string',
    'not a url'                           => 'garbage',
];
foreach ($blocked as $url => $why) {
    $r = relay_url_is_safe($url, $res);
    ok($r !== true, "rejected ($why): " . ($url === '' ? '<empty>' : $url), "verdict=" . var_export($r, true));
}
$r = relay_url_is_safe('https://one.one.one.one/', $res);
ok($r === true, 'allowed: https://one.one.one.one/', "verdict=" . var_export($r, true));
ok(is_array($res) && count($res) > 0, 'resolved addresses returned for pinning', json_encode($res));

echo "\n==================================================================\n";
echo " 6. CSRF\n";
echo "==================================================================\n";
$_SESSION = [];
$t1 = relay_csrf_token();
$t2 = relay_csrf_token();
ok(strlen($t1) === 64, 'token is 64 hex chars (32 bytes)', "len=" . strlen($t1));
ok($t1 === $t2, 'token is stable within a session');
ok(relay_csrf_verify($t1) === true, 'valid token verifies');
ok(relay_csrf_verify('deadbeef') === false, 'wrong token rejected');
ok(relay_csrf_verify('') === false, 'empty token rejected');
ok(relay_csrf_verify(null) === false, 'null token rejected (no crash)');
ok(relay_csrf_verify(str_repeat('a', 64)) === false, 'same-length wrong token rejected');
$_SESSION = [];
$t3 = relay_csrf_token();
ok($t3 !== $t1, 'token differs for a new session');
$field = relay_csrf_field();
ok(strpos($field, 'name="relay_csrf"') !== false, 'field renders hidden input');
ok(strpos($field, htmlspecialchars($t3, ENT_QUOTES, 'UTF-8')) !== false, 'field carries the token');

echo "\n==================================================================\n";
echo " 7. VISIBILITY ALLOWLIST\n";
echo "==================================================================\n";
ok(relay_require_enum('public', RELAY_VISIBILITY_VALUES) === 'public', 'public accepted');
ok(relay_require_enum('sonar_pulse', RELAY_VISIBILITY_VALUES) === 'sonar_pulse', 'sonar_pulse accepted');
ok(relay_require_enum('global_purge', RELAY_VISIBILITY_VALUES) === 'global_purge', 'global_purge accepted');
ok(relay_require_enum('<script>x</script>', RELAY_VISIBILITY_VALUES) === null, 'markup rejected');
ok(relay_require_enum('PUBLIC', RELAY_VISIBILITY_VALUES) === null, 'case variant rejected (exact match)');
ok(relay_require_enum('public ', RELAY_VISIBILITY_VALUES) === null, 'trailing space rejected');
ok(relay_require_enum('anything_else', RELAY_VISIBILITY_VALUES) === null, 'unknown value rejected');
ok(relay_require_enum('bogus', RELAY_VISIBILITY_VALUES, 'public') === 'public', 'default used for invalid');
ok(count(RELAY_VISIBILITY_VALUES) === 7, 'exactly 7 visibility values defined');

echo "\n==================================================================\n";
echo " 8. HTTPS DETECTION (shield bypass)\n";
echo "==================================================================\n";
function https_test(array $server) {
    $_SERVER = array_merge(['SCRIPT_NAME' => '/index.php'], $server);
    return relay_is_https();
}
ok(https_test(['HTTPS' => 'on']) === true, 'HTTPS=on detected');
ok(https_test(['SERVER_PORT' => 443]) === true, 'port 443 detected');
ok(https_test([]) === false, 'plain HTTP is NOT secure');
ok(https_test(['HTTP_X_FORWARDED_PROTO' => 'https', 'REMOTE_ADDR' => '203.0.113.5']) === false,
   'BLOCKED: spoofed X-Forwarded-Proto from non-CF peer cannot fake HTTPS');
ok(https_test(['HTTP_CF_VISITOR' => '{"scheme":"https"}', 'REMOTE_ADDR' => '203.0.113.5']) === false,
   'BLOCKED: spoofed CF-Visitor from non-CF peer cannot fake HTTPS');
ok(https_test(['HTTP_X_FORWARDED_PROTO' => 'https', 'REMOTE_ADDR' => '173.245.48.10']) === true,
   'real CF peer with X-Forwarded-Proto=https is trusted');
ok(https_test(['HTTP_CF_VISITOR' => '{"scheme":"https"}', 'REMOTE_ADDR' => '173.245.48.10']) === true,
   'real CF peer with CF-Visitor https is trusted');
ok(https_test(['HTTP_CF_VISITOR' => '{"scheme":"http"}', 'REMOTE_ADDR' => '173.245.48.10']) === false,
   'real CF peer reporting http is NOT secure');

echo "\n==================================================================\n";
echo " 9. ERROR OUTPUT DOES NOT LEAK\n";
echo "==================================================================\n";
// Capture STDOUT only. error_log() goes to STDERR, which is the server log -
// that is where the detail is supposed to go. What matters is that the
// response body (stdout) carries none of it.
$body_q = 'require ' . var_export($SEC, true) . '; relay_fail("Public message.", "SECRET_DB_PATH=/var/db/relay.sqlite", 500, false);';
$out = shell_exec(PHP_BINARY . ' -r ' . escapeshellarg($body_q) . ' 2>/dev/null');
ok(strpos($out, 'SECRET_DB_PATH') === false, 'response body does NOT contain the log detail', trim($out));
ok(strpos($out, 'Public message.') !== false, 'response body does contain the public message');
$log = shell_exec(PHP_BINARY . ' -r ' . escapeshellarg($body_q) . ' 2>&1 >/dev/null');
ok(strpos($log, 'SECRET_DB_PATH') !== false, 'the detail IS written to the server log (stderr)', trim($log));
$json_q = 'require ' . var_export($SEC, true) . '; relay_fail("Public.", "SECRET_TOKEN=abc", 400, true);';
$out = shell_exec(PHP_BINARY . ' -r ' . escapeshellarg($json_q) . ' 2>/dev/null');
ok(strpos($out, 'SECRET_TOKEN') === false, 'JSON mode response body does not leak detail');
ok(strpos($out, '"status":"error"') !== false, 'JSON mode emits structured error');

echo "\n==================================================================\n";
printf(" RESULT: %d passed, %d failed\n", $pass, $fail);
echo "==================================================================\n";
exit($fail === 0 ? 0 : 1);
