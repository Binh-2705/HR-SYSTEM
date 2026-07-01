# View Migration Map

Tai lieu nay doi chieu giua thu muc `views/` cua he cu va `laravel_app/resources/views/` cua ban Laravel.

## Tong quan

- He cu: 26 thu muc trong `views/`
- Laravel hien tai: 34 thu muc trong `resources/views/`
- Nguyen nhan chenh lech:
  - Nhiều module da duoc doi ten theo convention Laravel
  - Mot so module cu da duoc tach thanh nhieu khu vuc nho hon khi sang Laravel
  - Mot vai phan da bi tach thanh nhieu thu muc nho hon
  - Laravel co them mot so khu vuc moi nhu `services`, `resource_modules`, `auth`, `account`

## Route alias kieu cu

Laravel hien giu route English de tuong thich code hien tai, dong thoi da bo sung them alias gan voi he cu cho cac module chinh.

Vi du:

- `positions.*` dong thoi co alias `chucvu.*`
- `assignments.*` dong thoi co alias `phancong.*`
- `insurances.*` dong thoi co alias `baohiem.*`
- `leave-requests.*` dong thoi co alias `nghiphep.*`
- `salary-bands.*` dong thoi co alias `ngachluong.*`
- `salary-grades.*` dong thoi co alias `bacluong.*`
- `accounts.*` dong thoi co alias `taikhoan.*`
- `employee-profiles.*` co them alias `hosocanhan.*` cho cac man hinh chinh
- `contracts.*` co them alias `hopdong.*` cho CRUD va thao tac bo sung

Menu ben trai hien uu tien dung alias kieu cu de cau truc nhin gan he thong goc hon.

## Doi chieu `views` cu -> `resources/views` moi

| Views cu | Resources/views moi | Trang thai | Ghi chu |
| --- | --- | --- | --- |
| `auditlog` | `audit_logs` | Doi ten | Da co folder view rieng |
| `bacluong` | `salary_grades` | Doi ten | Da co folder view rieng |
| `baocao` | `reports` | Doi ten | Da tach view rieng |
| `baohiem` | `insurances` | Doi ten | Da co folder view rieng |
| `chamcong` | `attendance` | Doi ten | Da tach view rieng |
| `chatbot` | `chatbot` | Tuong ung truc tiep | Da tach view rieng |
| `chucvu` | `positions` | Doi ten | Da co folder view rieng |
| `dangnhap` | `auth` | Doi ten va tach | Gom login, forgot, reset, force password |
| `daotao` | `training` | Doi ten | Da tach view rieng |
| `errors` | `errors` | Tuong ung truc tiep | Da them cac trang 403, 404, 500 |
| `home` | `dashboard` | Doi ten | Da tach view rieng |
| `hopdong` | `contracts` | Doi ten va mo rong | Da co index, form va cac man hinh bo sung |
| `hosocanhan` | `employee_profiles` | Doi ten va mo rong | Da co index, form, show, review_requests |
| `khenthuong` | `reward_records` + `reward_types` | Tach thanh 2 module | Da co folder view rieng cho ca du lieu va danh muc |
| `layout` | `layouts` | Doi ten | Chua giu kieu include PHP cu, da doi sang Blade layout |
| `luong` | `payroll` | Doi ten | Da tach view rieng |
| `ngachluong` | `salary_bands` | Doi ten | Da co folder view rieng |
| `nghiphep` | `leave_requests` | Doi ten | Da co folder view rieng |
| `nhanvien` | `employees` | Doi ten | Da tach view rieng |
| `phancong` | `assignments` | Doi ten | Da co folder view rieng |
| `phanquyen` | `role_permissions` | Doi ten | Da tach view rieng |
| `phongban` | `departments` | Doi ten | Da tach view rieng |
| `search` | `search` | Tuong ung truc tiep | Da tach view rieng |
| `systemhealth` | `system_health` | Doi ten | Da tach view rieng |
| `taikhoan` | `accounts` + `account` + `auth` + `roles` + `features` + `account_roles` + `audit_logs` | Tach thanh nhieu khu vuc | CRUD, cai dat, auth, vai tro, chuc nang, gan vai tro, audit |
| `tuyendung` | `recruitment` | Doi ten | Da tach view rieng |

## Cac folder moi trong Laravel khong ton tai 1:1 ben he cu

| Resources/views moi | Nguon goc |
| --- | --- |
| `account` | Tach rieng phan cai dat tai khoan, session, doi ten dang nhap/mat khau |
| `accounts` | CRUD tai khoan duoc tach rieng khoi `account/settings` |
| `account_roles` | Tach rieng phan gan vai tro tai khoan |
| `assignments` | Tach rieng CRUD phan cong |
| `audit_logs` | Tach rieng khu vuc audit log |
| `auth` | Tach rieng phan xac thuc va khoi phuc mat khau |
| `contracts` | Man hinh bo sung cho module hop dong nhu gia han va lich su luong |
| `employee_profiles` | Man hinh bo sung cho ho so ca nhan nhu xem chi tiet va duyet yeu cau |
| `errors` | Trang loi chuan Laravel |
| `features` | Tach rieng phan danh muc chuc nang he thong |
| `insurances` | Tach rieng CRUD bao hiem |
| `layouts` | Blade layout thay cho `views/layout` include PHP cu |
| `leave_requests` | Tach rieng CRUD nghi phep |
| `positions` | Tach rieng CRUD chuc vu |
| `resource_modules` | View dung chung cho nhieu module CRUD do cau hinh dong |
| `reward_records` | Tach rieng CRUD khen thuong/ky luat |
| `reward_types` | Tach rieng danh muc loai khen thuong/ky luat |
| `roles` | Tach rieng danh muc vai tro |
| `salary_bands` | Tach rieng CRUD ngach luong |
| `salary_grades` | Tach rieng CRUD bac luong |
| `services` | Module service console moi trong ban Laravel |

## Vai tro cua `resource_modules`

`resource_modules` hien tai duoc giu lai nhu lop fallback va noi chua partial dung chung.

Controller `ResourceModuleController` se uu tien tim view rieng theo module truoc. Neu chua co, no moi quay ve `resource_modules`.

## Ket luan

Hien trang migration da gan hon rat nhieu voi mo hinh tach module thanh folder rieng.

No dang la mo hinh lai:

- Hầu het module da co folder Blade rieng
- `resource_modules` chi con dong vai tro fallback va partial dung chung
- Mot so khu vuc cu bi tach nho theo chuc nang khi sang Laravel

Phan chua doi ung 1:1 chu yeu la do Laravel tach them cac khu vuc moi nhu `auth`, `account`, `services` va tach `taikhoan`, `khenthuong` thanh nhieu module nho hon.