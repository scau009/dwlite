# Repository Guidelines

## Project Structure & Module Organization
- Root dirs: `backend/` (Symfony 6.4, FrankenPHP), `frontend/` (React 19, Vite, TS), `observability/` (Grafana/Loki/Tempo/Prometheus configs), `scripts/` helpers; orchestrate via `docker-compose.yml`.
- Backend layout: controllers in `backend/src/Controller`, services in `Service`, async in `Message` + `MessageHandler`, scheduled jobs in `Scheduler`, persistence in `Entity` + `Repository`, DTOs in `Dto`, cross-cutting in `EventSubscriber` and `Monolog`.
- Frontend layout: `frontend/src/components` shared UI, `pages` routes, `lib` API helpers, `config` menus/RBAC, `i18n` locales, `layouts` shells, `theme` Ant Design theming; alias `@/` → `frontend/src`.

## Build, Test, and Development Commands
- Docker stack (root): `docker compose up -d` to start backend/worker/scheduler + observability; add `--build` when changing images; `docker compose logs -f backend|worker|scheduler` to tail.
- Backend (`backend/`): `composer install`; `php bin/console doctrine:migrations:migrate` to sync schema; `php bin/console lexik:jwt:generate-keypair` for first-time JWT keys; `php bin/console cache:clear` after env changes.
- Frontend (`frontend/`): `npm install`; `npm run dev` for HMR; `npm run build` for type-check + production build; `npm run lint` for ESLint.

## Coding Style & Naming Conventions
- PHP: PSR-12 (4-space indent, strict types); constructor injection; name controllers `*Controller`, services `*Service`, handlers `*Handler`, DTOs `*Dto`; routes via attributes; keep DTO properties readonly where possible.
- TypeScript/React: functional components with hooks at top-level; prefer Ant Design + Tailwind utilities; PascalCase component files, kebab-case utils; follow `frontend/eslint.config.js`.
- Config: keep environment values in `.env`/Compose vars; never hardcode secrets.

## Testing Guidelines
- Frontend: run `npm run lint` before commits; `npm run build` for type safety. Add component/page tests when logic grows (Jest/RTL if introduced).
- Backend: no tests checked in; add Symfony functional or handler-level tests for new logic and document execution. Validate migrations on a fresh DB with `doctrine:migrations:migrate`.
- Manual: smoke main APIs after auth/schema changes; ensure `/health` and `/metrics` stay green.

## Commit & Pull Request Guidelines
- Commits: short, action-oriented; history mixes English/Chinese—stay concise and prefer imperative mood. Squash trivial WIP before PRs.
- PRs: include summary, linked issues/task IDs, screenshots for UI tweaks. Call out schema changes/migrations and manual steps. Confirm lint/build (frontend) and console checks/migrations (backend) ran.

## Security & Configuration Tips
- Set `APP_SECRET` and DB creds via env vars; MySQL expected at `host.docker.internal:3306`. JWT keys live in `backend/config/jwt/`—do not commit private keys.
- Grafana defaults to admin/admin; rotate outside dev. Logs/metrics/traces flow through Promtail/Loki/Prometheus/Tempo—emit structured logs and Prometheus metrics for new services.
