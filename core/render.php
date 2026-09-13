<?php
// ==========================================================
// 🧩 RELAY STATION: SHARED RENDER LAYER (V8.1)
// ==========================================================
// WHY THIS FILE EXISTS
//
// The Timeline, the Memory Vault and the public landing page had each grown
// their own copy of the same markup: the source label that says whose signal
// this is, the media matrix, and the ROGER THAT button. By v8.0.4 the source
// label alone existed in five places.
//
// That is not merely untidy - it is the mechanism by which bugs kept coming
// back:
//
//   - v8.0.2 fixed the label for a post I had merely relayed in the Vault.
//     The Timeline carried the same bug and had to be fixed separately, and
//     the landing page still disagrees with both.
//   - The AJAX Timeline path wrote the target URL into an onclick attribute
//     unescaped, while the two template paths escaped it. The same button was
//     safe in two places and injectable in the third.
//
// Anything that lives here once cannot drift again. The rule for what belongs
// in this file: markup derived from a transmission row that must look the same
// everywhere it is shown.
//
// This file renders only. It opens no sockets, writes nothing, and is not a
// web-accessible endpoint - it is require'd by pages that have already
// authenticated the operator.

// ---------------------------------------------------------------------------
// SOURCE LABELS
// ---------------------------------------------------------------------------
// Four things a transmission row can be, from this node's point of view:
//
//   local     I wrote it, it never left as a relay
//   my_relay  I am re-broadcasting somebody else's signal, so I am not its author
//   relayed   somebody else's signal that reached me through a relay
//   incoming  somebody else's signal, straight from them
//
// The distinction between local and my_relay is the one that matters: crediting
// myself with authorship of a relayed signal is a lie about who said it.
const RELAY_SOURCE_LABELS = [
    'local'    => 'LOCAL_AUTHOR:',
    'my_relay' => '<span class="text-warning">[ 🔁 RELAYED_BY_ME ]</span>',
    'relayed'  => '<span class="text-warning">[ 🔁 RELAYED ]</span> INCOMING FROM:',
    'incoming' => 'INCOMING FROM:',
];

/**
 * Classify a transmission row. Single source of truth for the four kinds.
 */
function relay_signal_kind(array $msg)
{
    $is_remote = ((int) ($msg['is_remote'] ?? 0)) === 1;
    $is_relay  = ((int) ($msg['is_relay'] ?? 0)) === 1;

    if (!$is_remote) {
        return $is_relay ? 'my_relay' : 'local';
    }

    return $is_relay ? 'relayed' : 'incoming';
}

/**
 * The label shown in front of the author name.
 *
 * Note this is a deliberate change from v8.0.4: the landing page used to say
 * "LOCAL_TRANSMISSION:" and "FROM:" where every other screen said
 * "LOCAL_AUTHOR:" and "INCOMING FROM:". Two vocabularies for one concept is
 * exactly the drift this file exists to stop, so there is now one.
 */
function relay_source_label(array $msg)
{
    $kind = relay_signal_kind($msg);

    return RELAY_SOURCE_LABELS[$kind] ?? RELAY_SOURCE_LABELS['incoming'];
}

/**
 * Author name, escaped for HTML text context.
 */
function relay_author_display(array $msg, $fallback = 'UNKNOWN')
{
    return htmlspecialchars($msg['author_alias'] ?? $fallback);
}

/**
 * Where a resonance for this signal should be sent.
 *
 * Only an incoming signal has a remote author to acknowledge; for everything
 * else there is nobody on the other end.
 */
function relay_target_planet_url(array $msg)
{
    if (((int) ($msg['is_remote'] ?? 0)) !== 1) {
        return '';
    }

    $parts = explode('@', (string) ($msg['author_alias'] ?? ''));
    if (count($parts) < 2) {
        return '';
    }

    $host = trim(end($parts));
    return $host === '' ? '' : 'https://' . $host;
}

// ---------------------------------------------------------------------------
// RESONANCE (ROGER THAT)
// ---------------------------------------------------------------------------

/**
 * May this node acknowledge this signal?
 *
 * Server-side rule, mirrored by core/transmitter.php which refuses the write:
 * you may only acknowledge an incoming signal. v8.0.2 hid the button on your
 * own transmissions but left the endpoint open, so the button was UI-only and
 * a direct POST still recorded a self-resonance.
 *
 * A relay of mine is excluded as well, and that is deliberate. The row is
 * local (is_remote = 0), so it has no remote author to notify: the counter
 * would only ever move on my own screen and would never reach the person who
 * actually wrote the signal. An acknowledgement nobody receives is noise
 * dressed up as a metric.
 */
function relay_can_resonate(array $msg)
{
    return ((int) ($msg['is_remote'] ?? 0)) === 1;
}

/**
 * How many times a signal has been acknowledged, without asking who did it.
 *
 * The public landing page shows the number and has no notion of "me" - its
 * visitor is not the operator - so there is nothing to ask about a reactor.
 * v8.1.0 briefly made that page call relay_resonance_stats() with a local-URL
 * variable it never had, which produced a warning on every page view; the
 * count was still right, which is exactly why it survived a green test run.
 * This function exists so the question the page actually asks is expressible.
 */
function relay_resonance_count(PDO $db, $post_id)
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM signal_resonance WHERE post_id = ?");
    $stmt->execute([$post_id]);

    return (int) $stmt->fetchColumn();
}

/**
 * How many times a signal has been acknowledged, and whether I am one of them.
 *
 * @return array{count: int, mine: bool}
 */
function relay_resonance_stats(PDO $db, $post_id, $local_url)
{
    $stmt_mine = $db->prepare("SELECT COUNT(*) FROM signal_resonance WHERE post_id = ? AND reactor_url = ?");
    $stmt_mine->execute([$post_id, $local_url]);

    return [
        'count' => relay_resonance_count($db, $post_id),
        'mine'  => ((int) $stmt_mine->fetchColumn()) > 0,
    ];
}

/**
 * The ROGER THAT button, or an empty string when acknowledging is meaningless.
 *
 * The target URL goes into a data attribute rather than an inline JS string
 * literal. It is derived from a remote-supplied alias, and the AJAX Timeline
 * path used to interpolate it raw into onclick="...'{$url}'..." - a quote in a
 * hostile node's alias was enough to break out of the attribute.
 */
function relay_roger_button(array $msg, array $stats, $target_url = '')
{
    if (!relay_can_resonate($msg)) {
        return '';
    }

    $acknowledged = !empty($stats['mine']);
    $text  = $acknowledged ? '[ ✓ ACKNOWLEDGED ]' : '[ 📻 ROGER THAT ]';
    $class = $acknowledged ? ' success' : '';

    return "<button type='button' data-roger-id='" . (int) $msg['id'] . "'"
        . " data-roger-target='" . htmlspecialchars((string) $target_url, ENT_QUOTES) . "'"
        . " onclick=\"toggleRogerThat(this)\""
        . " class='t-btn t-btn-sm{$class}' style='padding: 2px 6px; font-size: 10px;'>"
        . $text . "</button>";
}

// ---------------------------------------------------------------------------
// RELAY BUTTON
// ---------------------------------------------------------------------------

/**
 * The RELAY / UNRELAY button for an incoming signal, or '' for a local one.
 *
 * The origin DNA hash lives here too: it used to be recomputed inline in both
 * Timeline paths, and hashing a different string in one of them would silently
 * break de-duplication of relays.
 */
function relay_relay_button(PDO $db, array $msg)
{
    if (((int) ($msg['is_remote'] ?? 0)) !== 1) {
        return '';
    }

    $raw_origin = (string) ($msg['origin_id'] ?? '');
    $origin_id  = $raw_origin !== ''
        ? $raw_origin
        : hash('sha256', (string) ($msg['author_alias'] ?? '') . (string) ($msg['content'] ?? ''));

    $stmt = $db->prepare("SELECT id FROM transmissions WHERE is_remote = 0 AND is_relay = 1 AND origin_id = ?");
    $stmt->execute([$origin_id]);
    $relay_id = (int) $stmt->fetchColumn();

    $already = $relay_id > 0;
    $text    = $already ? '[ 🗑️ UNRELAY ]' : '[ 🔁 RELAY ]';
    $class   = $already ? 'danger' : 'warning';
    $onclick = $already
        ? "unrelayPost(this, {$relay_id}, " . (int) $msg['id'] . ", '" . htmlspecialchars($origin_id, ENT_QUOTES) . "')"
        : "relayPost(this, " . (int) $msg['id'] . ", '" . htmlspecialchars($origin_id, ENT_QUOTES) . "')";

    return "<button type='button' id='relay-btn-" . (int) $msg['id'] . "'"
        . " onclick=\"{$onclick}\""
        . " class='t-btn t-btn-sm {$class}' style='padding: 2px 6px; font-size: 10px;'>{$text}</button>";
}

// ---------------------------------------------------------------------------
// MEDIA MATRIX
// ---------------------------------------------------------------------------

/**
 * Render the media grid for a transmission.
 *
 * media_url is either a single URL or a JSON array of up to four. The row's
 * value is database state, never trusted markup, so every URL is escaped on
 * the way out.
 *
 * @param string $alt alt text for image cells; call sites differ slightly
 */
function relay_media_matrix($media_url, $alt = 'Secure Media')
{
    if (empty($media_url)) {
        return '';
    }

    $raw = (string) $media_url;

    // A JSON array means several attachments; anything else is a bare URL.
    // Guarded with is_array() rather than ?? [] because json_decode returns
    // whatever the payload happened to be: a scalar or an object would reach
    // array_slice() below and raise a TypeError, so a single malformed
    // media_url could take the whole Timeline down.
    if (strpos($raw, '[') === 0) {
        $decoded = json_decode($raw, true);
        $items = is_array($decoded) ? $decoded : [];
    } else {
        $items = [$raw];
    }

    $items = array_slice($items, 0, 4);
    if (count($items) === 0) {
        return '';
    }

    $html = '<div class="media-matrix media-matrix-' . count($items) . '">';

    foreach ($items as $url) {
        $safe = htmlspecialchars((string) $url);
        $ext  = strtolower(pathinfo((string) $url, PATHINFO_EXTENSION));

        if (in_array($ext, ['webm', 'ogg', 'mp3', 'wav', 'm4a'], true)) {
            $html .= '<div class="matrix-item audio-cell p-2">'
                . '<button type="button" class="t-btn warning w-100 audio-play-btn"'
                . " data-src=\"{$safe}\" style=\"font-size: 11px;\">[ ▶️ PLAY AUDIO_LOG ]</button>"
                . '</div>';
        } elseif ($ext === 'mp4') {
            $html .= '<div class="matrix-item">'
                . '<video class="matrix-video" controls preload="metadata">'
                . "<source src=\"{$safe}\" type=\"video/mp4\"></video></div>";
        } else {
            $html .= '<div class="matrix-item">'
                . "<img src=\"{$safe}\" class=\"matrix-img\" alt=\"" . htmlspecialchars($alt, ENT_QUOTES) . '"></div>';
        }
    }

    return $html . '</div>';
}
