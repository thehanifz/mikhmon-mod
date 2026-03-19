# MIKHMON V3
> **Modified by [thehanifz](https://github.com/thehanifz)** — RouterOS v6 & v7 compatibility patch

Original project by [Laksamadi Guko](https://github.com/laksa19/mikhmonv3).

---

## Tentang Mod Ini

Mod ini memperbaiki kompatibilitas Mikhmon V3 dengan **RouterOS v7** (diuji pada v7.20.8) sambil tetap mempertahankan dukungan untuk **RouterOS v6**.

### Perubahan Utama

#### Security
- Password MikroTik dienkripsi **AES-256-GCM** di `config.php`
- Password admin menggunakan **bcrypt**
- Encryption key dari environment variable (`.env`)
- CSRF protection di form login
- Security headers (X-Frame-Options, X-Content-Type-Options, dll)
- Input sanitization di `readcfg.php`

#### RouterOS v7 Compatibility
- Format tanggal `[/system clock get date]` berubah dari `jan/02/2025` ke `2026-01-02` (ISO)
- Script on-login profile diupdate untuk v7 date format
- Script bgservice (scheduler monitor expired) auto-detect format v6/v7 via `[:totime]`
- Path API `/system/script` dan `/system/scheduler` diupdate ke format v7
- Filter laporan penjualan & log pengguna support dual format owner (`mar2026` & `2026-03`)
- Query `?owner` exact match untuk mencegah false positive filter bulan

#### Bug Fixes
- Fix double `decrypt()` / double `$API->connect()` di banyak file
- Fix urutan include — `routeros_api.class.php` harus sebelum `config.php`
- Fix `security.php` harus tersedia sebelum `readcfg.php`
- Fix dropdown filter bulan/tahun di Selling Report dan User Log
- Fix widget traffic dashboard (try-catch JSON.parse)
- Fix hotspot active list kosong
- Fix scheduler tidak terbuat di RouterOS v7

#### File yang Dimodifikasi

| File | Perubahan |
|------|-----------|
| `include/security.php` | NEW — AES-256-GCM, bcrypt, CSRF, rate limiting |
| `include/readcfg.php` | Input sanitization, null coalescing |
| `lib/routeros_api.class.php` | Deteksi versi ROS, wrapper encrypt/decrypt |
| `hotspot/adduserprofile.php` | On-login & bgservice v7 compatible |
| `hotspot/userprofilebyname.php` | On-login & bgservice v7 compatible |
| `hotspot/hotspotactive.php` | Fix connect, include order |
| `hotspot/quickuser.php` | Fix date format comment, include order |
| `hotspot/generateuser.php` | Fix date format comment |
| `dashboard/home.php` | Fix traffic widget JS |
| `dashboard/aload.php` | Fix include order, connect |
| `report/selling.php` | Dual owner query v6+v7, dropdown v7 |
| `report/userlog.php` | Dual owner query v6+v7, dropdown v7 |
| `report/print.php` | Fix double connect, dual owner |
| `report/livereport.php` | Fix date format, dual owner |
| `report/resumereport.php` | Fix parse idbl v6+v7 |
| `traffic/traffic.php` | Fix include order, connect |
| `process/*.php` | Fix double connect |
| `status/*.php` | Fix double connect |
| `voucher/print.php` | Fix include order |
| `include/menu.php` | Fix link idbl format v7 |

---

## Setup

### Requirements
- PHP 7.4+
- Docker (opsional)
- MikroTik RouterOS v6 atau v7

### Environment Variable
Buat file `.env` di root direktori:
```
MIKHMON_KEY=your-random-32-char-key-here
```

### Deploy dengan Docker
```bash
docker compose up -d
```

### Deploy Manual
```bash
php -S 0.0.0.0:8000 -t /var/www
```

---

## Changelog Original

#### Update 06-30 2021 V3.20
1. Perbaikan typo script profile `on-login`.

#### Update 24-01 2021
1. Added docker-compose.yml for test-lab.

#### Update 09-08 2020 V3.19
1. Penambahan jumlah sisa voucher di "option comment" laman user list.

#### Update 08-16 2019 V3.18
1. Penambahan harga jual. (Harga yang tampil di voucher)

#### Update 08-06 2019 V3.17
1. Perbaikan live report, generate users, idle timeout, ping IP.

#### Update 07-14 2019 V3.16
1. Penambahan address pool di add/edit user profile.

#### Update 07-02 2019 V3.15
1. Update RouterOS API for support v6.45.x

#### Update 01-22-2019 V3.8
1. Traffic dashboard dengan Highchart, Traffic Monitor.

#### Update 01-27-2019 V3.9
1. Penambahan fitur Resume Report.

---

*Original: [laksa19/mikhmonv3](https://github.com/laksa19/mikhmonv3)*
*Modified: [thehanifz](https://github.com/thehanifz) — RouterOS v7 patch*