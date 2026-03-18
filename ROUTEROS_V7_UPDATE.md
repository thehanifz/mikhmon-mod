# Mikhmon V3 - RouterOS v7.20.8 Compatibility Update

## ⚠️ PENTING: Backward Compatible dengan RouterOS v6!

**Update ini TIDAK menghapus support untuk RouterOS v6!**

Mikhmon V3 sekarang **AUTO-DETECT** versi RouterOS dan menyesuaikan:
- ✅ **RouterOS v6.x** (6.40+) - Fully Supported
- ✅ **RouterOS v7.x** (7.1-7.20+) - Fully Supported

Sistem akan otomatis detect versi RouterOS saat connect dan menggunakan path/script yang sesuai.

---

## Ringkasan Perubahan

Dokumen ini menjelaskan perubahan yang dilakukan untuk mendukung RouterOS v7.20.8 Long Term.

### File yang Dimodifikasi

1. **lib/routeros_api.class.php**
2. **hotspot/adduserprofile.php**
3. **hotspot/userprofilebyname.php**
4. **index.php**
5. **admin.php**

### File Baru

1. **include/routeros_v7.php** - RouterOS v7 compatibility layer
2. **backup_v7/** - Backup file original untuk perbandingan

---

## Perubahan Detail

### 1. RouterOS API Class (`lib/routeros_api.class.php`)

#### Penambahan Properti Baru
```php
var $api_ver   = 6;     // RouterOS API version (6 or 7)
var $ros_version = 6;   // RouterOS major version
```

#### Peningkatan SSL Support
- Menambahkan `allow_self_signed => true` untuk mendukung sertifikat SSL self-signed di RouterOS v7
- Memperbaiki konfigurasi SSL context untuk kompatibilitas yang lebih baik

#### Fungsi Deteksi Versi
Menambahkan 4 fungsi baru untuk deteksi dan kompatibilitas RouterOS v7:

1. **detectRouterOSVersion()** - Mendeteksi versi RouterOS secara otomatis saat connect
2. **getRouterOSVersion()** - Mengembalikan versi RouterOS yang terdeteksi
3. **isRouterOSv7()** - Cek apakah running di RouterOS v7+
4. **getCompatPath()** - Mengembalikan path API yang sesuai versi

### 2. Script On-Login (`hotspot/adduserprofile.php` & `userprofilebyname.php`)

#### Perubahan Path Script
| Sebelum (v6) | Sesudah (v7) |
|--------------|--------------|
| `/system script add` | `/system/script/add` |
| `/sys sch add` | `/system/scheduler/add` |
| `/sys sch get` | `/system/scheduler/get` |
| `/sys sch find` | `/system/scheduler/find` |
| `/sys sch remove` | `/system/scheduler/remove` |

#### Alasan Perubahan
RouterOS v7 lebih ketat dalam parsing command. Menggunakan path lengkap (`/system/scheduler`) lebih reliable daripada shorthand (`/sys sch`).

### 3. Include Files (`index.php` & `admin.php`)

Menambahkan include untuk compatibility layer:
```php
// RouterOS v7 compatibility layer
include('./include/routeros_v7.php');
```

---

## Compatibility Layer (`include/routeros_v7.php`)

File baru ini menyediakan fungsi helper untuk kompatibilitas RouterOS v7:

### Fungsi yang Tersedia

1. **detectRouterOSVersion($API)** - Deteksi versi RouterOS
2. **getSystemScriptPath($API)** - Dapatkan path /system/script yang sesuai
3. **getSystemSchedulerPath($API)** - Dapatkan path /system/scheduler yang sesuai
4. **addSystemScript($API, $params)** - Tambah script dengan policy v7
5. **addSystemScheduler($API, $params)** - Tambah scheduler dengan policy v7
6. **formatDateForROSv7($date_string)** - Format tanggal untuk v7
7. **isRouterOSv7($API)** - Cek apakah RouterOS v7
8. **getRouterOSVersion($API)** - Dapatkan versi RouterOS

---

## Catatan Penting RouterOS v7.20.8

### Path yang Masih Sama
RouterOS v7.20.8 **TIDAK** mengubah path berikut (masih kompatibel dengan v6):
- `/system/script` - Masih digunakan (bukan `/system/scripting`)
- `/system/scheduler` - Masih digunakan (bukan `/system/scripting/scheduler`)
- `/ip/hotspot/user` - Tidak berubah
- `/queue/simple` - Tidak berubah

### Perubahan di v7.18+ Beta
Perubahan path ke `/system/scripting` hanya terjadi di RouterOS v7.18+ beta. Untuk versi stable 7.20.8 long term, path lama masih berfungsi.

### Policy Permissions
RouterOS v7 memerlukan policy yang lebih eksplisit untuk script:
```php
"policy" => array("read", "write", "test", "reboot")
```

### SSL/TLS
RouterOS v7 lebih ketat dengan SSL:
- Support untuk certificate yang lebih modern
- Perlu `allow_self_signed` untuk self-signed certificates

---

## Cara Test

### Menggunakan Docker (Recommended)

1. **Start Mikhmon:**
```bash
cd /root/main-app/mikhmon-v3
docker compose up -d
```

2. **Akses Mikhmon:**
- URL: **http://localhost:999**
- Atau: **http://<IP-CasaOS>:999**
- Via Cloudflare Tunnel: **https://mikhmon.thehanifz.fun**

3. **Login Default:**
- Username: `mikhmon`
- Password: `1234`

4. **Add RouterOS Connection:**
- Klik "Add Session"
- IP Address: IP RouterOS v7.20.8 Anda
- Username: admin (atau user dengan API access)
- Password: password router
- Port: 8728 (API) atau 8729 (API-SSL)

5. **Test Fungsi RouterOS v7:**
   - Dashboard - Cek connection status
   - Hotspot > User Profiles - Add profile dengan expired mode
   - Hotspot > Users - Generate users
   - Hotspot > Quick Print - Test voucher print
   - Report > Selling - Check sales report
   - System > Scheduler - Monitor scheduler

### Menggunakan PHP Built-in Server (Alternative)

```bash
cd /root/main-app/mikhmon-v3
php -S localhost:999 -t .
```

Akses: http://localhost:999

---

## Troubleshooting

### Masalah: "invalid username or password"
**Solusi:** Pastikan API service enabled di RouterOS:
```
/ip service print
/ip service enable api
```

### Masalah: "connection timeout"
**Solusi:** 
1. Cek firewall RouterOS tidak memblok port 8728
2. Test koneksi dengan telnet: `telnet <router-ip> 8728`

### Masalah: Script on-login tidak jalan
**Solusi:**
1. Cek policy user API memiliki permission "write" dan "test"
2. Test script manual di terminal RouterOS
3. Enable logging untuk debug: `/system logging add topics=script action=memory`

### Masalah: Scheduler tidak execute
**Solusi:**
1. Cek scheduler enabled: `/system/scheduler/print`
2. Test on-event script manual
3. Cek log: `/log print where topics~"scheduler"`

---

## Rollback

Jika ada masalah, restore dari backup:
```bash
cp backup_v7/routeros_api.class.php lib/
cp backup_v7/adduserprofile.php hotspot/
cp backup_v7/userprofilebyname.php hotspot/
# dst...
```

---

## Referensi

- [MikroTik RouterOS v7 API Documentation](https://help.mikrotik.com/docs/display/ROS/RouterOS+API)
- [RouterOS v7.20 Release Notes](https://mikrotik.com/download/changelogs/)
- [Mikhmon V3 GitHub](https://github.com/laksa19/mikhmonv3)

---

**Update:** 2026-02-21
**Versi Mikhmon:** V3.20
**RouterOS Target:** v7.20.8 Long Term
