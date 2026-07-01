# Service Module API

Tai lieu nay danh cho frontend goi truc tiep endpoint theo tung module (khong dung API tong hop `/api/services/...` nua).

## 1. Auth

Gui token chung qua header:

```http
X-Service-Token: your-token
```

Hoac:

```http
Authorization: Bearer your-token
```

## 2. Pattern endpoint

```text
GET    /api/modules/{module}/meta
GET    /api/modules/{module}
GET    /api/modules/{module}/{id}
POST   /api/modules/{module}
PUT    /api/modules/{module}/{id}
DELETE /api/modules/{module}/{id}
GET    /api/modules/{module}/export
```

Body `POST` va `PUT` la JSON object.

## 3. Format response

Danh sach:

```json
{
  "ok": true,
  "module": "positions",
  "service": "hr",
  "resource": "positions",
  "connection": "hr_service",
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 50
  },
  "data": [
    {
      "MaNV": 1,
      "HoTen": "Nguyen Van A",
      "__resource_id": "1"
    }
  ]
}
```

Chi tiet:

```json
{
  "ok": true,
  "module": "positions",
  "service": "hr",
  "resource": "positions",
  "connection": "hr_service",
  "data": {
    "MaNV": 1,
    "HoTen": "Nguyen Van A",
    "__resource_id": "1"
  }
}
```

Loi:

```json
{
  "ok": false,
  "message": "Resource is read-only."
}
```

## 4. Record ID

- Single key: `15`
- Composite key: `7,2`
- Attendance summary view: `15,4,2026`

Frontend nen dung truong `__resource_id` tra ve tu API thay vi tu ghep ID thu cong.

## 5. Module catalog

### HR

Module:
- `positions`
- `assignments`
- `insurances`
- `leave-requests`
- `reward-types`
- `reward-records`
- `employee-profiles`
- `accounts`
- `roles`
- `features`
- `account-roles`
- `audit-logs`

### Payroll

Module:
- `contracts`
- `salary-grades`
- `salary-bands`

Thong tin module day duoc lay tu `config/laravel_resource_modules.php`.

## 6. Frontend example

```js
const token = 'your-token';

async function fetchModuleRows(module, page = 1) {
  const response = await fetch(`/api/modules/${encodeURIComponent(module)}?page=${page}&limit=20`, {
    headers: {
      'Accept': 'application/json',
      'X-Service-Token': token,
    },
  });

  if (!response.ok) {
    throw new Error(`API error: ${response.status}`);
  }

  return response.json();
}

async function updateModuleRow(module, resourceId, payload) {
  const response = await fetch(`/api/modules/${encodeURIComponent(module)}/${encodeURIComponent(resourceId)}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Service-Token': token,
    },
    body: JSON.stringify(payload),
  });

  return response.json();
}
```

## 7. Frontend rule nhanh

- Luon goi `GET /api/modules/{module}/meta` truoc neu can render form dong theo cot.
- Luon luu `__resource_id` trong state cua bang/list.
- Khong hien nut tao/sua/xoa cho resource read-only.
- Neu API tra `401`, kiem tra token.
- Neu API tra `404`, kiem tra module/id.
- Neu API tra `405`, resource dang bi khoa ghi.

## 8. Portable deployment (tach sang project khac)

Khi tung database/service duoc dua sang project rieng, app hien tai van goi duoc neu set URL theo service trong `.env`:

```env
HR_API_BASE_URL=http://hr-service.local/api
PAYROLL_API_BASE_URL=http://payroll-service.local/api
ATTENDANCE_API_BASE_URL=http://attendance-service.local/api
RECRUITMENT_API_BASE_URL=http://recruitment-service.local/api
TRAINING_API_BASE_URL=http://training-service.local/api
REPORTING_API_BASE_URL=http://reporting-service.local/api
CHATBOT_API_BASE_URL=http://chatbot-service.local/api
```

Neu de trong cac bien tren, he thong se fallback ve `INTERNAL_API_BASE_URL`.