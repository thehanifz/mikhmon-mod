# Mikhmon V3 - Setup & Testing Guide

## Quick Start

### 1. Start Mikhmon

```bash
cd /root/main-app/mikhmon-v3
docker compose up -d
```

### 2. Access Mikhmon

- **Local:** http://localhost:999
- **Network:** http://<YOUR-IP>:999
- **Cloudflare Tunnel:** https://mikhmon.thehanifz.fun

### 3. Login

- **Username:** `mikhmon`
- **Password:** `1234`

---

## RouterOS v7.20.8 Connection

### Prerequisites
- RouterOS v7.20.8 device accessible from network
- API service enabled on RouterOS

### Enable API on RouterOS

```bash
# Via SSH/Winbox
/ip service print
/ip service enable api
```

Default API port: **8728**

### Add Router in Mikhmon

1. Login to Mikhmon
2. Click **"Add Session"**
3. Fill in:
   - **IP Address:** Your RouterOS IP (e.g., 192.168.88.1)
   - **Username:** admin
   - **Password:** your router password
   - **Port:** 8728 (or 8729 for API-SSL)
4. Click **"Save"**

---

## Testing RouterOS v7 Compatibility

### 1. Dashboard
- [ ] Check connection status (should be green/connected)
- [ ] View system resource
- [ ] Check hotspot statistics

### 2. Hotspot User Profile
- [ ] Add new profile
- [ ] Set expired mode (Remove/Notice/Remove&Record/Notice&Record)
- [ ] Set validity period
- [ ] Set price
- [ ] Save profile

### 3. Hotspot Users
- [ ] Generate users with selected profile
- [ ] Print vouchers
- [ ] Quick print test

### 4. Expiry System
- [ ] Wait for user to login
- [ ] Check scheduler created automatically
- [ ] Verify expired user removed/noticed based on mode

### 5. Reports
- [ ] Live Report - check real-time sales
- [ ] Selling Report - view historical data
- [ ] User Log - check user activity

### 6. System Scheduler
- [ ] View auto-created monitor schedulers
- [ ] Enable/Disable scheduler
- [ ] Test scheduler execution

---

## Troubleshooting

### Cannot Connect to RouterOS

**Error:** "Connection timeout" or "Cannot connect to router"

**Solutions:**
1. Check API service enabled: `/ip service print`
2. Check firewall not blocking port 8728
3. Test connectivity: `telnet <router-ip> 8728`
4. Verify username/password

### Login Script Errors

**Error:** Script errors in on-login

**Solutions:**
1. Update user profile from Mikhmon (open and re-save)
2. Check RouterOS logs: `/log print where topics~"hotspot"`
3. Verify scheduler permissions

### Port 999 Already in Use

**Error:** "Address already in use"

**Solutions:**
```bash
# Check what's using port 999
lsof -i :999

# Stop conflicting service or change port in docker-compose.yml
```

### Docker Container Won't Start

```bash
# View logs
docker logs mikhmon

# Restart container
docker compose restart

# Rebuild
docker compose down
docker compose up -d
```

---

## File Structure

```
/root/main-app/mikhmon-v3/
├── docker-compose.yml          # Docker configuration
├── nginx.conf                  # Nginx config (not used in v7)
├── Dockerfile.php              # PHP-FPM Dockerfile (deprecated)
├── index.php                   # Main application
├── admin.php                   # Admin panel
├── hotspot/                    # Hotspot management
│   ├── adduserprofile.php     # Add user profile (RouterOS v7 updated)
│   └── userprofilebyname.php  # Edit user profile (RouterOS v7 updated)
├── lib/
│   └── routeros_api.class.php # RouterOS API class (RouterOS v7 updated)
├── include/
│   └── routeros_v7.php        # RouterOS v7 compatibility layer
└── backup_v7/                 # Original files backup
```

---

## RouterOS v7 Changes Summary

### Modified Files

1. **lib/routeros_api.class.php**
   - Added RouterOS version detection
   - Improved SSL support
   - Added compatibility functions

2. **hotspot/adduserprofile.php**
   - Changed `/sys sch` to `/system/scheduler`
   - Changed `/system script` to `/system/script`

3. **hotspot/userprofilebyname.php**
   - Same script path updates as adduserprofile

4. **include/routeros_v7.php** (NEW)
   - RouterOS v7 compatibility layer
   - Version detection functions
   - Path compatibility helpers

### Script Path Changes

| RouterOS v6 | RouterOS v7 |
|-------------|-------------|
| `/sys sch` | `/system/scheduler` |
| `/system script` | `/system/script` |

### Important Notes

- RouterOS v7.20.8 still uses `/system/script` and `/system/scheduler`
- Path changes to `/system/scripting` only in v7.18+ beta
- Self-signed SSL certificates now require `allow_self_signed: true`
- Policy permissions more strict in v7

---

## Rollback

If you encounter issues, rollback to original files:

```bash
cd /root/main-app/mikhmon-v3
cp backup_v7/* . -r
docker compose restart
```

---

## Support

- Mikhmon V3.20
- RouterOS v7.20.8 Long Term
- PHP 7.4
- Docker Compose

## References

- [MikroTik RouterOS v7 API](https://help.mikrotik.com/docs/display/ROS/RouterOS+API)
- [Mikhmon GitHub](https://github.com/laksa19/mikhmonv3)
- [RouterOS v7.20 Release Notes](https://mikrotik.com/download/changelogs/)
