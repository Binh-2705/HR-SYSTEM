# Python Chatbot Service

This service powers the HRM chatbot and exposes a local HTTP API for the PHP app.

## 1) Setup

1. Create a virtual environment.
2. Install dependencies:
   pip install -r requirements.txt
3. Copy .env.example to .env and set values.

## 2) Run

uvicorn app:app --host 127.0.0.1 --port 8001 --reload

## 3) Endpoints

- GET /health
- POST /chat

## 4) Notes

- Service is read-only by default.
- It can answer normal chat and query safe HR metrics.
- Keep this service on local network or behind gateway.

## 5) Current Advanced Intents

- Total employees
- Pending leave approvals
- Leave status summary
- Contracts expiring in next 30 days
- Active assignment distribution by departments
- Employee lookup by name or ID (`tim nhan vien <keyword>`)

## 6) Safety Behavior

- If a prompt looks like write/delete/update, service returns an action plan instead of executing data changes.
- Tool responses are permission-aware (based on permissions passed from PHP session).

## 7) Shared Secret

If `APP_SHARED_SECRET` is set:

- PHP app must send header `X-App-Secret`.
- Python service validates the same secret before processing `/chat`.
