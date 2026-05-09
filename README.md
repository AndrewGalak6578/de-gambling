# De Gambling Starter Scaffold

Laravel/Sail starter scaffold for Team G-SHT.

## 1. Create Laravel project

```bash
curl -s "https://laravel.build/de-gambling?with=mysql,redis,mailpit" | bash
cd de-gambling
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
```

## 2. Install API auth

```bash
./vendor/bin/sail composer require laravel/sanctum
./vendor/bin/sail artisan install:api
```

## 3. Copy scaffold files

Copy files from this package into the Laravel project root.

## 4. Run migrations

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

## Team boundaries

- Pranav: User module, auth, dashboard, profile limits, self-exclusion.
- Yetkin: Game module, games, bets, RTP, provably fair logic.
- Andrew: Finance and ResponsibleGambling modules, wallets, transactions, settlement, risk score, circuit breaker.

## Rule

Do not call another module's Eloquent models directly from your module. Use contracts/services.
