# PepperzHub — deployment reference

Template only. **Never commit real secrets to this file** — it records what each
value has to be, not what it is.

This app has not been deployed yet. Everything below describes a first deploy to
a single VPS running the monolith, which is the architecture the app is built
for (see `AGENTS.md` §2).

---

## 1. Required `.env` values

Start from `.env.example` and change everything in this table. The defaults there
are development defaults; several of them are actively unsafe in production.

### Must change

| Key | Production value | Why it matters |
|---|---|---|
| `APP_ENV` | `production` | Gates the demo-catalogue seeder (§4) and switches Laravel's own error handling. |
| `APP_KEY` | Generated once by `php artisan key:generate` | Signs sessions and cookies. **Generating a new one on an existing deploy invalidates every session and any encrypted value.** Generate once, then back it up. |
| `APP_DEBUG` | `false` | `true` renders stack traces, config values and connection strings to whoever triggers an error. |
| `APP_URL` | The exact public origin, `https://` included, no trailing slash | The `public` disk builds product-image and payment-QR URLs from it. Wrong value ⇒ every image 404s. |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Per environment | `DB_PASSWORD` is blank in the example. It must not be blank in production. |
| `MAIL_*` | A real transport | See "Mail" below — this one has a real consequence. |
| `MAIL_FROM_ADDRESS` | A real sending address on your domain | Placeholder in the example. |

### Should review

| Key | Note |
|---|---|
| `DB_CONNECTION` | `mysql`. The example no longer defaults to sqlite. |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | All `database`. No Redis or Memcached is required; the `REDIS_*` and `MEMCACHED_*` blocks are unused. |
| `FILESYSTEM_DISK` | `local`. Both disks used by the app (`local`, `public`) are defined explicitly, so this default is only a fallback. |
| `AWS_*` | Unused — no disk targets S3. Left in place in case object storage is added later. |
| `LOG_LEVEL` | Consider `warning` or `error` in production; `debug` is noisy and can log more than you want retained. |

### Mail — read before deploying

`MAIL_MAILER=log` writes mail to `storage/logs/` instead of sending it.

Email verification **is enforced**: `App\Models\User` implements
`MustVerifyEmail`, and every `admin.*` route sits behind `['auth', 'verified']`.
The existing operator account is grandfathered by migration
`2026_09_02_000001_backfill_email_verified_at_for_existing_users`, so it is
unaffected. But **any account created after that migration runs will be unable
to verify itself while `MAIL_MAILER=log`**, and will be stuck on
`/email/verify` with no link to click. Configure a real transport before adding
a second operator, or mark the new account verified by hand.

---

## 2. Setup command order

`composer setup` runs the first five of these. It is safe to run by hand instead.

```bash
composer install --no-dev --optimize-autoloader   # 1. dependencies
cp .env.example .env                              # 2. then edit it — see §1
php artisan key:generate                          # 3. once, ever; back it up
php artisan migrate --force                       # 4. schema + the verification backfill
php artisan storage:link                          # 5. REQUIRED — see below
npm ci && npm run build                           # 6. compiled assets
```

**Order matters in two places.** `key:generate` must precede anything that
writes encrypted data. `storage:link` must run before the app serves its first
request: it symlinks `public/storage` → `storage/app/public`, which is where
product images and payment-method QR codes live. Without it those files exist on
disk but every URL to them 404s. It was missing from `composer setup` until now.

`storage:link` is idempotent — re-running it on an existing deploy is a no-op.
It does **not** touch `storage/app/private`, which holds payment proofs and is
deliberately not web-reachable (§5).

### Caching, on a stable deploy

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Re-run all three after any `.env` or route change — cached config ignores `.env`
entirely, which is a confusing way to spend an afternoon.

### Permissions

`storage/` and `bootstrap/cache/` must be writable by the web server user.

---

## 3. Where files live

| Path | Disk | Web-reachable | Contents |
|---|---|---|---|
| `storage/app/public` | `public` | Yes, via the `storage:link` symlink | Product images, payment-method QR codes |
| `storage/app/private` | `local` | **No** | Customers' payment proofs |

Both are outside the repository and are **not** in version control. A deploy that
replaces the directory wholesale loses every uploaded image and every payment
proof. Back `storage/app` up alongside the database, and treat the two as one
unit — an order row whose proof file is missing cannot be verified.

---

## 4. Seeding

Payment methods, couriers, and shipping regions are bootstrap data only. After
their initial creation they are owned by Admin, not deployment automation.

### First production or staging bootstrap

On a fresh, empty database, run migrations and then the explicit reference-data
bootstrap once:

```bash
php artisan migrate --force
php artisan db:seed --class=ReferenceDataBootstrapSeeder --force
```

`ReferenceDataBootstrapSeeder` creates the payment method, courier, and delivery
options Checkout requires. After it runs, review and configure all payment
methods, couriers, and regions through Admin before accepting orders.

This seeder is intentionally not called by `DatabaseSeeder`. Its child seeders
use `firstOrCreate` keyed by mutable names. They do not overwrite a row whose
name still matches, but rerunning them after an admin renames or deletes a
method, courier, or region can recreate the original active default.

### Subsequent deployments

Run migrations only:

```bash
php artisan migrate --force
```

Do **not** include either of these commands in normal deployment automation:

```bash
php artisan db:seed --force
php artisan migrate --seed
```

The default `DatabaseSeeder` contains only the non-production demo catalogue;
it contains no production or staging reference data. `CatalogSeeder` is not
re-runnable and remains development-only.

**No user is seeded.** There is no self-registration — `Features::registration()`
is disabled in `config/fortify.php` — so accounts are provisioned by hand:

```bash
php artisan tinker
>>> App\Models\User::create([
...   'name' => 'Operator',
...   'email' => 'you@example.com',
...   'password' => Illuminate\Support\Facades\Hash::make('a long unique password'),
... ])->forceFill(['email_verified_at' => now()])->save();
```

`email_verified_at` must be set explicitly, because creating a user this way
fires no `Registered` event and therefore sends no verification email. See the
Mail note in §1.

---

## 5. Security notes carried into production

These are decisions already made in the codebase. Preserve them.

- **Self-registration is off.** Admin routes are gated on `['auth', 'verified']`
  with no roles layer, so any account that could register and verify itself
  would be indistinguishable from the operator — full access to orders and
  customers' bank receipts. Re-enabling `Features::registration()` requires a
  real authorisation layer first.
- **The private disk is not served over HTTP.** `config/filesystems.php` sets
  `'serve' => false` on the `local` disk. Setting it to `true` registers an
  unauthenticated `GET` **and** `PUT` at `/storage/{path}` over the disk holding
  every payment proof, gated only by a signed URL — no session, no auth, no
  CSRF. Nothing in the app uses it; the admin proof route streams those files
  through its own authenticated controller action instead.
  `tests/Feature/PrivateDiskNotServedTest.php` guards this.
- **`APP_DEBUG=false`.** Payment details, database credentials and order data all
  surface in a Laravel debug page.
- **Back up `APP_KEY`.** Losing it invalidates every session; regenerating it on
  a live deploy logs everyone out.

---

## 6. Before the first real order

Two pieces of seeded reference data are placeholders and will be shown to paying
customers exactly as written:

- The seeded payment method is **GOtyme Bank, account `0012 3456 7890`,
  "PepperzzHub Trading"** — a placeholder account number, and no QR image.
- The seeded courier rates are the original hardcoded ones (J&T Express;
  Luzon & Visayas ₱150, Mindanao Small ₱100, Mindanao Large ₱200).

Both are editable in the admin at **Payments** and **Shipping** — no redeploy
needed. Correct them before taking a real order, or a customer will transfer
money to an account that does not exist.
