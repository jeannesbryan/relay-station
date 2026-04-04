# 🌌 Relay Station: The Constellation Network

> A sovereign, decentralized, and zero-dependency P2P communication node built for the open web. 

**Relay Station** is not a social media platform; it is a communication node. By installing this software on your server, you establish your own sovereign "Star" in a dark universe. When multiple Relay Nodes connect, they form a **Constellation**—a decentralized network of independent nodes sharing public timelines and encrypted direct messages without any central authority, tracker, or corporate oversight.

Built purely with **PHP** and **SQLite**, it is designed to run on absolutely any basic shared hosting environment. No Docker, no Node.js, no terminal commands required.

---

## ⚙️ System Requirements

Relay Station is designed to be extremely lightweight and can be hosted on a $1/month shared hosting plan or a Raspberry Pi.
* **PHP:** Version 7.4, 8.0, 8.1, or 8.2+
* **Database:** SQLite3 (No MySQL/MariaDB setup required)
* **Required PHP Extensions:** `cURL` (for transmission), `pdo_sqlite` (for core memory), `ZipArchive` (for OTA updates & installation), and `gd` or `fileinfo` (for media processing).

---

## 🚀 Core Capabilities & Features

* **100% Data Sovereignty:** You own the server, the database, and the media. There is no central database.
* **Constellation Star Chart (P2P Following):** Connect to other Relay nodes simply by adding their URL. The system automatically validates the target node.
* **Public Hologram & Laser Links:** Broadcast messages to the public timeline of your entire constellation, or send target-specific Direct Messages (Laser Links).
* **Ghost Protocol:** Transmit highly sensitive text and media that will automatically and physically self-destruct from the SQLite database and the server's hard drive after 24 hours. *(Note: Please ensure your hosting provider's automated daily backups are configured correctly if absolute physical deletion is required).*
* **Deep Space Radar Sweep:** An automated pinging system that scans your Star Chart. If an allied node goes offline or is destroyed, your radar automatically purges them from your database to keep your node clean.
* **Client-Side WebP Compression:** Uploaded media is compressed into lightweight WebP format directly in the user's browser before transmission, saving massive server bandwidth.
* **Self-Healing OTA Updates:** A built-in Over-The-Air (OTA) updater that allows station commanders to patch their node to the latest version with a single click, without touching the database or media folders.

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
3.  **Lightweight & Cost-Effective:** Uses SQLite for core memory and requires minimal RAM. It cleans up its own orphan files and old public signals automatically via an advanced Garbage Collector.
4.  **Censorship Resistant:** No algorithm, no shadow-banning, and no central moderation. You only receive data from the nodes you explicitly trust and follow.

### The Limitations (Cons)
1.  **The "Dark Universe" Concept (No Discovery):** Because there is no central tracker, there is no global "explore" page. You cannot search for users. You must know the exact URL of another Relay Station to connect with them.
2.  **Eventual Consistency:** This is not a real-time WebSocket chat app. Signals are fired via asynchronous HTTP requests (cURL). It may take a few seconds for a message to propagate across a large constellation.
3.  **Storage Responsibility:** While the Garbage Collector helps delete old public messages and Ghost Protocol media, permanent media storage relies entirely on your own server's capacity.
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
When a new version is available, you do not need to download anything manually. Simply click the **`[ SYS_UPDATE ]`** button inside your Relay Console to initiate the Over-The-Air patch.

---

## 🛡️ Security Systems

* **Rate Limiting & Anti-Spoofing:** The `api_inbox.php` endpoint restricts incoming transmissions to a maximum of 5 signals per minute per IP address.
* **Symmetrical Firewall:** Incoming signals are only accepted if the sender's planet URL is explicitly listed in your Star Chart (Following list). Unknown intruders are automatically dropped.
* **Encrypted Sessions:** Console access requires a Master Passcode hashed securely within the SQLite core memory.

---

## 📜 License

This project is open-source and built for the Sovereign Web. Feel free to fork, modify, and deploy your own constellation.

> *"We are just stars trying to connect in the vast emptiness."*