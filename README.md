# HealthSync

Family health record vault — monorepo for the API, mobile app, and web app.
See `docs/` for the product/development plan and architecture decision records.

## Repository layout

```
healthsync/
├── apps/
│   ├── mobile/   ← React Native (Expo) app
│   ├── web/      ← Next.js 14 web app
│   └── api/      ← Laravel 11 backend
├── packages/
│   ├── ui/       ← Shared design tokens
│   ├── types/    ← Shared TypeScript types (API contracts)
│   └── utils/    ← Shared utilities
├── infra/        ← Terraform (AWS resources)
├── docs/         ← API docs, ADRs
└── .github/workflows/
```

## Local development

```bash
# 1. Clone repo
git clone <repo-url> && cd healthsync

# 2. Start local services (PostgreSQL 16 + Redis 7)
docker compose up -d

# 3. Backend
cd apps/api
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve      # http://localhost:8000

# 4. Mobile
cd apps/mobile
npm install
npx expo start          # scan QR with Expo Go

# 5. Web
cd apps/web
npm install
npm run dev              # http://localhost:3000
```

## Notes

- Deployment (AWS provisioning, Terraform apply, CI/CD deploy stages) and QA/test execution
  are handled outside this repo's scaffold — `infra/` contains structure only, and CI runs
  lint/build/unit-test jobs but no deploy stage.
- Database schema and API conventions are documented in `docs/`.
