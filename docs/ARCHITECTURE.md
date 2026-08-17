# PairPay — Architecture

PairPay is a Laravel + Inertia + React application. It is the web port of the
Flutter app in `C:\projects\pairpay` (package `moneymate`), which was built with
Clean Architecture in a feature-first folder structure.

The layering survived the port; the mechanics changed to match the platform.

```
Flutter (mobile)                      Laravel + Inertia (web)
─────────────────────────────────     ─────────────────────────────────────────
presentation  Riverpod + widgets  →   React pages/components + Inertia props
domain        entities, contracts  →   Eloquent models, PHP enums, value objects
data          Drift / Firestore    →   Migrations + MySQL/SQLite via Eloquent
```

Dependencies still point inward: the React layer never knows how a row is
stored, and the enums that define a category's label, color, and icon are
declared once, in PHP, and shipped to the client as props.

## What each layer became

| Flutter                                       | Laravel / React                                                                |
| --------------------------------------------- | ------------------------------------------------------------------------------ |
| `AppUser` entity + Firebase Auth              | `App\Models\User` (profile only) — Firebase still holds the credentials         |
| `signInWithGoogle()` (Firebase + google_sign_in) | Laravel Socialite → `GoogleAuthController` + `FindOrCreateSocialUser`        |
| `signInWithEmailAndPassword()` (Firebase)     | Fortify's pipeline → `App\Actions\Fortify\AuthenticateUser` → Firebase          |
| `signInWithApple()`                           | not ported                                                                     |
| `Expense` entity                              | `App\Models\Expense`                                                           |
| `ExpenseCategory` / `PaymentMethod` enums     | `App\Enums\ExpenseCategory` / `App\Enums\PaymentMethod` (label + color + icon)  |
| `ExpenseFilter` / `MonthlySummary` value types | `App\Support\ExpenseFilter` / `App\Support\MonthlySummary`                      |
| `ExpenseRepository` contract + Drift impl     | `Expense::scopeFilter()` / `scopeForMonth()` / `scopeNewestFirst()`             |
| `ExpenseController` (Riverpod notifier)       | `App\Actions\Expenses\{Create,Update,Delete}Expense`                            |
| `Validators`                                  | `App\Concerns\ExpenseValidationRules` + form requests                           |
| `Result<T>` / `Failure`                       | Form-request validation + `Gate::authorize()` — Laravel's own error channels    |
| `firestore.rules` ownership                   | `App\Policies\ExpensePolicy`                                                    |
| `go_router` + auth redirect                   | `routes/web.php` + `auth`/`verified` middleware                                 |
| Riverpod providers (reactive streams)         | Inertia props on each request + the query string                                |
| `Formatters`                                  | `resources/js/lib/format.ts`                                                    |
| Material 3 `ColorScheme.fromSeed(#00897B)`    | Tailwind design tokens in `resources/css/app.css`                               |
| `HomeShell` bottom navigation                 | `resources/js/components/app-sidebar.tsx`                                       |
| `ComingSoon` tabs                             | `resources/js/pages/coming-soon.tsx`, configured per route                      |

## Folder map

```
app/
├─ Actions/Expenses/          # Write operations: Create / Update / DeleteExpense
├─ Concerns/                  # Shared validation rules + attribute normalization
├─ Enums/                     # ExpenseCategory, PaymentMethod — label, color, icon
├─ Http/
│  ├─ Controllers/ExpenseController.php
│  └─ Requests/Expenses/      # StoreExpenseRequest, UpdateExpenseRequest
├─ Models/                    # User, Expense
├─ Policies/ExpensePolicy.php # Owner-only access
└─ Support/                   # ExpenseFilter, MonthlySummary, Currencies

resources/js/
├─ components/
│  ├─ expenses/               # summary card, row, filter bar, form, delete dialog
│  ├─ dynamic-icon.tsx        # renders a Lucide icon by the name the server sent
│  └─ ui/                     # shadcn primitives
├─ hooks/use-currency.ts      # the signed-in user's currency
├─ lib/format.ts              # currency + date formatting, one place
├─ lib/expense-meta.ts        # icon-name → component registry, option lookups
├─ pages/expenses/            # index, create, edit
├─ pages/coming-soon.tsx      # later-phase destinations
└─ types/expenses.ts          # wire types mirroring the server's shapes
```

## State: streams became the URL

The mobile app drove the expense list from a reactive Drift stream, with the
filter and selected month held in Riverpod notifiers. On the web that state
lives in the **query string** instead:

- `?search=`, `?category=`, `?start_date=`, `?end_date=` — the list filter
- `?month=YYYY-MM` — the month the summary card totals
- `?page=` — pagination

`ExpenseFilter::fromRequest()` parses it on the way in; `ExpenseFilter::toArray()`
sends it back so the controls can render their current state. The benefit over
local component state: a filtered view is linkable, bookmarkable, and survives a
refresh. Search debounces and issues a partial Inertia reload (`only: ['expenses',
'filters']`); everything else is a plain link.

## Enums are the single source of truth

`ExpenseCategory` carries its own `label()`, `color()`, and `icon()` — exactly as
the Dart enum did. The controller ships `ExpenseCategory::options()` as a prop,
and the React side looks a row's category up in that list rather than keeping a
parallel map. Adding a category is a one-file change.

Icons cross the wire as Lucide *names* (`utensils`, `car`, …); `DynamicIcon`
resolves a name to a component via the registry in `lib/expense-meta.ts`.

## Google sign-in

The mobile app went Google → Firebase Auth → Firestore profile. On the web the
chain is shorter: Google → Laravel session → `users` row. This is the one path
that does *not* go through Firebase — Google has already proven who they are, so
there is nothing left for Firebase to add. Someone who used Google on mobile
lands on the same account here because both are keyed off the same Google
address.

A Google-only account therefore has no Firebase password. That is fine: it never
needs one, because it never uses the password form.

`App\Actions\Auth\FindOrCreateSocialUser` handles three cases:

1. **Known `google_id`** → sign in, refresh the avatar.
2. **Known email, no `google_id`** → link Google to that account — but *only* if
   Google's raw profile says `email_verified` is true. Otherwise anyone able to
   attach an unverified address to a Google account could claim someone else's
   PairPay account, so those are sent to the password form instead.
3. **Nobody matches** → create the account, email pre-verified, no password.

`google_id`, `avatar`, and `firebase_uid` are deliberately **not** mass
assignable; every write to them is an explicit `forceFill`. `users.password` is
nullable, because a Google-only account never sets one — they can create one
later through the forgot-password flow.

## Importing the Firebase accounts

`php artisan pairpay:import-firebase-users <file>` reads a
`firebase auth:export` JSON and creates or updates the matching `users` rows,
mapping `localId` → `firebase_uid`, the `google.com` provider's `rawId` →
`google_id`, plus name, email, avatar, and verification state.

**Passwords are not imported.** Firebase hashes them with its own modified
scrypt, which Laravel cannot verify. An imported account signs in with Google
(if it had that provider), or by typing the password it already has into the
ordinary login form — see the next section for how that is checked. The command
reports how many have no Google provider; it does not email anyone.

It is idempotent, matching on `firebase_uid` first and email second, so running
it twice updates rather than duplicates. Use `--dry-run` first.

Note that Firestore's `users` documents also held `currencyCode`, which the Auth
export does not include — imported accounts start at USD and can change it in
profile settings.

## Firebase is the credential store

Every password in this app lives in Firebase. `users.password` is never written,
and the `users` table is a profile store — the thing expenses hang off — rather
than something that can let anybody in.

    POST /login → Firebase checks the password → ID token → verify → session

`App\Services\Firebase\IdentityToolkit` is the whole account lifecycle, over
Google's Identity Toolkit REST API: creating accounts, checking passwords,
changing them, and asking for the reset and verification emails. It is all
server-to-server, which is why no Firebase SDK ships to the browser and no
service account key is deployed. The Web API key is enough, and it is a public
identifier rather than a secret.

Sign-in is the one call that treats refusal as routine and returns null.
Everywhere else a refusal is something the person needs told about, so it
arrives as a `FirebaseRefusal` carrying Firebase's own code — which is how
`EMAIL_EXISTS` becomes an ordinary validation error on the registration form.

### What each flow does now

| Flow | Where it happens |
| --- | --- |
| Login | `AuthenticateUser`, wired in via `Fortify::authenticateUsing()` |
| Registration | `CreateNewUser` — created in Firebase, then mirrored to `users` |
| Password reset | `PasswordResetController` asks; Firebase mails and hosts the page |
| Email verification | `EmailVerificationController`; Firebase mails and hosts the page |
| Change password | `SecurityController@update` → `accounts:update` |
| Delete account | `ProfileController@destroy` → `accounts:delete`, credential first |
| Confirm password | `CurrentFirebasePassword`, and `Fortify::confirmPasswordsUsing()` |

Fortify still owns the pipeline around login — throttling, the two-factor step,
session handling — and its `resetPasswords` and `emailVerification` features are
switched off because Firebase does those instead. There is deliberately no
`/reset-password/{token}` route: the new password is set on Firebase's page and
this app never sees it.

### Identity, verification, and the refresh token

`App\Services\Firebase\IdTokenVerifier` verifies the ID token Firebase returns —
RS256 against Google's published certificates (cached for whatever
`Cache-Control` says), plus `aud`, `iss`, `exp`, `iat`, `auth_time` and `sub`.
The token arrived over TLS straight from Google, so this is belt and braces, but
it is the only evidence of *who* signed in. Token revocation is not checked;
that needs the Admin SDK and a service account key, and is not worth it for a
token consumed milliseconds after it is minted.

`App\Actions\Auth\FindOrCreateFirebaseUser` resolves that identity to a row:
known `firebase_uid` → that user; known email → link, but *only* if Firebase says
the address is verified; nobody matches → create. So an account made straight in
the Firebase console gets its profile row on first login, without anybody
touching the database.

Verification happens on Firebase's pages, so the app has to notice rather than
perform it. Login syncs `email_verified_at` from the token claim — in both
directions, since an address changed in Firebase comes back unverified. The
notice screen re-asks Firebase every time it is opened, which is why clicking
the link in the mail and coming back is the whole flow.

Doing that needs a token, and ID tokens last an hour, so
`App\Services\Firebase\FirebaseUserSession` keeps the *refresh* token in the
session — encrypted and already expiring, and gone on logout. Confirming a
password also refreshes it, which is what lets "change my password" and "delete
my account" act immediately afterwards.

Leave `FIREBASE_PROJECT_ID` and `FIREBASE_API_KEY` blank and nothing reaches
Firebase at all — every login simply fails, because there is no other credential
store to fall back to.


## Error handling

Repositories returning `Result<T>` were replaced by Laravel's own channels:

- **Invalid input** → form requests; errors come back as Inertia `errors` props
  and render through `InputError`.
- **Not yours** → `ExpensePolicy` via `Gate::authorize()` → 403. This is the
  server-side equivalent of the Firestore rules; ownership is no longer
  enforceable on the client alone.
- **Success** → `Inertia::flash('toast', …)`, surfaced by `use-flash-toast`.

## Theme

The Material 3 scheme seeded on `#00897B` became CSS custom properties in
`resources/css/app.css`, in both light and dark:

- `--primary` / `--ring` / `--sidebar-primary` — the teal seed
- `--primary-container` — M3's tonal container; the monthly summary card sits on it
- `--income`, `--expense`, `--owed`, `--lent`, `--pending`, `--confirmed`,
  `--rejected`, `--disputed` — the semantic finance colors the neutral palette
  does not model, exposed as `bg-income`, `text-expense`, and so on

Category colors stay hex on the enum and are applied inline, since they are data
rather than theme.

## Money

Amounts are stored `decimal(12,2)` and cast to `float` — exact in the database,
JSON-friendly on the wire. Each user has a `currency_code` (set in profile
settings, defaults to USD) and all formatting goes through `lib/format.ts`.

## What is not ported

- **Offline-first storage.** Drift gave the mobile app a local SQLite database.
  The web app reads and writes the server directly.
- **Firestore, Storage, and Cloud Messaging.** Firebase *Auth* is still the
  credential store (see above), but nothing else of Firebase came across — data
  lives in MySQL/SQLite via Eloquent.
- **Firebase's own second factor.** Two-factor and passkeys are Fortify's, on
  the local user record, layered on top of the Firebase password check. That
  works because the two-factor step runs *after* credentials are validated, and
  passkeys replace the password rather than depending on it.
- **Apple sign-in.** The mobile app wired it for iOS/macOS. Socialite has no
  first-party Apple driver; add `socialiteproviders/apple` if you want it.
- **Receipt OCR** (`google_mlkit_text_recognition`) and the `receipt_path`
  upload flow. The column exists; nothing writes to it yet.
- **Bills, Loans, Shared, Reports** — routed and visible in the navigation, each
  rendering `coming-soon`, exactly as the mobile app's `ComingSoon` tabs did.
