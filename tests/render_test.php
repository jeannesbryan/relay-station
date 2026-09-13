<?php
// Shared render layer test: the labels, the media matrix and the buttons.
//
// WHY THIS EXISTS: v8.1 moved the source label, the media matrix and the
// ROGER THAT button out of five separate places and into core/render.php, so
// that a fix lands once instead of five times. Moving markup is exactly the
// kind of change that looks fine and is not, so the behaviour it replaced is
// pinned here - including the two things that were actually wrong before:
//
//   1. The AJAX Timeline path wrote a remote-supplied URL into an onclick
//      string literal unescaped, while the two template paths escaped it. The
//      same button was safe in two places and injectable in the third. There
//      is now a parser below that reads the emitted tag back and fails if a
//      value managed to become a new attribute.
//   2. The landing page called a local post LOCAL_TRANSMISSION and an incoming
//      one "FROM", where every other screen said LOCAL_AUTHOR and INCOMING
//      FROM. Two vocabularies for one concept is the drift the shared layer
//      exists to stop, so there is now one.
//
// Run: php tests/render_test.php

require_once dirname(__DIR__) . '/core/render.php';

$pass = 0; $fail = 0;

function check($label, $cond, $extra = '')
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  PASS  $label\n"; }
    else { $fail++; echo "  FAIL  $label" . ($extra !== '' ? "   [$extra]" : '') . "\n"; }
}

function section($name)
{
    echo "\n== $name ==\n";
}

/**
 * Parse an emitted tag into name => value.
 *
 * This is deliberately a real quote-aware scanner rather than a regex: the
 * point is to prove that no value could break out of its delimiters and create
 * an attribute nobody wrote. A regex that looks for `onmouseover=` would only
 * prove that one guess was wrong.
 */
function tag_attributes($tag)
{
    $len = strlen($tag);
    $i = 1;

    // Skip the tag name.
    while ($i < $len && !ctype_space($tag[$i]) && $tag[$i] !== '>') { $i++; }

    $attrs = [];
    while ($i < $len) {
        while ($i < $len && ctype_space($tag[$i])) { $i++; }
        if ($i >= $len || $tag[$i] === '>' || $tag[$i] === '/') { break; }

        $name = '';
        while ($i < $len && $tag[$i] !== '=' && !ctype_space($tag[$i]) && $tag[$i] !== '>') {
            $name .= $tag[$i++];
        }
        while ($i < $len && ctype_space($tag[$i])) { $i++; }

        $value = '';
        if ($i < $len && $tag[$i] === '=') {
            $i++;
            while ($i < $len && ctype_space($tag[$i])) { $i++; }
            if ($i < $len && ($tag[$i] === "'" || $tag[$i] === '"')) {
                $quote = $tag[$i++];
                while ($i < $len && $tag[$i] !== $quote) { $value .= $tag[$i++]; }
                $i++; // closing quote
            } else {
                while ($i < $len && !ctype_space($tag[$i]) && $tag[$i] !== '>') { $value .= $tag[$i++]; }
            }
        }

        if ($name !== '') { $attrs[strtolower($name)] = $value; }
    }

    return $attrs;
}

/** Pull the first <button ...> tag out of a fragment. */
function first_button($html)
{
    if (!preg_match('/<button\b[^>]*>/i', $html, $m)) { return null; }
    return $m[0];
}

function in_memory_db()
{
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE transmissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        content TEXT NOT NULL,
        is_remote INTEGER DEFAULT 0,
        is_relay INTEGER DEFAULT 0,
        origin_id TEXT DEFAULT NULL,
        author_alias TEXT
    )");
    $db->exec("CREATE TABLE signal_resonance (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id INTEGER NOT NULL,
        reactor_url TEXT NOT NULL,
        reactor_alias TEXT NOT NULL,
        resonance_type TEXT DEFAULT 'roger',
        UNIQUE(post_id, reactor_url)
    )");
    return $db;
}

$db = in_memory_db();
$me = 'https://mine.example';

// ---------------------------------------------------------------------------
section('signal classification');

$own       = ['id' => 1,   'is_remote' => 0, 'is_relay' => 0, 'author_alias' => 'ME'];
$my_relay  = ['id' => 2,   'is_remote' => 0, 'is_relay' => 1, 'author_alias' => 'THEM@peer.example'];
$relayed   = ['id' => 3,   'is_remote' => 1, 'is_relay' => 1, 'author_alias' => 'THEM@peer.example'];
$incoming  = ['id' => 4,   'is_remote' => 1, 'is_relay' => 0, 'author_alias' => 'THEM@peer.example'];
$no_flags  = ['id' => 5];

check('own transmission is local',            relay_signal_kind($own) === 'local');
check('my own relay is my_relay',             relay_signal_kind($my_relay) === 'my_relay');
check('a relayed incoming signal is relayed', relay_signal_kind($relayed) === 'relayed');
check('a direct incoming signal is incoming', relay_signal_kind($incoming) === 'incoming');
check('a row with no flags is still local',   relay_signal_kind($no_flags) === 'local');

// ---------------------------------------------------------------------------
section('source labels');

check('local is credited to the author', strpos(relay_source_label($own), 'LOCAL_AUTHOR:') !== false);
check('my relay names me as the relayer, not the author',
    strpos(relay_source_label($my_relay), 'RELAYED_BY_ME') !== false
    && strpos(relay_source_label($my_relay), 'LOCAL_AUTHOR') === false);
check('a relayed signal says RELAYED and where it came from',
    strpos(relay_source_label($relayed), '🔁 RELAYED ]') !== false
    && strpos(relay_source_label($relayed), 'INCOMING FROM:') !== false);
check('an incoming signal says where it came from',
    relay_source_label($incoming) === 'INCOMING FROM:');

$all_labels = array_map('relay_source_label', [$own, $my_relay, $relayed, $incoming]);
check('the four labels are distinct', count(array_unique($all_labels)) === 4);

// ---------------------------------------------------------------------------
section('who may acknowledge a signal');

check('my own transmission cannot be acknowledged', !relay_can_resonate($own));
check('my own relay cannot be acknowledged',        !relay_can_resonate($my_relay));
check('an incoming signal can be acknowledged',      relay_can_resonate($incoming));
check('a relayed incoming signal can be acknowledged', relay_can_resonate($relayed));
check('the rule is is_remote, nothing else',
    relay_can_resonate(['is_remote' => '1']) === true && relay_can_resonate(['is_remote' => '0']) === false);

// ---------------------------------------------------------------------------
section('resonance counts');

$db->exec("INSERT INTO signal_resonance (post_id, reactor_url, reactor_alias) VALUES (4, 'https://a.example', 'A')");
$db->exec("INSERT INTO signal_resonance (post_id, reactor_url, reactor_alias) VALUES (4, '$me', 'ME')");

$stats = relay_resonance_stats($db, 4, $me);
check('counts every reactor', $stats['count'] === 2, 'got ' . $stats['count']);
check('knows when I am one of them', $stats['mine'] === true);

$other = relay_resonance_stats($db, 999, $me);
check('a signal nobody acknowledged counts zero', $other['count'] === 0 && $other['mine'] === false);

// The public landing page asks only for the number - its visitor is not the
// operator, so there is no "mine" to ask about - and it must therefore not need
// a local URL at all. v8.1.0 briefly had it pass a variable that page never
// defined: the number came out right, so every test passed, and a warning was
// written to the production log on every single view. The fix is that the
// question the page actually asks is now expressible.
check('a count can be taken without knowing who is asking',
    relay_resonance_count($db, 4) === 2 && relay_resonance_count($db, 999) === 0,
    relay_resonance_count($db, 4) . '/' . relay_resonance_count($db, 999));
check('the standalone count agrees with the stats', relay_resonance_count($db, 4) === $stats['count']);

// ---------------------------------------------------------------------------
section('acknowledge target');

check('an incoming signal targets its author host',
    relay_target_planet_url($incoming) === 'https://peer.example',
    relay_target_planet_url($incoming));
check('my own transmission has no target', relay_target_planet_url($own) === '');
check('an alias without a host has no target',
    relay_target_planet_url(['is_remote' => 1, 'author_alias' => 'NOHOST']) === '');

// ---------------------------------------------------------------------------
section('media matrix');

check('no media renders nothing', relay_media_matrix(null) === '' && relay_media_matrix('') === '');

$one = relay_media_matrix('https://mine.example/media/a.png');
check('one image renders a single-cell matrix',
    strpos($one, 'media-matrix-1') !== false && substr_count($one, '<img') === 1);

$four = relay_media_matrix(json_encode([
    'https://mine.example/media/1.png', 'https://mine.example/media/2.png',
    'https://mine.example/media/3.png', 'https://mine.example/media/4.png',
]));
check('four items render a four-cell matrix',
    strpos($four, 'media-matrix-4') !== false && substr_count($four, 'matrix-item') === 4);

$five = relay_media_matrix(json_encode([
    'https://mine.example/media/1.png', 'https://mine.example/media/2.png',
    'https://mine.example/media/3.png', 'https://mine.example/media/4.png',
    'https://mine.example/media/5.png',
]));
check('a fifth attachment is still capped at four',
    strpos($five, 'media-matrix-4') !== false && substr_count($five, 'matrix-item') === 4);

check('audio gets the player button',
    strpos(relay_media_matrix('https://mine.example/media/a.webm'), 'audio-play-btn') !== false);
check('mp4 gets a video element',
    strpos(relay_media_matrix('https://mine.example/media/a.mp4'), '<video') !== false);

$broken = relay_media_matrix('[this is not json');
check('malformed json renders nothing instead of crashing', $broken === '', $broken);

// A scalar or object where an array was expected used to reach array_slice().
// These two used to be able to reach array_slice() as a string or an object
// and raise a TypeError, taking the whole Timeline down with one bad row. The
// requirement is only that they do not crash and do not fan out into cells.
$scalar = relay_media_matrix('"just-a-string"');
check('a json scalar does not reach array_slice',
    substr_count($scalar, 'matrix-item') <= 1, $scalar);
$object = relay_media_matrix('{"a":1}');
check('a json object does not reach array_slice',
    substr_count($object, 'matrix-item') <= 1, $object);

$quote = relay_media_matrix('https://mine.example/media/a"onload="alert(1).png');
check('a quoted url cannot escape the src attribute',
    strpos($quote, '&quot;') !== false && strpos($quote, 'onload="alert(1)"') === false, $quote);

check('alt text is escaped',
    strpos(relay_media_matrix('https://mine.example/media/a.png', 'a"b'), 'alt="a&quot;b"') !== false);

// ---------------------------------------------------------------------------
section('acknowledge button');

check('no button on my own transmission', relay_roger_button($own, ['count' => 3, 'mine' => false]) === '');
check('no button on my own relay', relay_roger_button($my_relay, ['count' => 0, 'mine' => false]) === '');

$btn = relay_roger_button($incoming, ['count' => 0, 'mine' => false], 'https://peer.example');
check('a button on an incoming signal', strpos($btn, 'ROGER THAT') !== false);
check('the unacknowledged state is not marked success', strpos($btn, 'success') === false);

$acked = relay_roger_button($incoming, ['count' => 1, 'mine' => true], 'https://peer.example');
check('the acknowledged state renders as ACKNOWLEDGED',
    strpos($acked, 'ACKNOWLEDGED') !== false && strpos($acked, 'success') !== false);

$attrs = tag_attributes(first_button($btn));
check('the button carries exactly the attributes it should',
    array_keys($attrs) === ['type', 'data-roger-id', 'data-roger-target', 'onclick', 'class', 'style'],
    implode(',', array_keys($attrs)));
check('the target is a data attribute, not an inline js argument',
    $attrs['data-roger-target'] === 'https://peer.example' && $attrs['onclick'] === 'toggleRogerThat(this)',
    $attrs['onclick']);

// The hostile case this exists for. The double quote is the character that
// actually mattered: the AJAX Timeline path wrote the target into a
// double-quoted onclick attribute UNESCAPED, so an alias containing one closed
// the attribute and everything after it became markup. The apostrophe variant
// is checked too, since it breaks the JS string literal instead.
foreach ([
    'a double quote'    => "EVIL@evil.example\" onmouseover=\"alert(1)",
    'an apostrophe'     => "EVIL@evil.example' onmouseover='alert(1)",
    'a greater-than'    => 'EVIL@evil.example><script>alert(1)</script>',
] as $what => $alias) {
    $evil = ['id' => 7, 'is_remote' => 1, 'author_alias' => $alias];
    $evil_btn = relay_roger_button($evil, ['count' => 0, 'mine' => false], relay_target_planet_url($evil));
    $evil_attrs = tag_attributes(first_button($evil_btn));

    check("$what in an alias cannot add an attribute to the button",
        !isset($evil_attrs['onmouseover']) && !isset($evil_attrs['onscript']),
        implode(',', array_keys($evil_attrs)));
    check("$what in an alias cannot change the attribute count",
        count($evil_attrs) === 6, implode(',', array_keys($evil_attrs)));
    check("$what in an alias stays inside its own attribute",
        strpos($evil_btn, '&quot;') !== false || strpos($evil_btn, '&#039;') !== false
        || strpos($evil_btn, '&gt;') !== false,
        $evil_attrs['data-roger-target'] ?? '');
}

// ---------------------------------------------------------------------------
section('relay button');

$db->exec("INSERT INTO transmissions (id, content, is_remote, is_relay, origin_id, author_alias)
           VALUES (10, 'from a peer', 1, 0, 'dna-one', 'THEM@peer.example')");

check('a local post has no relay button', relay_relay_button($db, $own) === '');

$relay_btn = relay_relay_button($db, ['id' => 10, 'is_remote' => 1, 'origin_id' => 'dna-one']);
check('an incoming signal offers RELAY', strpos($relay_btn, 'RELAY ]') !== false, $relay_btn);

$db->exec("INSERT INTO transmissions (content, is_remote, is_relay, origin_id, author_alias)
           VALUES ('my relay of it', 0, 1, 'dna-one', 'THEM@peer.example')");
$unrelay = relay_relay_button($db, ['id' => 10, 'is_remote' => 1, 'origin_id' => 'dna-one']);
check('an already-relayed signal offers UNRELAY', strpos($unrelay, 'UNRELAY') !== false, $unrelay);
// The origin DNA is a hash of a remote-supplied alias and content, so it is
// attacker-influenced in the fallback case. It goes into an inline onclick, and
// the guard is that the attribute is double-quoted while the value is escaped
// with ENT_QUOTES - so a double quote becomes &quot; rather than a delimiter.
$dna_btn = relay_relay_button($db, [
    'id' => 11, 'is_remote' => 1, 'origin_id' => 'dna" onmouseover="alert(1)',
]);
$dna_attrs = tag_attributes(first_button($dna_btn));
check('a quoted origin dna cannot add an attribute to the relay button',
    !isset($dna_attrs['onmouseover']) && count($dna_attrs) === 5,
    implode(',', array_keys($dna_attrs)));
check('a quoted origin dna stays inside the onclick attribute',
    strpos($dna_btn, '&quot;') !== false, $dna_btn);

// ---------------------------------------------------------------------------
echo "\n" . str_repeat('=', 66) . "\n";
echo " RENDER: $pass passed, $fail failed\n";
echo str_repeat('=', 66) . "\n";

exit($fail === 0 ? 0 : 1);
