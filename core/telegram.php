<?php
// ==========================================
// 👁️ RELAY STATION: THE ORACLE (TELEGRAM ENGINE)
// ==========================================
// This helper script sends real-time radar alerts 
// to the Commander's smartphone via Telegram Webhooks.

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
            'text' => $formatted_message,
            'parse_mode' => 'Markdown'
        ];

        // 4. Initialize cURL (The Courier)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $telegram_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // 🛡️ Tactical Timeout: 2 seconds maximum. 
        // We don't want to slow down the main server if Telegram API is lagging.
        curl_setopt($ch, CURLOPT_TIMEOUT, 2); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
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