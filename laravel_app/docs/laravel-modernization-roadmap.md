# Laravel Modernization Roadmap

This roadmap breaks the migration into safe, incremental milestones.

## 1) Architecture Standards

- [x] Route-level API write authorization guardrail test
- [x] FormRequest + API Resource introduced for core modules
- [ ] Move raw query hotspots to Eloquent models with relationships/scopes
- [ ] Introduce service interfaces for cross-service boundaries

Recommended sequence:
1. Attendance + Payroll query paths
2. Recruitment + Training query paths
3. Reports and dashboards

## 2) Security and Authorization

- [x] API token middleware baseline in place
- [x] Action-based write permission middleware on write endpoints
- [x] Centralized `Gate::define("permission")` and admin bypass
- [x] Rate limiting with configurable limits
- [x] CORS moved to environment-based configuration
- [ ] Introduce Sanctum-issued personal tokens for external API clients
- [ ] Replace remaining ad-hoc role checks with policies/gates

## 3) Async and Performance

- [x] Queue job for monthly payroll processing
- [x] Payroll completion event/listener for audit logging
- [x] Scheduler tasks for health checks and queue maintenance
- [ ] Queue heavy exports/reports/chatbot execution paths
- [ ] Redis cache for heavy lookups and paginated lists

## 4) Production Operations

- [x] Health check command (`php artisan app:health-check`)
- [x] Dedicated security and audit log channels
- [ ] Error reporting integration (Sentry/Bugsnag)
- [ ] Notification channels (mail/slack) for critical events
- [ ] Secret management strategy by environment

## 5) Quality and CI/CD

- [x] Feature tests for validation + write authorization
- [x] CI workflow for lint/static analysis/tests
- [x] Pint + Larastan configuration
- [ ] Database test matrix with seeded fixtures
- [ ] Optional Rector plan for controlled refactors

## Local commands

```bash
composer lint
composer analyse
composer test
php artisan schedule:work
php artisan queue:work
php artisan app:health-check
```
