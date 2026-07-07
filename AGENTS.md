# AGENTS.md — Alwahab Poultry POS

Vanilla PHP/MySQL POS. No Composer, no build step, no test runner, no CI.

## DB

- **Production** (`includes/database.php`): `atrmarke_alwahab` / `ATRsales123`, DB `atrmarke_alwahab`.
- **Local**: Import `database/schema.sql` — creates DB `poultry_shop` + 11 InnoDB tables + seed data. You must update `database.php` creds (`root`/empty/`poultry_shop`).
- PDO singleton: `getDB()` (`PDO::ERRMODE_EXCEPTION`, `FETCH_ASSOC`, real prepared stmts).
- Timezone `Asia/Karachi` set in `database.php` and `header.php`. Currency PKR (Rs.) — `money()` = `number_format($n, 2)`.

## Entry points

| File | Notes |
|---|---|
| `login.php` | Plaintext compare `$password === $user['password_hash']`. Seed: `admin`/`admin123`, `cashier`/`cashier123`. |
| `index.php` | Redirects to dashboard or login. |
| `logout.php` | Destroys session, redirects to login. |

## Protected page pattern (in order)

```php
require_once __DIR__ . '/../../includes/auth_check.php'; // starts session, 1h timeout, redirects login.php?expired=1
require_once __DIR__ . '/../../includes/database.php';   // getDB()
require_once __DIR__ . '/../../includes/functions.php';   // sanitize, money, csrf_token/verify_csrf, setFlash/flashMessage, generate_invoice_no, getCustomerBalance, getSupplierBalance, availableStock, todayProfit, todaySalesTotal, isAdmin/isCashier, navActive/navActiveDir/isSectionActive
// Set $page_title here
require_once __DIR__ . '/../../includes/header.php';       // <head>, topbar, sidebar.php, flash messages; exposes window.BASE_URL
// Page content
require_once __DIR__ . '/../../includes/footer.php';       // DataTables, SweetAlert2, sb-admin-custom.js
```

`auth_check.php` also requires `base_url.php` internally.

## CRUD

- **Create/Update:** POST with hidden `action=create|update` + `csrf_token`; `verify_csrf()` guard; redirect after (PRG).
- **Delete:** GET `?delete=ID` + class `btn-delete`; SweetAlert2 confirm from `sb-admin-custom.js`.
- **Flash:** `setFlash()` before redirect; auto-dismissed after 4s in `header.php`.

## POS (`pages/pos/`)

- `index.php` — frontend two-way calculator (weight↔amount) in `assets/js/pos.js`.
- `pos_ajax.php` — **own** `session_start()` (no `auth_check.php`). Auth + CSRF checked only on `save_sale`. JSON endpoint.
  - Actions: `get_rate` (rate + stock by type), `today_rates`, `search_customer`/`search_customers`, `save_sale`.
  - `save_sale`: DB transaction (sale + stock_ledger + optional payment). Resolves `customer_id=0` by querying name `'Walk-in Customer'`.
- Stock check via `availableStock()` before sale.
- Invoice format: `INV-YYYYMMDD-NNNN` (NNNN = `MAX(sales.id)+1` global, not per-day). Print via `sales/invoice.php?id=N`.

## Conventions

| Convention | Details |
|---|---|
| CSRF | 64-char hex in `$_SESSION` |
| XSS input | `sanitize()` = `htmlspecialchars(strip_tags(trim()), ENT_QUOTES, 'UTF-8')` |
| XSS output | `htmlspecialchars()` |
| Money | `money($n)` → 2-dec thousands sep; `moneyRaw($n)` → no sep |
| DataTables | Class `datatable` → init in `sb-admin-custom.js` (pageLength:25, stateSave) |
| Stock ledger | Types: `opening`, `purchase`, `sale`, `adjustment`. Stock = SUM(opening+purchase+adjustment) − SUM(sale) |
| Today's profit | `todayProfit()` = revenue − avg purchase cost × weight sold − expenses (simplified, not FIFO) |
| No server-side gating | `isAdmin()`/`isCashier()` used only in `header.php` for badge color. Both roles access all pages. |

## Frontend deps (all CDN)

Bootstrap 5.3.3, jQuery 3.7.1, DataTables 1.13.7, SweetAlert2 11, Chart.js 4.4.1, Font Awesome 6.5.1.
Custom CSS: `assets/css/sb-admin-custom.css` (`--primary: #059669`).
Custom JS: `assets/js/pos.js`, `assets/js/sb-admin-custom.js`.
`package.json` has `bootstrap ^5.3.8` in `node_modules/` but no build step — unused at runtime.

## Branding

App branded "Alwahab Poultry" (login page, print header). Store address in `header.php` inline CSS.
