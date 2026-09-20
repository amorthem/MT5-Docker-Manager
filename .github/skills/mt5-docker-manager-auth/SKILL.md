---
name: mt5-docker-manager-auth
description: "ใช้สำหรับออกแบบ พัฒนา review และแก้ปัญหาระบบ multi-role authentication และ authorization ของ MT5 Docker Manager ที่ใช้ Laravel 12, Laravel Jetstream, Inertia, Vue.js และรันด้วย Docker บน VPS ครอบคลุม roles, permissions, policies, gates, middleware, protected routes, Docker access, deployment hardening และ authorization tests"
argument-hint: "อธิบาย role, permission, workflow หรือปัญหา authorization ที่ต้องการจัดการ"
user-invocable: true
disable-model-invocation: false
---

# การควบคุมสิทธิ์ของ MT5 Docker Manager

ใช้ skill นี้กับงานที่เกี่ยวกับความปลอดภัยของระบบ authentication และ authorization ใน MT5 Docker Manager โดยถือว่า authentication คือการยืนยันตัวตน และ authorization คือการตัดสินใจฝั่ง server ว่าผู้ใช้ทำอะไรได้บ้าง Backend เป็นแหล่งอ้างอิงหลัก ส่วนการซ่อนเมนูใน Vue เป็นเพียง UX

## ขอบเขตและสมมติฐาน

- โปรเจคใช้ Laravel 12, Jetstream, Inertia และ Vue.js
- แอปพลิเคชันรันเป็น Docker containers และ deploy บน VPS โดยอาจใช้ `docker compose`
- role หลักคือ `user`, `support`, `admin` และ `dev` โดย `dev` มีสิทธิ์เต็ม, `admin` จัดการ `support` และ `user` ได้, `support` ใช้ HTTP `GET`, `POST` และ `PUT` ได้ และ `user` ใช้ได้เฉพาะ `GET`
- ระบบต้อง monitor logs ของทุก container และเลือกดู logs ของ container รายตัวได้
- dashboard ต้องแสดง CPU/RAM ทั้งค่าที่มีและค่าที่ใช้อยู่ของ VPS/host และ container ที่เลือก
- ต้องตรวจสอบชื่อ role, permission และขอบเขต resource จาก repository ก่อนแก้โค้ด
- ห้ามถือว่าการเรียก Docker ปลอดภัยเพียงเพราะผู้เรียก login แล้ว

## Workflow

### 1. ตรวจสอบ authorization model ปัจจุบัน

1. ตรวจ `composer.json`, `package.json`, `.env.example`, `Dockerfile`, `docker-compose.yml` หรือไฟล์ compose ที่ใช้งานจริง
2. ค้นหา `User` model, migrations, seeders, policies, gates, middleware aliases, route groups, controllers, Form Requests และ Inertia shared props
3. ค้นหา Jetstream/Inertia/Vue pages, layouts, navigation และ helper ฝั่ง client ที่เกี่ยวกับ role หรือ permission
4. ค้นหาจุดควบคุม Docker เช่น services, jobs, actions, Artisan commands, process execution, Docker SDK, compose commands, socket และ filesystem mounts
5. สรุป role และ permission จากโค้ดกับ schema หากยังไม่ชัดเจน ให้ถาม role matrix ก่อนสร้างกติกาใหม่
6. ตรวจว่ามี package จัดการ permission อยู่แล้วหรือไม่ ให้ใช้แนวทางเดิมและอย่าเพิ่มระบบ authorization ซ้ำโดยไม่มีเหตุผลชัดเจน

### 2. กำหนด access contract ก่อนแก้ไข

เขียนตารางสั้น ๆ สำหรับ feature ที่กำลังทำ:

| ผู้ใช้งาน | Resource/action | อนุญาตเมื่อ | ห้ามเมื่อ | สิ่งที่ต้อง audit |
|---|---|---|---|---|
| Role หรือ user | การทำงานที่ชัดเจน | เงื่อนไขที่ตรวจสอบได้ | เงื่อนไขที่ห้าม | event หรือ log |

สิทธิ์ตั้งต้นของระบบนี้:

| Role | สิทธิ์หลัก |
|---|---|
| `dev` | ทำได้ทุกอย่าง รวมถึงจัดการ role/permission และ operation ของ Docker |
| `admin` | เพิ่มและจัดการ `support`/`user` ได้ แต่ไม่ควรยกระดับตัวเองหรือจัดการ `dev` หากไม่ได้รับอนุญาตเป็นพิเศษ |
| `support` | เรียกใช้ endpoint ที่เป็น `GET`, `POST`, `PUT` ตาม permission ของ resource แต่ห้าม `DELETE` และการจัดการสิทธิ์ผู้ใช้ |
| `user` | อ่านข้อมูลผ่าน `GET` ได้อย่างเดียว ห้ามสร้าง แก้ไข ลบ หรือสั่ง operation ที่เปลี่ยนสถานะ |

อย่าใช้ HTTP method เป็น authorization เพียงอย่างเดียว เช่น `POST` ของ support ต้องมี permission ของ action นั้นด้วย และ `GET` ของ user ต้องกรอง resource ตาม scope ที่ได้รับอนุญาต

ยืนยันคำถามที่เกี่ยวข้อง:

- มี role ใดบ้าง และผู้ใช้หนึ่งคนมีหลาย role ได้หรือไม่
- `admin` เพิ่มได้เฉพาะ `support` และ `user` หรือมีข้อยกเว้นใดบ้าง
- `dev` เป็น role สูงสุดเพียง role เดียวหรือสามารถมีหลายบัญชี
- permission ผูกกับ role, user, tenant, server หรือหลายแบบร่วมกัน
- ใครมีสิทธิ์มอบ role หรือ permission
- แต่ละ role เข้าถึง MT5 instances, containers, volumes, networks, logs และ credentials ใดได้บ้าง
- action ใดอ่านได้อย่างเดียว และ action ใดเป็น operation ที่เสี่ยงหรือทำลายข้อมูล
- ต้อง audit, ย้อนกลับ, rate-limit หรือยืนยันซ้ำหรือไม่
- ผู้ไม่มีสิทธิ์ควรได้ `403`, redirect หรือรายการที่ถูกกรอง

ใช้หลัก least privilege และ deny by default ควรใช้ permission ที่สื่อ capability แทนการฝังชื่อ role ใน domain logic เมื่อเหมาะสม

### 3. บังคับสิทธิ์ที่ server boundary

1. สร้างหรือปรับ role และ permission ตาม pattern เดิมของ repository
2. เพิ่มหรือแก้ migrations และ seeders ให้ใช้ identifier ที่คงที่ มี unique constraints, indexes และรันซ้ำได้อย่างปลอดภัย
3. ใช้ policies สำหรับการตัดสินใจที่ผูกกับ resource และใช้ gates เฉพาะกติกาข้าม resource
4. ใช้ middleware กับ route group เมื่อเป็นกติกากว้าง แต่ต้องตรวจ object-level authorization เพิ่มด้วย
5. ใช้ Form Requests ตรวจ input และ authorization ตาม convention ของโปรเจค
6. ตรวจสิทธิ์ซ้ำใน controllers, actions, jobs และ services ที่อาจถูกเรียกนอก HTTP route
7. ก่อนเรียก Docker ต้องตรวจว่า target resource อยู่ใน scope ของผู้ใช้ ห้ามต่อ shell command จาก input ที่ไม่ไว้ใจ ให้ใช้ structured API, allowlist และ escaping ที่เหมาะสม
8. ใช้ response ให้ตรงขอบเขต: `401` สำหรับยังไม่ยืนยันตัวตน, `403` สำหรับ login แล้วแต่ไม่มีสิทธิ์ และ redirect เฉพาะ flow ที่จำเป็น
9. ห้ามเปิดเผยชื่อ container, path, credentials, hostname ภายใน หรือการมีอยู่ของ resource ผ่าน response ที่ไม่ได้รับอนุญาต

### 4. เชื่อม Jetstream, Inertia และ Vue อย่างปลอดภัย

1. แชร์ role และ permission ผ่าน Inertia shared props เท่าที่ UI จำเป็นต้องใช้
2. ถือว่า props และ client-side checks เป็น presentation logic เท่านั้น ไม่ใช่การบังคับสิทธิ์
3. ซ่อนหรือ disable เมนูและปุ่มที่ใช้ไม่ได้เพื่อ UX แต่ direct URL และ request ต้องยังถูก Laravel ป้องกัน
4. ใช้ route names และ ability names ให้ตรงกับ policy ฝั่ง backend
5. จัดการสถานะ `403` และ session หมดอายุใน Vue layout และ pages อย่างชัดเจน
6. ตรวจว่า server-side filtering ตรงกับ scope ที่ UI แสดง ห้ามโหลด resource ทั้งหมดแล้วค่อยซ่อนใน Vue

### 5. ทำระบบ monitor Docker logs

1. สร้าง service หรือ action กลางสำหรับดึง logs ของ container แทนการให้ controller เรียก shell กระจายหลายจุด
2. แสดงรายการ logs ของทุก container ตามสิทธิ์ของผู้ใช้ พร้อมชื่อสถานะและ timestamp ที่จำเป็น โดยไม่เปิดเผย environment variables, secrets หรือข้อมูลที่ไม่เกี่ยวข้อง
3. รองรับการเลือกดู logs ของ container รายตัวด้วย container identifier ที่ตรวจสอบกับรายการ container จริง ห้ามรับชื่อ command หรือ path จากผู้ใช้ไปต่อเป็น shell command
4. กำหนด query parameters ที่ปลอดภัย เช่น `tail`, `since`, `until`, `follow` และจำกัดจำนวนบรรทัด ระยะเวลา และขนาด response
5. สำหรับการดูแบบ live ให้ใช้ streaming/SSE/WebSocket ตาม infrastructure ที่มี พร้อมยกเลิก stream เมื่อออกจากหน้าและจำกัดจำนวน connection ต่อผู้ใช้
6. ให้ `user` ดู logs ได้เฉพาะ container ที่อยู่ใน scope และห้ามใช้ log endpoint เพื่อควบคุม container
7. ให้ `support` และ `admin` เข้าถึง logs ตาม permission ที่กำหนด ส่วน `dev` เข้าถึงได้ทั้งหมด
8. ป้องกัน log injection ใน UI ด้วยการแสดงเป็น text และกำหนด retention, redaction และ audit ตามความเหมาะสม

### 6. ทำ dashboard CPU/RAM ของ host และ container

1. แยก metric ระหว่างทรัพยากรของ VPS/host กับทรัพยากรของ container ที่เลือกอย่างชัดเจน
2. แสดงค่าที่มี (total/limit) และค่าที่ใช้อยู่ (used/usage) พร้อมหน่วย, timestamp และสถานะว่าข้อมูลล่าสุดเมื่อใด
3. ใช้แหล่งข้อมูลที่เหมาะสม เช่น Docker stats API หรือ metrics service ที่มีอยู่ ห้ามอ่านข้อมูลจาก client แล้วถือเป็นค่าจริง
4. จำกัด container identifier ให้เป็น resource ที่ผู้ใช้มีสิทธิ์ดู และตรวจ authorization ทุกครั้งที่เปลี่ยน container
5. ป้องกัน polling ถี่เกินไปด้วย interval ขั้นต่ำ, rate limit, caching หรือ aggregation ตามความเหมาะสม
6. จัดการกรณี container หยุด, metrics unavailable, Docker daemon timeout และข้อมูล stale โดยไม่ทำให้ dashboard แสดงค่าปัจจุบันปลอม
7. ไม่ส่ง Docker socket, host filesystem path, environment variables หรือ credential ไปยัง Vue; frontend รับเฉพาะข้อมูล metrics ที่จำเป็น
8. ทดสอบว่าค่า host และ container ไม่ปะปนกัน และผู้ใช้ไม่สามารถเปลี่ยน ID เพื่อดู metrics ของ container นอก scope

### 7. ตรวจ Docker และการ deploy บน VPS

1. ตรวจ `docker compose config`, service dependencies, networks, exposed ports, healthchecks, volumes และ user ที่ container ใช้รัน
2. ตรวจ `.env`, secrets, session/cookie, HTTPS, trusted proxies, CORS และ CSRF ให้เหมาะกับ reverse proxy และ domain จริง
3. ตรวจสิทธิ์ Docker socket หรือ Docker API อย่า mount socket ให้ container หากไม่จำเป็น และจำกัด container privileges ให้ต่ำที่สุด
4. ตรวจ mounted secrets, filesystem permissions, bind mounts และไม่ให้ Laravel container เข้าถึง path เกินขอบเขต
5. ให้ queues, scheduled jobs, workers และ Artisan commands ใช้ authorization context เดียวกับ HTTP requests โดยส่ง actor อย่างชัดเจนเมื่อจำเป็น
6. ตรวจว่า migrations และ seeders รันผ่าน container ได้โดยไม่ใช้ destructive reset และลำดับ cache (`config`, `route`, `view`, permission) ไม่ทำให้กติกาเก่าค้าง
7. ห้ามใส่ secret, token หรือ production credential ใน source code, logs, Vue props, screenshots หรือ test fixtures
8. บันทึกขั้นตอน rollback สำหรับการเปลี่ยน role/permission ที่อาจทำให้ administrator ถูก lock out

### 8. ทดสอบ authorization contract

ต้องมี focused tests ก่อนสรุปงาน:

- ผู้ที่ยังไม่ login เข้า protected page และ endpoint ไม่ได้
- role หรือ permission ที่อนุญาตทำ action ได้จริง
- role ใกล้เคียงที่ขาด permission ได้ `403`
- ผู้ใช้ข้ามไปยัง MT5 resource ของ user หรือ scope อื่นด้วย ID, route parameter หรือ request body ไม่ได้
- role แบบ read-only สั่ง Docker operation ที่ทำลายหรือเปลี่ยนสถานะไม่ได้
- direct HTTP request ยังปลอดภัยแม้ bypass ปุ่มและการตรวจใน Vue
- `user` เรียก endpoint ที่ไม่ใช่ `GET` ไม่ได้ และ `support` เรียก `DELETE` หรือจัดการ role ไม่ได้
- การมอบ role และ permission ถูกจำกัดและ audit ตามข้อกำหนด
- jobs, commands และ retry paths รักษาขอบเขตสิทธิ์เดิม
- UI ซ่อน action ที่ใช้ไม่ได้และจัดการ server-side `403` ได้
- ผู้มีสิทธิ์เห็น logs ของทุก container ตาม scope และเลือกดู container รายตัวได้เท่านั้น
- log stream จำกัดขนาด/เวลา/connection และหยุดเมื่อ client ยกเลิก
- dashboard แสดง host และ container CPU/RAM ถูกตัว พร้อมจัดการ stopped, timeout และ stale metrics
- ผู้ใช้เปลี่ยน container ID เพื่ออ่าน logs หรือ metrics นอก scope ไม่ได้

เริ่มจากคำสั่ง PHPUnit/Pest และ frontend test ที่แคบที่สุด จากนั้นจึงรัน test, lint และ build ทั้งโปรเจคเมื่อทำได้ รายงานคำสั่งที่รันไม่ได้พร้อมเหตุผล

## Review Checklist

- [ ] กำหนด access matrix ของงานไว้อย่างชัดเจน
- [ ] บังคับ authorization ฝั่ง server ที่ทุก entry point
- [ ] policies, middleware, requests, jobs และ services ใช้กติกาสอดคล้องกัน
- [ ] การตรวจใน Vue เป็นเพียง UX layer
- [ ] ตรวจ ownership และ resource scope ฝั่ง server
- [ ] Docker commands และ target identifiers ถูกจำกัดอย่างปลอดภัย
- [ ] role matrix `user/support/admin/dev` ถูกบังคับตรงตามข้อกำหนด
- [ ] logs ของทุก container และ logs ราย container มี authorization, redaction และ limits
- [ ] dashboard แยก host กับ container CPU/RAM และระบุ freshness ของ metrics
- [ ] response ที่ไม่ได้รับอนุญาตไม่เปิดเผยข้อมูลสำคัญ
- [ ] ตรวจ session, CSRF, proxy, cache, queue และ Docker privileges บน VPS แล้ว
- [ ] มี positive, negative, isolation และ regression tests
- [ ] migrations, seeders, Docker deployment และ rollback behavior ถูกบันทึก

## Completion Report

สรุปผลเป็นภาษาไทยดังนี้:

1. role/permission contract ที่ implement หรือ review
2. จุด enforcement ฝั่ง backend และพฤติกรรมฝั่ง frontend
3. ผลกระทบด้านความปลอดภัยของ Docker และ VPS
4. tests และ commands ที่รัน รวมถึงสิ่งที่ล้มเหลวหรือรันไม่ได้
5. assumptions ที่ยังต้องให้ฝ่าย product หรือ operations ยืนยัน
