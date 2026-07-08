# AGENTS.md — Alwahab Poultry POS

Vanilla PHP/MySQL POS. No Composer, no build step, no test runner, no CI, no `.htaccess`.

## DB

- **Multi-client**: Codebase shared across deployments. Commented-out blocks in `database.php` for each client.
- **Local dev**: `root`/empty/`poultry_form`. **Gotcha**: `database/schema.sql` creates DB `poultry_shop` — you must change the `CREATE DATABASE` name or the local config to match.
- **Schema**: 11 InnoDB tables + seed data (`admin`/`admin123`, `cashier`/`cashier123`, chicken types, Walk-in Customer).
- PDO singleton `getDB()` (`ERRMODE_EXCEPTION`, `FETCH_ASSOC`, real prepared stmts). Timezone `Asia/Karachi` set in `database.php` and `header.php`. Currency PKR.

## Architecture

| File | Role |
|---|---|
| `login.php` | Plaintext compare `$password === $user['password_hash']`. No CSRF. |
| `index.php` | Redirects to dashboard or login. |
| `logout.php` | Destroys session, redirects login. |

**Protected pages** (all under `pages/`): require in order — `auth_check.php` (session, 1h timeout, redirects to `login.php?expired=1`), `database.php`, `functions.php`, set `$page_title`, `header.php`, content, `footer.php`.

## Conventions

| Convention | Detail |
|---|---|
| CSRF | 64-char hex in `$_SESSION`, `csrf_token()`/`verify_csrf()` |
| XSS input | `sanitize()` = `htmlspecialchars(strip_tags(trim()), ENT_QUOTES, 'UTF-8')` |
| XSS output | `htmlspecialchars()` |
| Money | `money($n)` → 2-dec with comma sep; `moneyRaw($n)` → no sep |
| Nav helpers | `navActive($page)`, `navActiveDir($dir)`, `isSectionActive($section)` — used in `sidebar.php` |
| DataTables | Class `datatable` → init in `sb-admin-custom.js` (pageLength:25, stateSave, auto `dom` layout) |
| Stock ledger | Types: `opening`, `purchase`, `sale`, `adjustment`. Stock = SUM(opening+purchase+adjustment) − SUM(sale) |
| Today's profit | `todayProfit()` = revenue − avg purchase cost × weight sold − expenses (simplified avg, not FIFO) |
| Roles | `isAdmin()`/`isCashier()` used only in `header.php` for badge color. **No server-side gating** — both roles access all pages. |

## CRUD pattern

- **Create/Update:** POST with hidden `action=create|update` + `csrf_token`; `verify_csrf()` guard; redirect after (PRG). Forms are either inline Bootstrap modals or standalone `create.php`.
- **Delete:** GET `?delete=ID` + class `btn-delete`; SweetAlert2 confirm from `sb-admin-custom.js`.
- **Flash:** `setFlash()` before redirect; auto-dismissed after 4s.
- **Date filters**: List pages (purchases, sales, etc.) use `from`/`to` GET params.

## POS (`pages/pos/`)

- `index.php` — frontend calculator (weight↔amount two-way) via `assets/js/pos.js`.
- `pos_ajax.php` — **own** `session_start()` (no `auth_check.php`). Auth + CSRF checked only on `save_sale`.
  - Actions: `get_rate` (rate + stock by type), `today_rates`, `search_customer`/`search_customers`, `save_sale`.
  - `save_sale`: DB transaction (sale + stock_ledger + optional payment). Resolves `customer_id=0` to `'Walk-in Customer'`.
- Stock check via `availableStock()` before sale (both server-side and client-side).
- Invoice format: `INV-YYYYMMDD-NNNN` (NNNN = `MAX(sales.id)+1` global). Print via `sales/invoice.php?id=N`. Weight shown in KG and **Man** (1 Man = 40 KG).
- **Chicken rates**: User enters rate per **Man** on the form; stored as `rate_per_kg` (÷40). Display always converts back (×40).

## Purchases

- DB transaction: inserts into `purchases` + `stock_ledger` in one go.
- Expense categories ENUM: `labour`, `transport`, `electricity`, `misc`.
- Payment methods ENUM: `cash`, `bank`, `credit`.

## Frontend deps (all CDN — `node_modules/` unused)

Bootstrap 5.3.3, jQuery 3.7.1, DataTables 1.13.7, SweetAlert2 11, Chart.js 4.4.1, Font Awesome 6.5.1.
Custom: `assets/css/sb-admin-custom.css` (`--primary: #059669`), `assets/js/pos.js`, `assets/js/sb-admin-custom.js`.

## Unused artifacts

- `sb-admin2/` — vendored SB Admin 2 template, not used by the app. Ignore.
- `package.json` — `bootstrap ^5.3.8` in `node_modules/`, no build step.

## Branding

"Ismail's Poultry Services" — login page, sidebar, print header, invoice. Address/phone in `header.php` and `invoice.php`.
