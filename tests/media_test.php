<?php
// Regression test: media and upload validation.
//
// v7.3 base64-decoded whatever it was handed and wrote it into media/ with no
// size cap and no content inspection; multipart uploads were checked only
// against a filename extension allowlist. These tests pin the replacement:
// bounded size, base64 shape checked before decoding, and the extension on
// disk derived from the file's own magic bytes rather than from the type the
// caller declared.
//
// Run: php tests/media_test.php

require_once __DIR__ . '/../core/security.php';

$pass = 0; $fail = 0;
function ok($cond, $label, $extra = '') {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  PASS  $label\n"; }
    else { $fail++; echo "  FAIL  $label" . ($extra ? "   [$extra]" : '') . "\n"; }
}

// Minimal but structurally real headers.
$webp = "RIFF" . pack('V', 4) . "WEBP" . str_repeat('A', 40);
$ogg  = "OggS" . str_repeat('B', 40);
$webm = "\x1A\x45\xDF\xA3" . str_repeat('C', 40);
$m4a  = pack('N', 32) . "ftypM4A " . str_repeat('E', 40);
$php  = '<?php echo "pwned"; ?>';
$gif  = "GIF89a" . str_repeat('D', 40);

function as_data_url($mime, $bytes) {
    return "data:$mime;base64," . base64_encode($bytes);
}

echo "==================================================================\n";
echo " 1. GENUINE MEDIA IS ACCEPTED, TYPED FROM CONTENT\n";
echo "==================================================================\n";
foreach (['webp' => $webp, 'ogg' => $ogg, 'webm' => $webm, 'm4a' => $m4a] as $name => $bytes) {
    $r = relay_decode_media_data_url(as_data_url('application/octet-stream', $bytes), RELAY_MAX_MEDIA_BYTES);
    ok($r !== null && $r['ext'] === $name, "accepts a real $name and types it as $name",
       $r ? 'got ' . $r['ext'] : 'null');
}
// The declared type is attacker-controlled and must not decide the extension.
$r = relay_decode_media_data_url(as_data_url('image/png', $ogg), RELAY_MAX_MEDIA_BYTES);
ok($r !== null && $r['ext'] === 'ogg', 'extension comes from content, not the declared data: type',
   $r ? $r['ext'] : 'null');
// A truthful content type is neither required nor trusted; the bytes decide.
$r = relay_decode_media_data_url(as_data_url('application/x-php', $webp), RELAY_MAX_MEDIA_BYTES);
ok($r !== null && $r['ext'] === 'webp',
   'a lying MIME type is harmless because the bytes are genuine webp',
   $r ? $r['ext'] : 'null');

echo "\n==================================================================\n";
echo " 2. HOSTILE AND MALFORMED PAYLOADS ARE REJECTED\n";
echo "==================================================================\n";
$reject = [
    'a PHP payload'                => as_data_url('image/webp', $php),
    'a GIF mislabelled as webp'    => as_data_url('image/webp', $gif),
    'a data URL with no comma'     => 'data:image/webp;base64',
    'an empty body'                => 'data:image/webp;base64,',
    'a body that is not base64'    => 'data:image/webp;base64,!!!!not-base64!!!!',
    'a body too short to identify' => as_data_url('image/webp', 'RI'),
    'a plain string'               => 'not a data url at all',
    'an empty string'              => '',
];
foreach ($reject as $label => $value) {
    $r = relay_decode_media_data_url($value, RELAY_MAX_MEDIA_BYTES);
    ok($r === null, "rejects $label", $r ? 'got ' . json_encode($r['ext']) : '');
}
foreach ([null, 123, [], new stdClass()] as $i => $value) {
    ok(relay_decode_media_data_url($value, RELAY_MAX_MEDIA_BYTES) === null,
       'rejects a non-string input (#' . $i . ')');
}

echo "\n==================================================================\n";
echo " 3. SIZE BOUNDING\n";
echo "==================================================================\n";
$big = $webp . str_repeat('Z', RELAY_MAX_MEDIA_BYTES);
ok(relay_decode_media_data_url(as_data_url('image/webp', $big), RELAY_MAX_MEDIA_BYTES) === null,
   'rejects a payload larger than the cap');
// Just under the cap must still pass, so the check is a bound and not a ban.
$under = $webp . str_repeat('Z', (RELAY_MAX_MEDIA_BYTES - strlen($webp) - 1));
ok(relay_decode_media_data_url(as_data_url('image/webp', $under), RELAY_MAX_MEDIA_BYTES) !== null,
   'accepts a payload just under the cap');
ok(RELAY_MAX_MEDIA_BYTES === 5 * 1024 * 1024, 'media cap is 5 MB');
ok(RELAY_MAX_AUDIO_BYTES === 10 * 1024 * 1024, 'audio cap is 10 MB');

echo "\n==================================================================\n";
echo " 4. SNIFFER PRIMITIVES\n";
echo "==================================================================\n";
ok(relay_sniff_media_type($webp) === 'webp',       'sniff webp');
ok(relay_sniff_media_type($ogg) === 'ogg',         'sniff ogg');
ok(relay_sniff_media_type($webm) === 'webm',       'sniff webm');
ok(relay_sniff_media_type($m4a) === 'm4a',         'sniff m4a');
ok(relay_sniff_media_type($php) === null,          'sniff rejects PHP source');
ok(relay_sniff_media_type($gif) === null,          'sniff rejects GIF (not in the accepted set)');
ok(relay_sniff_media_type('') === null,            'sniff rejects empty');
ok(relay_sniff_media_type('RIFF') === null,        'sniff rejects a truncated header');
// "RIFF....WAVE" is a WAV, not a WEBP - the 4-byte form marker must be checked.
ok(relay_sniff_media_type("RIFF" . pack('V', 4) . "WAVE" . str_repeat('X', 20)) === null,
   'sniff distinguishes WAV from WEBP');

echo "\n==================================================================\n";
echo " 5. MULTIPART UPLOAD VALIDATION\n";
echo "==================================================================\n";
// relay_validate_upload requires is_uploaded_file(), which is only true for a
// real upload. Every rejection path must therefore trigger before that check.
ok(relay_validate_upload(['tmp_name' => '/etc/passwd', 'error' => UPLOAD_ERR_OK, 'size' => 100],
                         RELAY_MAX_MEDIA_BYTES) === null,
   'rejects a path that is not a genuine uploaded file');
ok(relay_validate_upload(['tmp_name' => '/tmp/x', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0],
                         RELAY_MAX_MEDIA_BYTES) === null,
   'rejects an upload that reported an error');
ok(relay_validate_upload(['tmp_name' => '/tmp/x', 'error' => UPLOAD_ERR_OK, 'size' => 0],
                         RELAY_MAX_MEDIA_BYTES) === null,
   'rejects a zero-byte upload');
ok(relay_validate_upload(['tmp_name' => '/tmp/x', 'error' => UPLOAD_ERR_OK,
                          'size' => RELAY_MAX_MEDIA_BYTES + 1],
                         RELAY_MAX_MEDIA_BYTES) === null,
   'rejects an upload over the size cap');
ok(relay_validate_upload('not an array', RELAY_MAX_MEDIA_BYTES) === null, 'rejects a non-array');
ok(relay_validate_upload([], RELAY_MAX_MEDIA_BYTES) === null, 'rejects an empty array');

echo "\n==================================================================\n";
printf(" RESULT: %d passed, %d failed\n", $pass, $fail);
echo "==================================================================\n";
exit($fail === 0 ? 0 : 1);
