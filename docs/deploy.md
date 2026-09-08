# Deploy Runbook — Production

Production เป็น **VPS เดี่ยว สภาพแวดล้อมเดียว** (ไม่มี staging บนคลาวด์ — dev อยู่บนเครื่อง local เท่านั้น) เพราะงบจำกัดและเครื่อง RAM 2GB

## เครื่องและการเข้าถึง

| | |
|---|---|
| ผู้ให้บริการ | Hostatom VPS (SSD1, 2GB RAM + 2GB swap, 20GB disk) |
| IP | `203.146.252.200` |
| OS | Ubuntu 24.04 LTS |
| user | `deploy` (อยู่ในกลุ่ม `sudo`, `docker` — sudo ต้องใส่รหัส) |
| SSH | `ssh gamoryid` (alias ใน `~/.ssh/config` → key `~/.ssh/gamoryid_deploy`, user `deploy`) |
| โค้ดบนเครื่อง | `/opt/gamoryid` (git clone ของ repo นี้, deploy ด้วย `git pull`) |
| env | `/opt/gamoryid/.env` — ไฟล์เดียวกับที่ Laravel และ `docker compose` อ่าน |

fail2ban เปิดอยู่ (jail `sshd`, ban 10 นาที) — ถ้า login ไม่ได้มักเป็นเพราะไม่ได้ใช้ key/ผิด user ไม่ใช่โดนแบน (ตรวจ: `ssh gamoryid` จากเครื่องเดิมยังเข้าได้ไหม)

## Stack (docker compose)

`/opt/gamoryid/docker-compose.yml` — 6 service, budget RAM รวม ~1.2GB:

| service | image | หน้าที่ | mem |
|---|---|---|---|
| `db` | `mariadb:10.11` | ฐานข้อมูล (ไม่ map port ออก host — เข้าถึงได้เฉพาะใน docker network ชื่อ `db`) | 350m |
| `app` | `ghcr.io/alchermy/gamoryid-app` | Laravel API (FrankenPHP, พอร์ต 8000 ภายใน) | 400m |
| `worker` | เดียวกับ `app` | `queue:work --queue=default,notifications` | 150m |
| `scheduler` | เดียวกับ `app` | loop เรียก `schedule:run` ทุกนาที | 100m |
| `web` | `ghcr.io/alchermy/gamoryid-web` | **Caddy** — service เดียวที่ publish 80/443, terminate TLS, เสิร์ฟ SPA ทั้งสองตัว + reverse proxy `/api` | 100m |
| `pma` | `phpmyadmin:5.2` | GUI ดูฐานข้อมูล (ดูหัวข้อ phpMyAdmin ด้านล่าง) | 128m |

> ใช้ MariaDB ไม่ใช่ `mysql:8.0` เพราะ image mysql รุ่นใหม่ต้องการ CPU baseline x86-64-v2 แล้ว crash บน vCPU ของ VPS ตัวนี้

## โดเมนและ TLS

DNS อยู่ที่ **Cloudflare** ทุก record เปิด proxy (เมฆส้ม) → Cloudflare ต่อ origin แบบ **Full** (ยังไม่ strict)
Caddy ขอ cert Let's Encrypt เอง (http-01 ผ่าน Cloudflare, tls-alpn-01 ใช้ไม่ได้เพราะ CF terminate TLS — Caddy fallback ให้อัตโนมัติ)

| โดเมน | ไปที่ |
|---|---|
| `gamoryid.com`, `www.` | public storefront (`/srv/public`) + `/sitemap.xml`,`/robots.txt` → `app:8000` |
| `app.gamoryid.com` | merchant SPA (`/srv/app`) |
| `api.gamoryid.com` | `reverse_proxy app:8000` |
| `console-x7k2.gamoryid.com` | phpMyAdmin (`reverse_proxy pma:80`) หลัง HTTP basic auth |

`Caddyfile` อยู่ที่ repo root และถูก **bake เข้า image `web` ตอน build** (`COPY Caddyfile` ใน `Dockerfile.web`) → แก้ Caddyfile แล้ว **ต้อง `docker compose build web`** ก่อน `up -d web` ไม่งั้น config ไม่เข้า

## ขั้นตอน deploy

```bash
# 1. บนเครื่อง dev — push
cd C:\gamoryid && git push origin main

# 2. บน VPS
ssh gamoryid
cd /opt/gamoryid
git pull origin main

# 3. build เฉพาะที่เปลี่ยน
docker compose build app        # โค้ด backend เปลี่ยน
docker compose build web        # โค้ด frontend หรือ Caddyfile เปลี่ยน

# 4. หมุน container
docker compose up -d app worker scheduler web

# 5. ถ้ามี migration ใหม่
docker compose exec -T app php artisan migrate --force

# 6. เสมอ หลัง .env หรือ config เปลี่ยน
docker compose exec -T app php artisan config:cache

# 7. ถ้าคำสั่ง Discord (slash command) เปลี่ยนนิยาม
docker compose exec -T app php artisan tinker --execute="app(App\Services\Discord\DiscordApiClient::class)->registerCommands();"
```

หลัง deploy: smoke test บน `app.gamoryid.com` / `gamoryid.com` เสมอ (ไม่มี staging)

เคลียร์พื้นที่ disk เมื่อเต็ม (ไม่ต้อง sudo): `docker builder prune -af && docker image prune -af`

## phpMyAdmin (ดูฐานข้อมูล prod)

**ทางหลัก — เปิด URL ได้เลย ไม่ต้องรันคำสั่ง:**
`https://console-x7k2.gamoryid.com` → HTTP basic auth (`admin` / รหัสเก็บใน password manager) → phpMyAdmin auto-login เป็น DB user ของแอป (`PMA_USER`/`PMA_PASSWORD` = config auth ไม่มีหน้า login ของ phpMyAdmin เอง)

- hash ของรหัส basic auth อยู่ใน `Caddyfile` บรรทัด `admin $2a$...`
- เปลี่ยนรหัส: `docker compose exec web caddy hash-password --plaintext 'รหัสใหม่'` → เอา hash ไปแทนใน Caddyfile → `git push` → `git pull && docker compose build web && docker compose up -d web`
- เปลี่ยนชื่อ subdomain: แก้ชื่อใน `Caddyfile` + `PMA_ABSOLUTE_URI` ใน `docker-compose.yml`, เพิ่ม/ลบ A record ที่ Cloudflare (proxied), แล้ว build+up `web`

**ทางสำรอง — SSH tunnel (ใช้ root ได้):**
```bash
ssh -L 8081:localhost:8081 gamoryid      # เปิดค้างไว้
# แล้วเปิด http://localhost:8081  (bind 127.0.0.1 เท่านั้น ไม่ออกเน็ต)
```

**CLI (root / query สั้น ๆ):**
```bash
ssh gamoryid 'cd /opt/gamoryid && docker compose exec db sh -c '\''mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'\'''
```

`docker compose stop pma` คืน RAM ~45MB ตอนไม่ใช้ (`restart: unless-stopped` → รีบูตเครื่องกลับมาเอง)

## สำรอง / ล้างข้อมูล

สำรองทั้ง DB:
```bash
ssh gamoryid 'cd /opt/gamoryid && docker compose exec -T db sh -c '\''mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'\'' | gzip > ~/gamoryid-backup-$(date +%F-%H%M).sql.gz'
```

ล้างข้อมูลธุรกรรมทั้งหมด (เก็บ users/shops/members/subscriptions/discord): TRUNCATE `inventory_items`, `inventory_credentials`, `inventory_media`, `reservations`, `sales`, `customers`, `credit_transactions`, `payment_submissions`, `slip_verifications`, `activity_logs`, `discord_command_logs`, `import_jobs`, `import_errors`, `shop_view_daily`, `discord_link_codes`, `discord_setup_codes` + `UPDATE shops SET credit_balance = 0` (FK checks off) — จากนั้นลบไฟล์ `storage/app/private/{inventory,imports,slips}/*` และ `php artisan queue:flush && cache:clear && docker compose restart worker`

## ยังไม่ได้ทำ (backlog infra)

- CI/CD (GitHub Actions push image → GHCR → VPS `docker compose pull`) — ตอนนี้ build บน VPS
- DB backup อัตโนมัติ (cron + ทดสอบ restore)
- Cloudflare SSL Full → Full (strict)
- ufw จำกัดให้รับเฉพาะ IP ของ Cloudflare
- `/etc/sudoers.d/deploy-fail2ban` NOPASSWD ให้ `deploy` unban ได้เอง
