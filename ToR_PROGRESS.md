# ToR Progress Tracker

Last updated: 2026-03-15
Reference: `ToR.md`

## Module Status

- [x] 7. Authentication Module
- [x] 8. Shops Module
- [x] 9. Users Module
- [x] 10. Products Module
- [x] 11. Purchases Module
- [x] 12. Sales Module
- [x] 13. Expenses Module
- [x] 14. Debts Module
- [x] 15. Reports Module
- [x] 16. Currencies Module
- [x] 17. Settings Module

## Delivered Endpoints (API v1)

- Auth: `login`, `me`, `logout`, `refresh`
- Shops: CRUD
- Users: CRUD with role restrictions
- Products: CRUD
- Purchases: `index`, `show`, `store`
- Sales: `index`, `show`, `store`
- Expenses: CRUD
- Debts: `index`, `show`, `store`, `storeTransaction`
- Reports: `sales`, `expenses`, `profit`, `stock`
- Currencies: `index`, `show`, `update`
- Settings: `show`, `update`

## Current Role Matrix

- `super_admin`: full access to all modules, shops, users, currencies, settings, reports
- `owner`: full operational access in own shop + can create/manage only `seller` users in own shop
- `seller`: sales create/view, products view, own shop view, own profile view/update

## ToR Acceptance Criteria Tracking

- [x] All core modules implemented (sections 7-17)
- [x] Endpoints covered by automated tests (feature tests)
- [x] Authentication and authorization active (Sanctum + roles)
- [x] Tenant isolation implemented (`shop_id` scoped queries)
- [x] Full `shop_id` persistence for transaction entities (`sale_items`, `purchase_items`, `debt_transactions`)
- [x] Profit report formula implemented (`sales - cogs - expenses`)
- [x] Load testing completed (baseline performance scenario test)
- [x] Secure headers and token expiration policy enabled

## Infrastructure Status (ToR sections 19, 23, 24)

- [x] Performance baseline tests:
  - `tests/Feature/Api/V1/LoadProfileTest.php` (opt-in profile)
  - `tests/Feature/Api/V1/PerformanceThresholdTest.php` (response-time thresholds)
- [x] Backup strategy:
  - command: `php artisan app:db-backup --retention-days=30`
  - restore: `php artisan app:db-restore {backup-file} --force`
  - schedule: daily 02:00 in `routes/console.php`
  - retention: file pruning by age
- [x] Deployment baseline:
  - `Dockerfile`
  - `docker-compose.yml`
  - `docker/nginx/default.conf`
  - `.dockerignore`

## Web Admin Interface (Inertia)

- [x] Super Admin middleware-protected routes under `/admin/*`
- [x] Admin pages:
  - Dashboard
  - Shops management (status toggle)
  - Users listing
  - Currencies management (set default, update rate)
  - Reports summary
- [x] Sidebar navigation for super admin users

## Notes / Gaps

- Load test is implemented as `tests/Feature/Api/V1/LoadProfileTest.php` and is opt-in via env vars.
- Success/error envelopes are unified for `/api/*` (`success`, `message`, `data/errors`) via API middleware + exception renderers.
- Service/Repository architecture is implemented for transaction-heavy modules:
  - `Sales`, `Purchases`, `Debts`, `Products`, `Expenses`
- Redis caching is intentionally deferred for a later phase.
- `sales`, `purchases`, and `debts` currently do not expose full update/delete lifecycle endpoints.
