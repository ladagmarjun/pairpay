# Deploying to a Namecheap subdomain

Namecheap shared hosting is cPanel with no root, no Node, and no supervisor.
That shapes everything below: **GitHub Actions builds, the server only serves.**
Composer and npm never run on the host.

    push to master → Actions: composer --no-dev + vite build → FTPS upload → deploy hook

## One-time: cPanel

### 1. Create the subdomain with the right document root

This is the step people get wrong, and it is the one that matters. Laravel must
serve from `public/`, never from the app root — otherwise `.env`, `storage/` and
`vendor/` are all downloadable over HTTP.

cPanel → **Domains** → **Create A New Domain**:

| Field | Value |
| --- | --- |
| Domain | `app.yourdomain.com` |
| Document Root | `/home/USER/app.yourdomain.com/public` |

Untick "Share document root".

**cPanel's default is wrong for Laravel.** Left alone it sets the document root
to the subdomain folder itself — `/home/USER/app.yourdomain.com` — and if the
app is deployed there, then `config/`, `vendor/`, `database/` and eventually
`.env` are all served over HTTP. Append `/public` so the app root sits one level
above the web root.

You can tell which you have by looking in the folder: cPanel creates
`.well-known` and `cgi-bin` inside whatever it considers the document root.

To fix an existing subdomain, cPanel → **Domains** → the domain → edit
**Document Root** and append `/public`. Nothing needs re-uploading.

Verify afterwards that `https://app.yourdomain.com/.env` returns 404, not a
file, and that `/up` responds.

### 2. PHP version

cPanel → **Select PHP Version** → **8.3** or newer (`composer.json` requires
`^8.3`). Enable these extensions if they are not already on: `bcmath`, `ctype`,
`curl`, `dom`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`,
`xml`, `zip`. `curl` and `openssl` are not optional here — every Firebase call
goes through them.

### 3. Database

cPanel → **MySQL Databases**. Create a database and a user, and grant **All
Privileges**. Note the names: cPanel prefixes both with your account, so
`pairpay` becomes `cpaneluser_pairpay`.

SQLite also works and needs no setup, but the file must live outside
`public/` and survives deploys only because the workflow excludes `*.sqlite`.
MySQL is the safer default on shared hosting.

### 4. `.env` on the server

Create `/home/USER/app.yourdomain.com/.env` through **File Manager**. It is deliberately
never deployed — CI would overwrite it, and it holds every secret you have.

```dotenv
APP_NAME=PairPay
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://app.yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cpaneluser_pairpay
DB_USERNAME=cpaneluser_pairpay
DB_PASSWORD=the-password-you-set

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

CACHE_STORE=database
QUEUE_CONNECTION=database

# Firebase holds every password. Without these, nobody can log in at all —
# there is no other credential store. Console → Project settings → General.
FIREBASE_PROJECT_ID=
FIREBASE_API_KEY=

# Google sign-in. Add https://app.yourdomain.com/auth/google/callback as an
# Authorized redirect URI in Google Cloud Console, or this silently fails.
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"

MAIL_MAILER=smtp
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="noreply@yourdomain.com"

# Optional — see "Finishing the deploy" below.
DEPLOY_HOOK_TOKEN=
```

`APP_DEBUG=false` is not a style preference. With it on, any error page prints
your `.env` — including the database password — to whoever triggered it.

`SESSION_SECURE_COOKIE=true` matters more here than in most apps: the session
carries the Firebase refresh token, which is a bearer credential.

`APP_KEY` is filled in on the first deploy. Sessions are encrypted with it, so
**changing it later logs everybody out** — set it once and leave it.

### 5. HTTPS

cPanel → **SSL/TLS Status** → select the subdomain → **Run AutoSSL**. Wait for
the certificate before setting `SESSION_SECURE_COOKIE=true`, or you will lock
yourself out of your own login form: the cookie will not be sent over plain
HTTP, so the session never sticks.

## One-time: GitHub

**Settings → Secrets and variables → Actions**, under an environment named
`production`:

| Secret | Value |
| --- | --- |
| `FTP_SERVER` | `ftp.yourdomain.com` |
| `FTP_USERNAME` | the full FTP user, usually `deploy@yourdomain.com` |
| `FTP_PASSWORD` | its password |
| `FTP_SERVER_DIR` | `/app.yourdomain.com/` — relative to the FTP user's home |
| `DEPLOY_HOOK_URL` | `https://app.yourdomain.com/deploy-hook` (optional) |
| `DEPLOY_HOOK_TOKEN` | the same value as the server's (optional) |

Create a **dedicated FTP account** (cPanel → FTP Accounts) rooted at
`/home/USER/app.yourdomain.com` rather than reusing your cPanel login. If the credential
leaks, it reaches one directory instead of your whole account.

`FTP_SERVER_DIR` is relative to that FTP user's home. If the account is rooted
at the app directory, `/` is correct; if it is rooted at `/home/USER`, use
`/app.yourdomain.com/`. Getting this wrong uploads the app one level off, which shows up as
a blank page or a directory listing.

## Finishing the deploy

Files land, but migrations still need running and caches rebuilding. Pick one:

**SSH** (Stellar Plus and above) — the clean option:

```bash
cd ~/app.yourdomain.com && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan storage:link
```

**The deploy hook** — for plans without SSH. Set `DEPLOY_HOOK_TOKEN` in both the
server `.env` and GitHub secrets, and the workflow calls it after uploading.
Generate something long and random:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

It runs migrate and the cache commands, nothing else. Leave the token unset and
the route 404s — including for you, so if you are not using it, unset it.

**cPanel Terminal** — same commands as the SSH option, if your plan has it.

Note that `config:cache` is deliberately *not* run in CI. Caching config on the
build runner would freeze that runner's empty `.env` into the artefact and
override the real one on your server.

## After the first deploy

1. `php artisan key:generate --force` (or paste a generated key into `.env`)
2. Confirm `storage/` and `bootstrap/cache/` are `755`, and writable
3. Visit `https://app.yourdomain.com/up` — Laravel's health check
4. Check `https://app.yourdomain.com/.env` returns **404**
5. Register an account and confirm the verification email arrives

If you see a 500 with no detail, that is `APP_DEBUG=false` doing its job. The
real error is in `storage/logs/laravel.log`.

## Things that will bite you

**`certificate common name doesn't match requested host name`.** Namecheap
issues the FTP certificate for the *server* hostname, not your domain. Set
`FTP_SERVER` to the server hostname — cPanel → **General Information** →
**Current Server**, something like `server215.web-hosting.com`. That is the
right fix, and keeps verification fully on.

Only if that still mismatches, add a repository **variable** (not a secret)
`FTP_CHECK_HOSTNAME` = `no`. That relaxes the name check alone; the certificate
must still be validly issued by a trusted CA, and the transfer stays encrypted.
There is deliberately no switch for turning verification off altogether.

**"Re-run jobs" does not pick up workflow fixes.** Re-running a failed run
replays the *original commit*, workflow file included — so a fix you just pushed
is not in it. After changing anything under `.github/workflows/`, start a fresh
run: **Actions → deploy → Run workflow**, or push a new commit.

**Both workflows are keyed to `master`.** If you ever rename the default branch,
change `branches:` in `tests.yml` and `deploy.yml` together — `deploy` is
chained to `tests` by name, so a mismatch silently means neither ever fires.

**Stale files.** The upload has no `--delete`, deliberately — the alternative
risks deleting `.env` or `storage/` if an exclude pattern is ever wrong. Removed
packages therefore linger in `vendor/`. Once in a while, delete `vendor/` on the
server and let the next deploy repopulate it.

**Scheduled tasks.** Nothing currently needs the scheduler. If that changes, add
a cPanel cron job at one-minute intervals:
`/usr/local/bin/php /home/USER/app.yourdomain.com/artisan schedule:run >/dev/null 2>&1`

**Queues.** `QUEUE_CONNECTION=database` with no worker means jobs queue and never
run. Nothing queues work today; if that changes, run
`php artisan queue:work --stop-when-empty` from cron rather than expecting a
daemon — shared hosting kills long-running processes.

**Firebase.** Nothing about it is domain-restricted, because every call is
server-to-server from PHP. The API key needs no referrer allowlist — and if you
ever add one in Google Cloud Console, server calls break, since a server request
sends no referrer.

**Google sign-in.** Unlike Firebase, this *is* domain-bound. The production
callback URL has to be added to the Google Cloud OAuth client separately from
your local one.
