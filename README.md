# 📡 RELAY STATION (v3.0 - Fediverse Edition)

**Decentralized Interplanetary Communication Terminal**

RELAY adalah perangkat lunak *microblogging* dan komunikasi *Peer-to-Peer* (P2P) murni yang dirancang untuk satu tujuan: **Kedaulatan Data Mutlak**. Tidak ada server pusat. Tidak ada algoritma. Tidak ada korporasi yang memantau sinyal Anda. 

Anda menginstal stasiun ini di *hosting* Anda, dan Anda memiliki kendali penuh atas siapa yang bisa berkomunikasi dengan Anda di luasnya kehampaan digital.

![UI Concept](https://img.shields.io/badge/UI_Concept-Satellite_Terminal-0a0a0a?style=for-the-badge&logo=gnu-terminal&logoColor=4af626)
![Tech Stack](https://img.shields.io/badge/Tech_Stack-PHP_Native_%2B_SQLite3-blue?style=for-the-badge&logo=php)
![Version](https://img.shields.io/badge/Version-3.0_Fediverse-orange)

## 🌌 Filosofi: Satu Kapal, Satu Kapten
RELAY mengusung arsitektur **Sovereign Single-Tenant**. Satu domain / satu server HANYA ditujukan untuk satu pengguna (Sang Kapten). 
Anda tidak perlu mendaftar di server orang lain. Anda adalah pemegang kunci dari stasiun relay Anda sendiri.

## 🚀 Fitur Taktis Utama (v3.0)

* **Terminal UI Aesthetic:** Antarmuka dirancang menyerupai komputer kendali satelit bawah tanah dengan fitur *Infinite Scroll* (AJAX) tanpa muat ulang halaman.
* **Fediverse Multimedia Hotlinking:** Dukungan unggah gambar antar-stasiun tanpa membebani *database* lawan.
* **Client-Side Image Compression:** Gambar dikompresi menjadi format WebP langsung di *browser* Anda sebelum diunggah, menghemat *bandwidth* server hingga 90%.
* **Direct Point-to-Point (P2P):** Pesan rahasia (*Laser Link*) dikirim secara langsung dari server Anda ke server tujuan tanpa perantara.
* **Ghost Protocol:** Opsi penghancuran pesan otomatis (*Self-Destruct*) setelah 24 jam.
* **Atmospheric Shield (Anti-Spoofing):** Dilengkapi *True Rate-Limiting* berbasis IP fisik pengirim untuk menangkal serangan *Spam* dan pemalsuan identitas domain.
* **O(N) Garbage Collection:** Mesin akan otomatis membersihkan file gambar yatim piatu di latar belakang menggunakan algoritma C-level (`array_diff`) yang sangat ringan bagi prosesor.

## 📊 Proyeksi Kapasitas Server (Shared Hosting)
RELAY dirancang agar sangat ringan. Namun, performa P2P sangat bergantung pada batas *Entry Processes* (EP) dan CPU *hosting* Anda. 

Untuk *Shared Hosting* standar (1 Core CPU, 1GB RAM, 25 EP, 4GB SSD):
* 🟢 **Zona Hijau (10 - 150 Node Teman):** Sangat lancar. Layar *loading* saat *broadcast* hanya memakan waktu 2-5 detik. Bebas *error*.
* 🟡 **Zona Kuning (150 - 300 Node Teman):** Mulai terasa beban antrean koneksi. *Broadcast* butuh 10-20 detik.
* 🔴 **Zona Merah (> 400 Node Teman):** Rawan *Crash*. Rentan terkena *Error 508 (Resource Limit)* akibat batas eksekusi CPU saat menembakkan banyak sinyal sekaligus.

## 🗺️ Roadmap & Limitasi Skalabilitas (Menuju v4.0)
Karena arsitektur V3 menggunakan pemrosesan sinkronous murni, ini adalah batasan fisika komputasi yang kelak akan kita pecahkan pada Evolusi V4:

1. **The Broadcast Bomb (Solusi Terjadwal: Asynchronous Queues)**
   * *Masalah:* Melakukan *Multi-cURL* ke 5.000 stasiun sekaligus akan menghabiskan memori dan memicu PHP Timeout (504).
   * *Rencana v4:* Menggunakan sistem *Background Queue* (Antrean Latar Belakang) atau *Cron Job* agar pesan ditembakkan secara bertahap.
2. **The SQLite Write-Lock (Solusi Terjadwal: Database Migration)**
   * *Masalah:* Jika ada 1.000 stasiun membalas pesan Anda di detik yang sama, file SQLite akan terkunci (*Database is locked*).
   * *Rencana v4:* Menambahkan dukungan opsional ke MySQL/PostgreSQL untuk menangani puluhan ribu *query* serentak dengan *Row-Level Locking*.
3. **Radar Sweep Timeout (Solusi Terjadwal: Chunking & Webhooks)**
   * *Masalah:* Mengeping ribuan stasiun sekaligus akan dihentikan paksa oleh server (`max_execution_time`).
   * *Rencana v4:* Memecah proses ping menjadi kelompok kecil (*Chunking*) atau beralih ke sistem *PubSub/Webhook*.
4. **Viral Media Bandwidth (Solusi Terjadwal: S3/R2 Cloud Storage)**
   * *Masalah:* Jika satu gambar di server lokal Anda viral dan di-*hotlink* jutaan kali, kuota *Bandwidth* server Anda akan habis.
   * *Rencana v4:* Integrasi otomatis pengunggahan folder `/media/` ke *Object Storage* eksternal seperti Cloudflare R2 atau AWS S3.

## ⚙️ Kemudahan Deployment
RELAY dibangun untuk mereka yang tidak ingin repot berurusan dengan *Node.js*, *Docker*, atau *PostgreSQL* yang berat.
Cukup unggah *file* ke *Shared Hosting* termurah sekalipun, jalankan `install.php` di *browser*, dan stasiun Anda akan menciptakan *database* SQLite-nya sendiri lalu mengunci sistem secara otomatis.

## 🛠️ Persyaratan Sistem Minimum
* PHP 8.0+ (dengan ekstensi `curl`, `sqlite3`, `zip`, `pdo`)
* Web Server (Apache/Nginx/LiteSpeed)
* Kapasitas penyimpanan minimal 50MB

---
*“Transmit your signal into the void. Let the right nodes find you.”*