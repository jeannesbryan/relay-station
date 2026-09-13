-- ==========================================================
-- RELAY STATION: CORE MEMORY SCHEMA (V8.0.1 - Aegis)
-- ==========================================================
-- This file is the complete description of the core memory. If a table is
-- added here it must match what the application creates at runtime, and vice
-- versa: a table the app provisions on its own but that is missing here is a
-- silent gap for anyone reading (or restoring) the schema.

-- 1. Gudang Transmisi (Log Sinyal Utama)
CREATE TABLE IF NOT EXISTS transmissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    content TEXT NOT NULL,
    -- Must list every value the application writes. v7.3 restricted this to
    -- ('public','direct') while core/transmitter.php inserted five more
    -- (sonar_pulse, ack_receipt, scorched_earth, global_purge, resonance), so
    -- on any database built from this file those five signal types failed with
    -- an Integrity constraint violation. Kept as a CHECK because defence in
    -- depth is worth having, but it has to agree with the app.
    visibility TEXT CHECK(visibility IN ('public', 'direct', 'sonar_pulse', 'ack_receipt', 'scorched_earth', 'global_purge', 'resonance')) DEFAULT 'public',
    target_planet TEXT,          -- Koordinat target untuk Laser Link (Direct Message)
    is_remote INTEGER DEFAULT 0, -- 0: Sinyal Lokal (Anda), 1: Sinyal Masuk dari Luar
    is_relay INTEGER DEFAULT 0,  -- 🔁 [NEW V7.3] 1 jika pesan ini adalah hasil estafet
    origin_id TEXT DEFAULT NULL, -- 🛡️ [NEW V7.3] Pelacak sumber asli (Mencegah Echo Chamber)
    author_alias TEXT,           -- Identitas pengirim
    expiry_date DATETIME,        -- Waktu ledak otomatis (Ghost Protocol)
    media_url TEXT DEFAULT NULL, -- Dukungan Gambar/Media Fediverse
    sender_ip TEXT DEFAULT NULL, -- Keamanan Rate-Limiting Anti-Spoofing
    status TEXT DEFAULT 'sent',  -- ACK Protocol: 'sent' atau 'read'
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 2. Peta Bintang (Stasiun sekutu yang Anda pantau)
CREATE TABLE IF NOT EXISTS following (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    planet_url TEXT UNIQUE NOT NULL,
    alias TEXT,                  -- Akan terisi nama domain jika dikosongkan
    handshake_token TEXT,        -- [NEW V6.2] Token Rahasia Anti-Spoofing & Pemicu Re-Sync
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 3. Daftar Pengikut (Stasiun yang memantau Anda)
CREATE TABLE IF NOT EXISTS followers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    planet_url TEXT UNIQUE NOT NULL,
    handshake_token TEXT,        -- [NEW V6.2] Token Rahasia dari stasiun pengikut
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 4. Radar Peringatan (Notifikasi)
CREATE TABLE IF NOT EXISTS alerts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT,                   -- Kategori: 'new_follower', 'new_dm', 'sonar_pulse', 'resync'
    from_planet TEXT,
    payload TEXT DEFAULT NULL,   -- Data tambahan seperti Short Code Sonar atau Handshake Validasi
    is_read INTEGER DEFAULT 0,   -- 0: Belum dibaca, 1: Sudah dibaca
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 5. Konfigurasi Sistem (Ruang Mesin Rahasia)
CREATE TABLE IF NOT EXISTS system_config (
    config_key TEXT PRIMARY KEY,
    config_value TEXT
);

-- Slot untuk Kunci Publik (E2E)
INSERT OR IGNORE INTO system_config (config_key, config_value) VALUES ('public_key', '');

-- Tuas Bunker Mode / Private Node (Default: 0 / OFF)
INSERT OR IGNORE INTO system_config (config_key, config_value) VALUES ('bunker_mode', '0');

-- [ NEW V6.2 ] Menyimpan Koordinat Domain Terkini untuk Deteksi Pindah Rumah (Nomadic)
INSERT OR IGNORE INTO system_config (config_key, config_value) VALUES ('local_planet_url', '');

-- 👁️ [ NEW V7.0 ] Slot untuk Konfigurasi The Oracle (Telegram Webhooks)
INSERT OR IGNORE INTO system_config (config_key, config_value) VALUES ('telegram_enabled', '0');
INSERT OR IGNORE INTO system_config (config_key, config_value) VALUES ('telegram_bot_token', '');
INSERT OR IGNORE INTO system_config (config_key, config_value) VALUES ('telegram_chat_id', '');

-- 6. Keamanan Gerbang (Anti Brute-Force & Log)
CREATE TABLE IF NOT EXISTS login_attempts (
    ip_address TEXT PRIMARY KEY,
    attempts INTEGER DEFAULT 0,
    lockout_until DATETIME
);

-- ==========================================================
-- 🗄️ 7. [ V7.2 ] THE MEMORY VAULT (BOOKMARKS)
-- Menyimpan ID pesan yang ditandai oleh Kapten. Menggunakan INNER JOIN saat dipanggil.
-- ==========================================================
CREATE TABLE IF NOT EXISTS bookmarks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    transmission_id INTEGER NOT NULL,
    bookmarked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(transmission_id)
);

-- ==========================================================
-- ⏱️ 9. [ V8.0.1 ] THE RATE LIMITER (fixed-window counters)
-- ==========================================================
-- Declared here for completeness: core/security.php relay_rate_limit() also
-- issues this exact CREATE TABLE IF NOT EXISTS on first use, so installations
-- that predate this file keep working unchanged (the definitions are identical,
-- so whichever runs first wins). One row per bucket per window; the bucket
-- string keeps each endpoint's counter independent.
CREATE TABLE IF NOT EXISTS relay_rate_limits (
    bucket TEXT NOT NULL,
    window_start INTEGER NOT NULL,
    hits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (bucket, window_start)
);

-- ==========================================================
-- ⚡ 8. [ V7.2 ] SIGNAL RESONANCE (ROGER THAT)
-- Mencatat interaksi. Kunci UNIQUE(post_id, reactor_url) adalah tameng mutlak anti-spam ping.
-- ==========================================================
CREATE TABLE IF NOT EXISTS signal_resonance (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    reactor_url TEXT NOT NULL,
    reactor_alias TEXT NOT NULL,
    resonance_type TEXT DEFAULT 'roger',
    reacted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(post_id, reactor_url)
);