# Laravel Service Console

Laravel app nay dong vai tro web app chinh, web console quan tri du lieu, va API tach rieng theo tung service/database.

## Kien truc

- Root app: Laravel trong `laravel_app`
- Dedicated API: `/api/hr/...`, `/api/payroll/...`, `/api/attendance/...`, `/api/recruitment/...`, `/api/training/...`, `/api/reports/...`
- Web console: `/services`

## Service da map

- `hr`: nhan su, phong ban, tai khoan, ho so, chuc vu, phan cong, bao hiem, nghi phep, khen thuong/ky luat, vai tro, quyen, session, reset token
- `payroll`: bang luong, bac luong, ngach luong, hop dong, lich su luong
- `attendance`: cham cong, cau hinh cham cong, tong hop cong theo view
- `recruitment`: ung vien, dot tuyen dung, ho so ung tuyen, lich phong van, danh gia phong van
- `training`: khoa dao tao, tham gia dao taoeeeeee
- `reporting`: bao cao
- `chatbot`: sessions, action drafts, messages

## Xac thuc API

Dat `SERVICE_GATEWAY_TOKEN` trong `.env` va gui mot trong hai header sau:

- `X-Service-Token: your-token`
- `Authorization: Bearer your-token`

## Cau hinh tach API theo tung service

De khi chuyen database/service sang project khac ma khong phai viet lai, hay cau hinh URL rieng cho moi service trong `.env`:

```env
INTERNAL_API_BASE_URL=http://localhost:8080/du_an2/api

HR_API_BASE_URL=http://hr-service.local/api
PAYROLL_API_BASE_URL=http://payroll-service.local/api
ATTENDANCE_API_BASE_URL=http://attendance-service.local/api
RECRUITMENT_API_BASE_URL=http://recruitment-service.local/api
TRAINING_API_BASE_URL=http://training-service.local/api
REPORTING_API_BASE_URL=http://reporting-service.local/api
CHATBOT_API_BASE_URL=http://chatbot-service.local/api
```

Ghi chu:

- Neu bien `*_API_BASE_URL` de trong, he thong se fallback ve `INTERNAL_API_BASE_URL`.
- `InternalApiClient` da ho tro map theo service, nen khi doi URL service ban chi can sua `.env`, khong can sua lai controller/service nghiep vu.
- Cac service ben ngoai can giu dung contract endpoint dang duoc goi (`/api/biz/...`, `/api/modules/...`, hoac endpoint dedicated tuong ung).

## Endpoint chinh

- `GET /api/hr/employees`: danh sach nhan vien
- `GET /api/hr/departments`: danh sach phong ban
- `GET /api/attendance`: danh sach cham cong
- `GET /api/payroll`: danh sach bang luong
- `GET /api/recruitment/campaigns`: danh sach dot tuyen dung
- `GET /api/training/courses`: danh sach khoa dao tao
- `GET /api/reports`: danh sach bao cao

Chi tiet frontend xem tai [docs/service-alias-api.md](docs/service-alias-api.md).

## Dinh danh ban ghi

- Single key: dung truc tiep gia tri khoa chinh, vi du `GET /api/hr/employees/15`
- Composite key: ghep bang dau phay theo dung thu tu khoa trong registry, vi du `GET /api/hr/account-roles/7,2`
- Read-only resource: hien tai `attendance-summaries` chi ho tro `GET`

## Web console

- `GET /services`: xem tat ca service/resource da map
- `GET /services/{service}/{resource}`: xem du lieu co phan trang qua service registry
- `GET /services/{service}/{resource}/create`: tao ban ghi cho resource cho phep ghi
- `GET /services/{service}/{resource}/{id}/edit`: sua ban ghi, co ho tro composite key

## Kiem tra

Chay:

```bash
php artisan route:list
php artisan test
```
