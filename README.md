# 📡 RELAY STATION

**Decentralized Interplanetary Communication Terminal**

RELAY adalah perangkat lunak *microblogging* dan komunikasi *Peer-to-Peer* (P2P) murni yang dirancang untuk satu tujuan: **Kedaulatan Data Mutlak**. Tidak ada server pusat. Tidak ada algoritma. Tidak ada korporasi yang memantau sinyal Anda. 

Anda menginstal stasiun ini di *hosting* Anda, dan Anda memiliki kendali penuh atas siapa yang bisa berkomunikasi dengan Anda di luasnya kehampaan digital.

![UI Concept](https://img.shields.io/badge/UI_Concept-Satellite_Terminal-0a0a0a?style=for-the-badge&logo=gnu-terminal&logoColor=4af626)
![Tech Stack](https://img.shields.io/badge/Tech_Stack-PHP_Native_%2B_SQLite3-blue?style=for-the-badge&logo=php)

## 🌌 Filosofi: Satu Kapal, Satu Kapten
RELAY mengusung arsitektur **Sovereign Single-Tenant**. Satu domain / satu server HANYA ditujukan untuk satu pengguna (Sang Kapten). 
Anda tidak perlu mendaftar di server orang lain. Anda adalah pemegang kunci dari stasiun relay Anda sendiri.

## 🚀 Fitur Taktis Utama

* **Terminal UI Aesthetic:** Antarmuka dirancang menyerupai komputer kendali satelit bawah tanah. Fungsional, minimalis, dan dingin.
* **Direct Point-to-Point (P2P):** Pesan dikirim secara langsung dari server Anda ke server tujuan menggunakan PHP cURL yang dibungkus enkripsi HTTPS bawaan domain.
* **Deep Space Radar:** Pantau siaran publik dari planet-planet (server) sekutu yang Anda masukkan ke dalam *Star Chart* Anda.
* **Laser Link (Encrypted DM):** Kirim pesan rahasia satu-lawan-satu tanpa pernah mampir ke server pihak ketiga.
* **Ghost Protocol:** Opsi penghancuran pesan otomatis (*Self-Destruct*) setelah 24 jam di kedua belah server.
* **Lazy Garbage Collection:** Mesin akan otomatis membersihkan *cache* dan pesan publik kedaluwarsa secara diam-diam setiap kali Anda membuka dasbor.

## ⚙️ Kemudahan Deployment

RELAY dibangun untuk mereka yang tidak ingin repot berurusan dengan *Node.js*, *Docker*, atau *PostgreSQL* yang berat.

1. **One-Click Install (`install.php`):** Cukup unggah *file* ke *Shared Hosting* termurah sekalipun, jalankan skrip di *browser*, dan stasiun Anda akan menciptakan *database* SQLite-nya sendiri lalu mengunci sistem secara otomatis.
2. **Over-The-Air (OTA) Updates:** Pembaruan sistem dilakukan langsung dari dasbor. Jika ada versi baru di GitHub ini, sistem akan mendeteksinya. Cukup klik **[ UPGRADE SYSTEM ]**, dan RELAY akan mengunduh, mengekstrak, serta melakukan migrasi *database* secara otomatis di latar belakang.

## 🛠️ Persyaratan Sistem (Sangat Ringan)
* PHP 8.0+ (dengan ekstensi `curl`, `sqlite3`, `zip`)
* Web Server (Apache/Nginx)
* Kapasitas penyimpanan minimal 50MB

---
*“Transmit your signal into the void. Let the right nodes find you.”*
