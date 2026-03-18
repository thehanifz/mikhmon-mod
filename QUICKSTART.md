# 🚀 Quick Start - Mikhmon V3 RouterOS v6/v7

## ✅ Support Kedua Versi!

| RouterOS Version | Status | Auto-Detect |
|-----------------|--------|-------------|
| **v6.x** (6.40+) | ✅ Supported | Yes |
| **v7.x** (7.1-7.20+) | ✅ Supported | Yes |

---

## 📦 Installation (Docker)

```bash
# 1. Clone/navigate ke directory
cd /root/main-app/mikhmon-v3

# 2. Start Mikhmon
docker compose up -d

# 3. Check status
docker ps
```

---

## 🌐 Access

| Method | URL |
|--------|-----|
| **Local** | http://localhost:999 |
| **Network** | http://<YOUR-IP>:999 |
| **Cloudflare** | https://mikhmon.thehanifz.fun |

**Login Default:**
- User: `mikhmon`
- Pass: `1234`

---

## 🔌 Connect ke RouterOS

### 1. Enable API di RouterOS

**Via Terminal:**
```bash
/ip service enable api
/ip service print
```

**Default port:** 8728

### 2. Add Session di Mikhmon

1. Login ke Mikhmon
2. Click **"Add Session"**
3. Fill:
   - **IP Address:** RouterOS IP (e.g., 192.168.88.1)
   - **Username:** admin
   - **Password:** router password
   - **Port:** 8728
4. Click **"Save"**

### 3. Auto-Detect Will Work!

Sistem akan otomatis:
- ✅ Detect RouterOS version (v6 or v7)
- ✅ Adjust script paths
- ✅ Set proper permissions
- ✅ Log version info

---

## 🧪 Test Functions

### Basic Test (Both v6 & v7)

```
☐ Dashboard loads
☐ RouterOS connected (green indicator)
☐ System resource displayed
☐ Hotspot users listed
```

### User Profile Test

```
☐ Add new User Profile
☐ Set expired mode (Remove/Notice)
☐ Set validity (e.g., 30d)
☐ Set price
☐ Save profile
```

### Generate Users Test

```
☐ Generate users from profile
☐ Print vouchers
☐ Quick print works
```

### Expiry Test

```
☐ User login recorded
☐ Scheduler created automatically
☐ Expired user handled (removed/noticed)
```

---

## 🔍 Check RouterOS Version

### Di Mikhmon:
Debug mode akan show:
```
RouterOS version detected: 7.20.8 (major: 7)
```

### Di RouterOS:
```bash
/system resource print
# Output: version: 7.20.8 (long-term)
```

---

## 🐛 Troubleshooting

### Cannot Connect

**Error:** "Connection timeout"

**Fix:**
```bash
# Check API enabled
/ip service print

# Check firewall
/ip firewall filter print

# Test connection
telnet <router-ip> 8728
```

### Wrong Version Detected

**Fix:**
```bash
# Re-connect session
# Mikhmon will re-detect on next connect
```

### Script Errors

**For v6:**
- Check RouterOS ≥ 6.40
- Enable API service

**For v7:**
- Check user policy: read, write, test
- Re-save user profile

---

## 📊 Compatibility Quick Reference

| Feature | RouterOS v6 | RouterOS v7 |
|---------|-------------|-------------|
| API Connect | ✅ | ✅ |
| Hotspot Users | ✅ | ✅ |
| User Profiles | ✅ | ✅ |
| Schedulers | ✅ | ✅ |
| Scripts | ✅ | ✅ |
| Reports | ✅ | ✅ |
| Auto-Detect | ✅ | ✅ |

---

## 📚 Documentation

- `SETUP_GUIDE.md` - Complete setup guide
- `ROUTEROS_V7_UPDATE.md` - v7 update details
- `COMPATIBILITY.md` - Compatibility matrix
- `README.md` - General info

---

## 🔄 Rollback (If Needed)

```bash
cd /root/main-app/mikhmon-v3
cp backup_v7/* . -r
docker compose restart
```

---

## ✅ Support

**Tested On:**
- RouterOS v6.49.6 ✅
- RouterOS v7.20.8 ✅

**Mikhmon V3.20** with RouterOS v7 Update

**Auto-Detect:** YES! System will automatically detect and adapt to RouterOS version.
