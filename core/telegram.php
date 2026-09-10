<?php
// ==========================================
// 👁️ RELAY STATION: THE ORACLE (TELEGRAM ENGINE)
// ==========================================
// This helper script sends real-time radar alerts 
// to the Commander's smartphone via Telegram Webhooks.

/**
 * Escape text for Telegram MarkdownV2.
 *
 * MarkdownV2 treats a fixed set of characters as markup. Anything not escaped
 * can break the message or let an interpolated value render as a link. Values
 * that reach these alerts include remote node URLs, so escaping matters.
 */
function relay_telegram_escape_markdownv2($text)
{
    $special = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
    $out = '';
    $len = strlen($text);
    for ($i = 0; $i < $len; $i++) {
        $c = $text[$i];
        $out .= in_array($c, $special, true) ? '\\' . $c : $c;
    }
    return $out;
}

function sendTelegramAlert($message) {
    global $db;

    // If the core memory (database) connection is missing, abort.
    if (!$db) {
        return false;
    }

    try {
        // 1. Fetch Oracle configurations from Core Memory
        $stmt = $db->query("SELECT config_key, config_value FROM system_config WHERE config_key IN ('telegram_enabled', 'telegram_bot_token', 'telegram_chat_id')");
        $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $is_enabled = isset($configs['telegram_enabled']) ? $configs['telegram_enabled'] : '0';
        $bot_token = isset($configs['telegram_bot_token']) ? trim($configs['telegram_bot_token']) : '';
        $chat_id = isset($configs['telegram_chat_id']) ? trim($configs['telegram_chat_id']) : '';

        // 2. If the Oracle is offline or missing coordinates, silently abort the mission
        if ($is_enabled !== '1' || empty($bot_token) || empty($chat_id)) {
            return false;
        }

        // 3. Construct the payload for Telegram API
        $telegram_url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
        
        // Format the message with a tactical prefix
        $formatted_message = "🛰️ *RELAY STATION ALERT*\n" . "───────────────\n" . $message;

        $post_fields = [
            'chat_id' => $chat_id,
            // The alert text embeds values that originate from other nodes -
            // a remote station's URL arrives via api_handshake and is stored,
            // then interpolated into this string. With parse_mode=Markdown
            // those characters are interpreted as formatting, so a node could
            // inject links or fake structure into the Commander's alerts.
            // MarkdownV2 requires escaping everything outside a strict set.
            'text' => relay_telegram_escape_markdownv2($formatted_message),
            'parse_mode' => 'MarkdownV2'
        ];

        // 4. Build the request through the shared gate: certificate verified
        //    against the host, HTTPS only, and redirects not followed (a
        //    redirect would be a way to make this endpoint talk to a host it
        //    was never validated against).
        $ch = relay_node_curl($telegram_url);
        if (!$ch) {
            error_log('[ ORACLE ERROR ] refused to contact the Telegram API endpoint');
            return false;
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));

        // Tactical timeout: 2 seconds maximum. The Oracle must never slow the
        // station down or hold a request open because Telegram is lagging.
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);

        // 5. Fire the signal silently
        $response = curl_exec($ch);
        curl_close($ch);

        return true;

    } catch (Exception $e) {
        // Suppress errors silently. The Oracle failing should never crash the station.
        error_log("[ ORACLE ERROR ] Failed to send Telegram alert: " . $e->getMessage());
        return false;
    }
}
?>