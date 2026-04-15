# 🌌 Relay Station: The Constellation Network

> A sovereign, decentralized, and zero-dependency P2P communication node built for the open web. 

**Relay Station** is not a social media platform; it is a communication node. By installing this software on your server, you establish your own sovereign "Star" in a dark universe. When multiple Relay Nodes connect, they form a **Constellation**—a decentralized network of independent nodes sharing public timelines and encrypted direct messages without any central authority, tracker, or corporate oversight.

Built purely with **PHP** and **SQLite**, it is designed to run on absolutely any basic shared hosting environment. No Docker, no Node.js, no terminal commands required.

---

## ⚙️ System Requirements

Relay Station is designed to be extremely lightweight and can be hosted on a $1/month shared hosting plan or a Raspberry Pi.
* **PHP:** Version 7.4, 8.0, 8.1, or 8.2+
* **Database:** SQLite3 (No MySQL/MariaDB setup required)
* **Required PHP Extensions:** `cURL` (for transmission), `pdo_sqlite` (for core memory), `ZipArchive` (for OTA updates, installation, & backups), and `gd` or `fileinfo` (for media processing).
* **Security:** An active SSL/HTTPS certificate is strictly required for the domain/subdomain.

---

## 🚀 Core Capabilities & Features (v7.3 - The Relay Protocol)

* **100% Data Sovereignty:** You own the server, the database, and the media. There is no central database.
* **🔁 The Relay Protocol (NEW):** Seamlessly re-broadcast and curate transmissions from allied nodes across your Constellation. Propagate valuable intel through a decentralized Trust Chain without algorithms. You maintain full control to manually `[ UNRELAY ]` and clean your local database at any time.
* **🛡️ The Anti-Loop Shield (NEW):** A built-in defense mechanism for the Relay Protocol. Every transmission is embedded with a unique `origin_id` DNA. If a relayed signal loops back to a node that already possesses it, the shield silently drops it, completely preventing spam reverberations and Echo Chambers.
* **💥 Cascading Chain-Purge (NEW):** An upgrade to the Global Purge protocol. When a commander deletes an original transmission or fires a Global Purge, the destruction signal dynamically cascades through all relayed nodes, automatically wiping mirrored data across the entire network.
* **⚡ Signal Resonance:** A decentralized `[ 📻 ROGER THAT ]` interaction protocol. Acknowledge and appreciate allied transmissions instantly across the P2P network, equipped with built-in Anti-Spam Ping mitigation to protect your database.
* **📌 The Memory Vault:** A dedicated Bookmark system to pin important transmissions locally. Powered by an INNER JOIN architecture, bookmarks automatically vanish if the origin node executes a Global Purge or Ghost Protocol self-destruct.
* **🤝 Symmetric Key Exchange:** Flawless mutual follow handshake protocol ensuring perfect cryptographic alignment between nodes, completely eliminating the 401 Spoofing Paradox errors during P2P connections.
* **The Oracle (Real-Time Alerts):** Securely integrate a private Telegram Bot to act as your station's Early Warning System. Receive instant tactical notifications for incoming Laser Links, Sonar Pulses, follower handshakes, and security alerts even when your browser is closed.
* **Constellation Star Chart (P2P Following):** Connect to other Relay nodes simply by adding their URL. The system automatically validates the target node and prevents echo chambers via built-in Anti-Self Loop protocols.
* **The Nomadic Protocol (Token Re-Sync):** Absolute domain independence. If your server dies, move your SQLite database to a new domain. Your station will automatically fire a cryptographic Re-Sync pulse to update your URL across all allied nodes instantly.
* **The Station Archive & Escape Pod:** Complete data portability. Download a full one-click ZIP backup of your entire station (source code, media, and database), or execute a quick export of just the core SQLite memory for safe, seamless migrations.
* **The Quantum Gate:** Silky smooth, zero-reload authentication. Your Master Passcode decrypts your Vault and logs you in simultaneously in milliseconds.
* **The Lighthouse Protocol:** An opt-in headless directory. Transmit your coordinates to a central beacon to be discovered organically, or stay completely hidden.
* **The Handshake Protocol (Smart Alerts):** When a foreign node locks onto your coordinates or sends a Laser Link, your console's alert system will notify you, allowing for seamless one-click mutual connections without sacrificing your firewall.
* **Public Hologram & E2E Laser Links:** Broadcast messages to the public timeline, or send target-specific Direct Messages (Laser Links) secured by native RSA-OAEP 2048-bit End-to-End Encryption.
* **The Encrypted Key Vault (Multi-Device):** Seamlessly login across multiple devices. Your E2E Private Key is secured on the server using AES-GCM encryption, unlocked only by your Master Passcode in the browser.
* **Advanced Media Matrix:** Upload up to 4 mixed media files (Images, Video, Audio) seamlessly rendered in a dynamic CSS Grid Gallery with immersive cyberpunk Hologram FX.
* **The Scorched Earth Protocols:** Send a silent missile to permanently wipe Direct Message history on both local and target remote nodes simultaneously. 
* **PTT Audio Logs (Walkie-Talkie):** Hold to record and broadcast short voice transmissions natively from your browser. Includes E2E encryption support, a tactical Web Audio API squelch generator, and a retro playback interface.
* **Tactical Quote:** Securely quote incoming text messages in both Public and Direct channels without breaking media references.
* **Tactical Sonar Pulse:** A highly lightweight, low-bandwidth pinging system. Send short encrypted alphanumeric codes to allied nodes. The receiving station decodes the signal in real-time using a native Web Audio API Morse Code synthesizer.
* **The ACK Protocol (Read Receipts):** A built-in, decentralized acknowledgment system for Direct Messages. When your encrypted Laser Link is opened by the target node, a silent payload is fired back, updating your local console with a real-time `[ READ ]` status indicator.
* **Ghost Protocol:** Transmit highly sensitive text and media that will automatically and physically self-destruct from the SQLite database and the server's hard drive after 24 hours.
* **Bunker Mode (Private Node):** Toggle your station into stealth mode at any time. Your public hologram timeline will be sealed from outsiders, and any incoming follower requests will be held in your console for manual approval.
* **Deep Space Radar Sweep:** An automated pinging system that scans your Star Chart. If an allied node goes offline or is destroyed, your radar automatically purges them from your database to keep your node clean.
* **Client-Side WebP Compression:** Uploaded media is compressed into lightweight WebP format directly in the user's browser before transmission, saving massive server bandwidth.
* **Self-Healing OTA Updates:** A built-in Over-The-Air (OTA) updater that allows station commanders to patch their node to the latest version with a single click.

---

## 🌍 Deployment Flexibility (Agnostic Routing)

Relay Station is engineered to be **Subdomain and Subfolder Agnostic**. You can deploy your node anywhere without breaking the transmission routing or facing identity crisis bugs:
* **Root Domain:** `https://your-domain.com`
* **Subdomain:** `https://relay.your-domain.com`
* **Deep Subfolder:** `https://your-domain.com/secret/bunker/node`

The Transmitter Engine will automatically detect its exact coordinates and introduce itself correctly to the Constellation.

---

## ⚖️ Architecture: Pros & Cons

Relay Station is built on a pure **Peer-to-Peer (P2P) Distributed Architecture**. This brings absolute freedom, but also specific technical trade-offs.

### The Advantages (Pros)
1.  **Extreme Resilience (Anti-Fragile):** There is no central tracking server. If 90% of the nodes in the universe are destroyed, the remaining 10% will continue to function and communicate flawlessly.
2.  **Zero-Configuration Deployment:** The "Drop-Pod" installer builds the database, sets up security protocols, and self-destructs the installation files automatically. 
3.  **Lightweight & High Concurrency:** Uses SQLite for core memory. Powered by **Write-Ahead Logging (WAL) Mode** and an **Anti-Collision Engine (Busy Timeout)**, the database allows simultaneous read and write operations without locking, gracefully handling high-traffic P2P signal bombardments with minimal RAM. It also cleans up its own orphan files automatically via an advanced Garbage Collector.
4.  **Censorship Resistant:** No algorithm, no shadow-banning, and no central moderation. You only receive data from the nodes you explicitly trust and follow.

### The Limitations (Cons)
1.  **Optional Discovery:** Because there is no central tracker by default, there is no global "explore" page. However, you can explicitly opt-in to *The Lighthouse Protocol* to list your node in a public directory, or remain entirely hidden in Bunker Mode and share your URL manually.
2.  **Eventual Consistency:** This is not a real-time WebSocket chat app. Signals are fired via asynchronous HTTP requests (cURL). It may take a few seconds for a message to propagate across a large constellation.
3.  **Storage Responsibility:** While the Garbage Collector helps delete old public messages and Ghost Protocol media, permanent media storage relies entirely on your own server's capacity. The new Relay feature also stores copies locally, though you maintain the ability to manually purge them.
4.  **Trust-Based Network:** If a node you follow starts broadcasting spam, your only defense is to manually trigger the **Disconnect Protocol** (Unfollow). 

---

## 🔌 Installation (Genesis Deployment)

Relay Station utilizes a highly secure **Drop-Pod Installation** method. 

1. Download the latest `Relay-Installer-vX.X.X.zip` from the **Releases** tab.
2. Upload and extract the ZIP file into your server's chosen directory.
3. Access `https://your-domain.com/path-to-folder/install.php` via your web browser.
4. Create your Master Passcode.
5. The system will extract the core files, build the SQLite database, secure the data directories, and **automatically delete the installer files** to prevent unauthorized access.

### Updating the Station
When a new version is available, you do not need to download anything manually. Simply click the **`[ SYS_UPDATE ]`** button inside your Relay Console to initiate the Over-The-Air patch. For Telegram notifications setup, please refer to [TELEGRAM.md](TELEGRAM.md).

---

## 🛡️ Security Systems

* **The Anti-Loop Shield (NEW):** Blocks infinite relay loops and echo chambers by enforcing strict `origin_id` tracking across the entire network.
* **Advanced HTML Sanitization:** Extreme data purging mechanisms (strip_tags, filter_var) neutralize XSS payloads and malicious scripts from foreign nodes before they can breach the core memory.
* **The Oracle Sentinel:** Real-time Telegram alerts for successful Commander logins and anti-brute force lockouts, providing an immediate layer of defensive awareness.
* **Symmetric Key Exchange Enforcer:** Perfect cryptographic alignment during the mutual follow sequence. Stations exchange and enforce symmetrical tokens upon connection to completely eliminate 401 Spoofing Paradox errors across the Constellation.
* **End-to-End Encryption (E2E):** Direct messages use Dual-Ciphertext Routing. Your Private Key never leaves your browser's local storage, ensuring a Zero-Knowledge architecture—even server admins cannot read the SQLite database.
* **Cloudflare WAF Integration:** To protect your node from DDoS without breaking the P2P Constellation, follow the [Official WAF Defense Guide (SECURITY.md)](SECURITY.md).
* **Rate Limiting & Anti-Spoofing:** The `api_inbox.php` endpoint restricts incoming transmissions to a maximum of 5 signals per minute per IP address.
* **Symmetrical Firewall:** Incoming signals are only accepted if the sender's planet URL is explicitly listed in your Star Chart (Following list). Unknown intruders are automatically dropped.
* **Anti-Brute Force Lockout:** The system automatically freezes the login radar for 15 minutes after 5 consecutive failed passcode attempts to protect against dictionary and bot attacks.
* **Strict SSL Enforcement:** All endpoints, including UI and API P2P receivers, strictly require encrypted HTTPS connections. Unsecured HTTP requests are automatically redirected or rejected to prevent packet sniffing.
* **Encrypted Sessions:** Console access requires a Master Passcode hashed securely within the SQLite core memory.

---

## 📜 License

This project is open-source and built for the Sovereign Web. Feel free to fork, modify, and deploy your own constellation.

> *"We are just stars trying to connect in the vast emptiness."*