# De Gambling

Laravel/Sail backend for Team G-SHT online casino simulation. The payment provider handles crypto/payment intake, while the internal app ledger uses USD for wallets, bets, payouts, withdrawals, and risk analytics.

## Quick Start

Use Sail because project dependencies require PHP 8.4+.

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
```

For a clean local database:

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

API base URL:

```text
http://localhost:8081/api/v1
```

## Admin Access

Create or register a user, then grant admin by email:

```bash
./vendor/bin/sail artisan app:grant-admin your@email.com
```

Admin routes require a Bearer token from `/api/v1/auth/login`.

## Current Backend Coverage

- Sanctum token auth.
- Active/disabled account middleware.
- RBAC admin middleware using `roles` and `role_user`.
- USD wallet balance endpoint.
- Provider-based deposit invoice flow.
- Payment webhook parsing and signature check.
- Game list and dice betting flow.
- Provably fair seed/hash flow.
- Game settlement through Finance contract.
- USD transaction ledger.
- Manual withdrawal settlement queue.
- Admin withdrawal approve/reject flow.
- Risk score calculation.
- Risk events and active interventions.
- Circuit breaker blocking deposit and bet routes.
- Dashboard returns live risk/intervention state.

## Useful API Routes

Auth:

```http
POST /api/v1/auth/register
POST /api/v1/auth/login
GET  /api/v1/auth/me
POST /api/v1/auth/logout
```

User:

```http
GET   /api/v1/user/dashboard
GET   /api/v1/user/self-exclusion
POST  /api/v1/user/self-exclusion
GET   /api/v1/user/restrictions
PATCH /api/v1/user/restrictions
```

Finance:

```http
GET  /api/v1/wallet
POST /api/v1/wallet/deposit
POST /api/v1/wallet/withdraw
POST /api/v1/payments/webhook
```

Game:

```http
GET  /api/v1/games
POST /api/v1/games/{game}/bet
```

Admin:

```http
GET    /api/v1/admin/users
PATCH  /api/v1/admin/users/{user}/disable
DELETE /api/v1/admin/users/{user}
GET    /api/v1/admin/withdrawals
PATCH  /api/v1/admin/withdrawals/{transaction}/approve
PATCH  /api/v1/admin/withdrawals/{transaction}/reject
GET    /api/v1/admin/risk-events
GET    /api/v1/admin/interventions
```

## Smoke Test Notes

Route registration:

```bash
./vendor/bin/sail artisan route:list --path=api/v1
```

Create an active dice game if the DB has no games:

```bash
./vendor/bin/sail artisan tinker --execute="use App\Models\Game; Game::updateOrCreate(['slug'=>'dice'], ['name'=>'Dice', 'status'=>'active', 'rtp_percentage'=>'95.00']);"
```

Known local issue: host `php artisan` can fail if host PHP is below 8.4. Use Sail commands.

## Team Boundaries

- Andrew: Finance and ResponsibleGambling modules, wallets, transactions, settlement, risk score, circuit breaker.
- Pranav / Enzo: User module, auth, dashboard, profile limits, self-exclusion, account restrictions, admin user management.
- Yetkin: Game module, games, bets, RTP, provably fair logic, game state.

## Urgent Remaining Tasks

### Andrew

- Add feature tests for withdrawal request, approve, reject, and refund ledger correction.
- Add feature tests for circuit breaker blocking deposit and bet.
- Decide whether `risk_metrics` table is still required or whether `risk_events` + `interventions` is the final simplified design.
- Make deposit provider errors return clean API JSON instead of leaking provider exceptions.
- Add minimal admin-facing fields for withdrawal destination/method in frontend.

### Pranav / Enzo

- Confirm RBAC flow after latest changes: `RoleSeeder`, `UserRoleSeeder`, `app:grant-admin`, and admin middleware.
- Make dashboard consume real backend data: wallet balance, risk score, self-exclusion, active intervention.
- Finish profile restrictions UI/API polish.
- Make self-exclusion UX clear: user should see active period and cannot bet while excluded.
- Add frontend auth token handling and route guards for active/disabled users.

### Yetkin

- Seed at least one active game for demo, preferably Dice.
- Confirm every game writes bets in `USD`, not `USDT`.
- Add validation/handling for insufficient balance response from Finance settlement.
- Add RTP/admin win-rate controls or mark as simplified if not enough time.
- Add frontend game screen that calls `/games` and `/games/{game}/bet`.

### Everyone

- Stop changing another module's internals unless there is an agreed contract change.
- Use contracts/services across module boundaries where possible.
- Prioritize demo-ready frontend screens now: login, dashboard, wallet, game, admin settlement, responsible gambling alerts.
- Keep API responses JSON and predictable for frontend integration.
