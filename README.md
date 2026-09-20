# MT5 Docker Manager

Laravel 12 + Jetstream + Inertia/Vue dashboard สำหรับ monitor และจัดการ Docker containers

## Requirements

- Ubuntu VPS
- Docker Engine และ Docker Compose plugin
- Git
- Port `8000` เปิดใช้งาน หรือวาง reverse proxy ด้านหน้า

ตรวจสอบ Docker:

```bash
docker --version
docker compose version
```

## Deploy บน VPS

### 1. Clone project

```bash
git clone https://github.com/amorthem/MT5-Docker-Manager.git
cd MT5-Docker-Manager
```

### 2. สร้าง environment

ห้ามใช้ `.env` จากเครื่อง local และห้าม commit `.env` ขึ้น GitHub

```bash
cp .env.example .env
```

แก้ค่าหลักใน `.env`:

```env
APP_NAME="MT5 Docker Manager"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://YOUR_SERVER_IP:8000
HOST_METRICS_SCOPE=vps-host
SEED_DEFAULT_USER=false
DOCKER_SOCKET_HOST=/var/run/docker.sock
```

### 3. Build และ start

```bash
docker compose up -d --build
```

Docker จะสร้าง `APP_KEY` อัตโนมัติในครั้งแรกและเก็บไว้ใน named volume `app-config` จึงไม่เกิด `MissingAppKeyException` และ key จะคงเดิมหลัง restart

ถ้าต้องการกำหนด key เองสำหรับ production ให้ใส่ใน `.env` ก่อน start:

```env
APP_KEY=base64:your-generated-key
```

ห้ามเปลี่ยน `APP_KEY` หลังระบบเริ่มใช้งานแล้ว เพราะจะทำให้ session และข้อมูลที่เข้ารหัสเดิมใช้ไม่ได้

> คำสั่งที่ถูกต้องคือ `up` ไม่ใช่ `-up`

ตรวจสอบสถานะ:

```bash
docker compose ps
docker compose logs --tail=100 app
curl http://127.0.0.1:8000/up
```

ผลลัพธ์ health check ที่ถูกต้องควรเป็น HTTP `200` และข้อความ `Application up`

### 4. ตรวจ database และ default user

สำหรับ production ให้รัน migration อย่างเดียว:

```bash
docker compose exec app php artisan migrate --force
```

หากต้องการสร้างบัญชี dev สำหรับเครื่องทดสอบเท่านั้น ให้ตั้งค่าใน `.env`:

```env
SEED_DEFAULT_USER=true
```

แล้ว recreate container:

```bash
docker compose up -d --build
```

บัญชีทดสอบ:

```text
Email: dev@localhost
Password: 12345678
Role: dev
```

ไม่ควรใช้บัญชีและ password นี้บน production จริง

## URLs

```text
Dashboard:  http://YOUR_SERVER_IP:8000/dashboard
Containers: http://YOUR_SERVER_IP:8000/docker-containers
Login:      http://YOUR_SERVER_IP:8000/login
```

## Development แบบไม่ต้อง rebuild ทุกครั้ง

เมื่อแก้ Vue/CSS/JS ให้ใช้:

Terminal 1:

```bash
php artisan serve
```

Terminal 2:

```bash
npm run dev
```

หรือ:

```bash
composer run dev
```

ใช้ `docker compose up -d --build` เมื่อแก้ `Dockerfile`, `docker-compose.yml`, PHP extensions หรือ dependencies

## แก้ MissingAppKeyException

ถ้าเจอ:

```text
No application encryption key has been specified.
```

ให้ตรวจว่า container ทำงานและ volume สำหรับ key ถูกสร้างแล้ว:

```bash
docker compose ps
docker volume ls | grep app-config
```

ถ้าเป็นระบบติดตั้งใหม่และต้องการสร้าง key เอง:

```bash
APP_KEY_VALUE="base64:$(openssl rand -base64 32)"
sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY_VALUE}|" .env
docker compose up -d --build
docker compose exec app php artisan config:clear
docker compose exec app php artisan config:cache
```

ถ้ามี `APP_KEY` แล้วแต่ยัง error ให้ตรวจว่า Compose โหลดไฟล์ `.env` ถูกต้อง และ recreate container:

```bash
docker compose config
docker compose up -d --force-recreate
docker compose logs --tail=100 app
```

## Docker socket

แอปเรียก Docker Engine API ผ่าน `/var/run/docker.sock` ไม่ได้เรียก shell command `docker ps` จากหน้าเว็บ โดย Compose mount socket ให้ container:

```yaml
- /var/run/docker.sock:/var/run/docker.sock:ro
```

บน Ubuntu ที่ใช้ rootless Docker ให้ตรวจ socket ที่ใช้งานจริง:

```bash
docker context inspect --format '{{.Endpoints.docker.Host}}'
```

ถ้าได้ค่าเช่น `unix:///run/user/1000/docker.sock` ให้ตั้งค่าใน `.env` เป็น path ที่ตัด `unix://` ออก:

```env
DOCKER_SOCKET_HOST=/run/user/1000/docker.sock
```

จากนั้น recreate:

```bash
docker compose up -d --build --force-recreate
```

ถ้าใช้ Docker แบบ rootful ค่าเริ่มต้น `/var/run/docker.sock` ใช้ได้ตามปกติ

ตรวจสอบว่า socket มีอยู่:

```bash
ls -l /var/run/docker.sock
docker compose exec app ls -l /var/run/docker.sock
```

## Update version ใหม่

```bash
git pull origin main
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

## Security notes

- ใช้ `APP_DEBUG=false` บน production
- ใช้ HTTPS และ reverse proxy เมื่อเปิดใช้งานจริง
- เก็บ `.env` ไว้บน server เท่านั้น
- อย่าเปิด Docker socket ให้ public
- เปลี่ยน password default และปิด `SEED_DEFAULT_USER` บน production
- อย่าเปลี่ยน `APP_KEY` ของระบบที่มีข้อมูลแล้ว

## License

This project is based on Laravel and is licensed under the MIT License.
