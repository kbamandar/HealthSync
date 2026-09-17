# HealthSync — Development Plan
## Version 1.0 | May 2026 | CONFIDENTIAL

> **Document Purpose:** Sprint-level development plan derived from the HealthSync Product Foundation Document (v1.0). Intended for the engineering team, co-founders, and technical advisors. Covers the full arc from environment setup through MVP launch and into V1.x post-launch hardening.

---

## Table of Contents

1. [Guiding Principles](#1-guiding-principles)
2. [Team & Roles](#2-team--roles)
3. [Repository & Environment Setup](#3-repository--environment-setup)
4. [Database Schema Design](#4-database-schema-design)
5. [Sprint Plan — MVP (Months 1–5)](#5-sprint-plan--mvp-months-15)
   - [Phase 0 — Pre-Sprint Setup (Week 0)](#phase-0--pre-sprint-setup-week-0)
   - [Sprint 1–4 — Foundation (Months 1–2)](#sprint-14--foundation-months-12)
   - [Sprint 5–6 — Core Loop (Month 3)](#sprint-56--core-loop-month-3)
   - [Sprint 7–8 — Polish & Security (Month 4)](#sprint-78--polish--security-month-4)
   - [Sprint 9–10 — Launch Prep (Month 5)](#sprint-910--launch-prep-month-5)
6. [Post-MVP Roadmap (Months 6–18)](#6-post-mvp-roadmap-months-618)
7. [Definition of Done](#7-definition-of-done)
8. [API Design Conventions](#8-api-design-conventions)
9. [Testing Strategy](#9-testing-strategy)
10. [CI/CD Pipeline](#10-cicd-pipeline)
11. [Security Checklist](#11-security-checklist)
12. [Compliance Milestones](#12-compliance-milestones)
13. [Risk Register](#13-risk-register)

---

## 1. Guiding Principles

These govern every technical decision made during development. When in doubt, apply them in order.

1. **PHI safety first.** Every piece of code that touches patient health data must be reviewed for encryption, access control, and audit logging before merge. No exceptions.
2. **Mobile-first, then web.** React Native drives adoption. The web app is a secondary surface. Build and test on mobile before web.
3. **FHIR-aware from day 1.** Even though ABDM is V2, the data model must map to FHIR resources now. Retrofitting costs more than doing it right.
4. **Ship working software in 2-week cycles.** Every sprint ends with a deployable build. No sprint ends with "almost done."
5. **Test coverage is not optional.** PRs without tests for new functionality are rejected. Target: > 70% coverage at all times.
6. **Feature flags over branches.** Incomplete features go behind a flag, not in a long-lived branch. Main is always deployable.
7. **India data residency is non-negotiable.** No PHI leaves ap-south-1 (Mumbai). Confirm on every third-party integration.

---

## 2. Team & Roles

| Role | Responsibility | Stack ownership |
|---|---|---|
| **Tech Lead / Full-Stack** (Mandar) | Architecture decisions, backend core, PR reviews, infra | Laravel backend, AWS, PostgreSQL, CI/CD |
| **Mobile Developer** | React Native app — all screens, OCR, biometrics, offline | React Native (Expo), TypeScript, MMKV |
| **Backend Developer** | API endpoints, background jobs, PDF generation | Laravel / NestJS, Redis, Bull MQ |
| **Frontend Developer (Part-time)** | Next.js web app, shareable record links | Next.js, Tailwind, shadcn/ui |
| **QA / Testing** | Test plan, regression, device testing | Jest, Detox, Supertest |
| **Dr. Supriya Patil (Co-founder)** | Clinical validation, UX acceptance, user research | — |

> **Hiring note:** For a lean MVP, the Mobile Developer and Backend Developer roles may overlap in a 2-person engineering team with Mandar as tech lead. The Frontend Developer can be contracted from Month 3 for the web app surface.

---

## 3. Repository & Environment Setup

### 3.1 Repository Structure

```
healthsync/                     ← monorepo root (Turborepo)
├── apps/
│   ├── mobile/                 ← React Native (Expo) app
│   ├── web/                    ← Next.js 14 web app
│   └── api/                    ← Laravel 11 backend
├── packages/
│   ├── ui/                     ← Shared design tokens, icons
│   ├── types/                  ← Shared TypeScript types (API contracts)
│   └── utils/                  ← Shared utilities (date formatting, health constants)
├── infra/                      ← Terraform (AWS resources)
│   ├── modules/
│   └── environments/
│       ├── staging/
│       └── production/
├── docs/                       ← API docs, ADRs, runbooks
└── .github/
    └── workflows/              ← CI/CD pipelines
```

### 3.2 Branch Strategy

```
main            ← production-ready; protected; requires 1 PR review + all CI passing
staging         ← auto-deployed to staging environment on merge
develop         ← integration branch; feature branches merge here first
feature/*       ← individual features (e.g. feature/record-upload-ocr)
fix/*           ← bug fixes
release/*       ← release branches for version tagging
```

### 3.3 Environment Variables

| Environment | Purpose | Infrastructure |
|---|---|---|
| `local` | Developer machine | Docker Compose (Postgres + Redis) |
| `staging` | Internal QA + beta testing | AWS ECS Fargate (t3.small), RDS free tier |
| `production` | Live app | AWS ECS Fargate (2 tasks), RDS t3.medium |

All secrets managed via **AWS Secrets Manager**. Never committed to Git. Pulled at container startup.

### 3.4 Local Development Setup

```bash
# 1. Clone repo
git clone git@github.com:healthsync/healthsync.git && cd healthsync

# 2. Start local services
docker compose up -d   # PostgreSQL 16 + Redis 7

# 3. Backend
cd apps/api
cp .env.example .env   # fill in local values
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve      # http://localhost:8000

# 4. Mobile
cd apps/mobile
npm install
npx expo start         # scan QR with Expo Go

# 5. Web
cd apps/web
npm install
npm run dev            # http://localhost:3000
```

---

## 4. Database Schema Design

### 4.1 Core Tables

```sql
-- ─────────────────────────────────────────────────────────────
-- USERS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE users (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  mobile          VARCHAR(15) NOT NULL UNIQUE,   -- E.164 format
  email           VARCHAR(255) UNIQUE,
  name            VARCHAR(255) NOT NULL,
  date_of_birth   DATE,
  gender          VARCHAR(20),
  blood_group     VARCHAR(10),
  profile_photo   TEXT,                          -- S3 key
  abha_id         TEXT,                          -- encrypted; pgcrypto
  emergency_contact_name  VARCHAR(255),
  emergency_contact_mobile VARCHAR(15),
  is_active       BOOLEAN DEFAULT TRUE,
  created_at      TIMESTAMPTZ DEFAULT NOW(),
  updated_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ─────────────────────────────────────────────────────────────
-- FAMILY GROUPS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE family_groups (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  owner_id        UUID NOT NULL REFERENCES users(id),
  name            VARCHAR(255),
  created_at      TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE family_members (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  family_group_id UUID NOT NULL REFERENCES family_groups(id),
  member_user_id  UUID REFERENCES users(id),   -- NULL = guardian-managed
  added_by_user_id UUID NOT NULL REFERENCES users(id),
  relationship    VARCHAR(50) NOT NULL,         -- spouse, parent, child, sibling, other
  custom_label    VARCHAR(100),
  display_name    VARCHAR(255) NOT NULL,
  date_of_birth   DATE,
  gender          VARCHAR(20),
  blood_group     VARCHAR(10),
  is_guardian_managed BOOLEAN DEFAULT FALSE,
  access_level    VARCHAR(30) DEFAULT 'self_only',  -- full_access | self_only | view_only
  invite_status   VARCHAR(20) DEFAULT 'pending',    -- pending | accepted | declined
  invite_token    TEXT,
  invite_sent_at  TIMESTAMPTZ,
  created_at      TIMESTAMPTZ DEFAULT NOW(),
  updated_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ─────────────────────────────────────────────────────────────
-- HEALTH RECORDS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE health_records (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  family_group_id UUID NOT NULL REFERENCES family_groups(id),
  member_id       UUID NOT NULL REFERENCES family_members(id),
  uploaded_by_id  UUID NOT NULL REFERENCES users(id),
  category        VARCHAR(50) NOT NULL,   -- see enum below
  title           VARCHAR(500),
  record_date     DATE,
  doctor_name     VARCHAR(255),
  hospital_clinic VARCHAR(255),
  notes           TEXT,
  is_favourite    BOOLEAN DEFAULT FALSE,
  custom_tags     TEXT[],
  fhir_resource_type VARCHAR(100),        -- FHIR V2 readiness
  fhir_resource_id   VARCHAR(255),
  is_deleted      BOOLEAN DEFAULT FALSE,  -- soft delete
  deleted_at      TIMESTAMPTZ,
  version         INTEGER DEFAULT 1,
  parent_record_id UUID REFERENCES health_records(id), -- version chain
  created_at      TIMESTAMPTZ DEFAULT NOW(),
  updated_at      TIMESTAMPTZ DEFAULT NOW()
);

-- Record category enum (enforced at app layer, not DB for flexibility)
-- lab_report | radiology | prescription | discharge_summary | vaccination
-- chronic_condition | allergy | vital_signs | dental | eye | insurance
-- fitness_lifestyle | other

CREATE TABLE record_files (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  record_id       UUID NOT NULL REFERENCES health_records(id),
  file_type       VARCHAR(10) NOT NULL,   -- pdf | jpg | png
  s3_key          TEXT NOT NULL,          -- UUID-named; never patient identifiable
  file_size_bytes INTEGER,
  mime_type       VARCHAR(100),
  ocr_extracted   BOOLEAN DEFAULT FALSE,
  ocr_data        JSONB,                  -- extracted values, dates, lab names
  created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ─────────────────────────────────────────────────────────────
-- VITALS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE vital_readings (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  family_group_id UUID NOT NULL REFERENCES family_groups(id),
  member_id       UUID NOT NULL REFERENCES family_members(id),
  logged_by_id    UUID NOT NULL REFERENCES users(id),
  vital_type      VARCHAR(50) NOT NULL,   -- bp_systolic | bp_diastolic | sugar_fasting
                                          -- sugar_pp | sugar_random | hba1c | weight
                                          -- bmi | heart_rate | spo2 | temperature
  value           NUMERIC(8,2) NOT NULL,
  unit            VARCHAR(20) NOT NULL,
  reading_context VARCHAR(100),           -- fasting | post_exercise | after_medication
  notes           TEXT,
  recorded_at     TIMESTAMPTZ NOT NULL,
  is_abnormal     BOOLEAN DEFAULT FALSE,
  alert_sent      BOOLEAN DEFAULT FALSE,
  created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ─────────────────────────────────────────────────────────────
-- REMINDERS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE reminders (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  family_group_id UUID NOT NULL REFERENCES family_groups(id),
  member_id       UUID NOT NULL REFERENCES family_members(id),
  created_by_id   UUID NOT NULL REFERENCES users(id),
  reminder_type   VARCHAR(50) NOT NULL,   -- medication | appointment | vaccination
                                          -- annual_checkup | lab_repeat
  title           VARCHAR(500) NOT NULL,
  description     TEXT,
  due_at          TIMESTAMPTZ NOT NULL,
  recurrence      VARCHAR(50),            -- daily | weekly | monthly | custom
  recurrence_rule JSONB,                  -- cron-like spec for custom recurrence
  notify_via      TEXT[] DEFAULT '{push}', -- push | sms | whatsapp
  is_active       BOOLEAN DEFAULT TRUE,
  last_sent_at    TIMESTAMPTZ,
  linked_record_id UUID REFERENCES health_records(id),
  created_at      TIMESTAMPTZ DEFAULT NOW(),
  updated_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ─────────────────────────────────────────────────────────────
-- DOCTORS (personal directory)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE doctors (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  family_group_id UUID NOT NULL REFERENCES family_groups(id),
  name            VARCHAR(255) NOT NULL,
  speciality      VARCHAR(255),
  hospital_clinic VARCHAR(500),
  phone           VARCHAR(20),
  location        TEXT,
  notes           TEXT,
  created_at      TIMESTAMPTZ DEFAULT NOW(),
  updated_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ─────────────────────────────────────────────────────────────
-- SHARED LINKS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE shared_links (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  created_by_id   UUID NOT NULL REFERENCES users(id),
  link_type       VARCHAR(30) NOT NULL,   -- record | health_summary
  record_id       UUID REFERENCES health_records(id),
  member_id       UUID REFERENCES family_members(id),  -- for health_summary
  token           TEXT NOT NULL UNIQUE,   -- cryptographically random
  expires_at      TIMESTAMPTZ NOT NULL,
  access_count    INTEGER DEFAULT 0,
  max_access      INTEGER,                -- NULL = unlimited within expiry
  is_revoked      BOOLEAN DEFAULT FALSE,
  created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ─────────────────────────────────────────────────────────────
-- AUDIT LOG (append-only — never delete)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE audit_events (
  id              BIGSERIAL PRIMARY KEY,
  user_id         UUID REFERENCES users(id),
  family_group_id UUID,
  event_type      VARCHAR(100) NOT NULL,  -- record.upload | record.view | link.create
                                          -- link.access | member.invite | login.otp
  resource_type   VARCHAR(50),
  resource_id     UUID,
  ip_address      INET,
  user_agent      TEXT,
  metadata        JSONB,
  created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ─────────────────────────────────────────────────────────────
-- CONSENT RECORDS (DPDP Act compliance)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE consent_records (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id         UUID NOT NULL REFERENCES users(id),
  consent_type    VARCHAR(100) NOT NULL,  -- privacy_policy | terms_of_service | data_processing
  version         VARCHAR(20) NOT NULL,
  consented_at    TIMESTAMPTZ NOT NULL,
  ip_address      INET,
  user_agent      TEXT
);
```

### 4.2 Key Indexes

```sql
-- Performance indexes
CREATE INDEX idx_health_records_member    ON health_records(member_id, record_date DESC);
CREATE INDEX idx_health_records_category  ON health_records(family_group_id, category);
CREATE INDEX idx_health_records_search    ON health_records USING GIN(to_tsvector('english', coalesce(title,'') || ' ' || coalesce(notes,'')));
CREATE INDEX idx_vital_readings_member    ON vital_readings(member_id, vital_type, recorded_at DESC);
CREATE INDEX idx_reminders_due            ON reminders(due_at, is_active) WHERE is_active = TRUE;
CREATE INDEX idx_audit_events_user        ON audit_events(user_id, created_at DESC);
CREATE INDEX idx_shared_links_token       ON shared_links(token) WHERE is_revoked = FALSE;
```

### 4.3 FHIR Resource Mapping Reference

| HealthSync Entity | FHIR R4 Resource | Notes |
|---|---|---|
| User / Family Member | `Patient` | gender, birthDate, identifier (ABHA ID) |
| Allergy record | `AllergyIntolerance` | code, clinicalStatus |
| Chronic condition | `Condition` | code, onsetDate, clinicalStatus |
| Lab report | `DiagnosticReport` + `Observation` | result references |
| Vaccination record | `Immunization` | vaccineCode, occurrenceDate |
| Prescription | `MedicationRequest` | medication, dosageInstruction |
| Vital reading | `Observation` | code (LOINC), value, effectiveDateTime |
| Uploaded document | `DocumentReference` | content.attachment (S3 URL) |

---

## 5. Sprint Plan — MVP (Months 1–5)

> **Sprint cadence:** 2-week sprints. Sprint planning Monday morning. Demo + retrospective last Friday. Story points: Fibonacci (1, 2, 3, 5, 8, 13). Max capacity per sprint per developer: 30 points.

---

### Phase 0 — Pre-Sprint Setup (Week 0)

**Goal:** Everything is ready for Sprint 1 to begin coding on Day 1.

| Task | Owner | Est |
|---|---|---|
| Create GitHub org + monorepo (Turborepo scaffold) | Mandar | Day 1 |
| Configure GitHub Actions: lint + test + build | Mandar | Day 1 |
| Set up AWS account, IAM roles, VPC, subnets | Mandar | Day 1–2 |
| Terraform: RDS PostgreSQL + ElastiCache Redis (staging) | Mandar | Day 2–3 |
| Terraform: ECS Fargate cluster + ECR registry (staging) | Mandar | Day 2–3 |
| AWS Secrets Manager: seed staging secrets | Mandar | Day 3 |
| Docker Compose for local dev (Postgres + Redis) | Backend Dev | Day 2 |
| Laravel 11 project scaffold in `apps/api` | Backend Dev | Day 2 |
| Expo React Native project scaffold in `apps/mobile` | Mobile Dev | Day 2 |
| Figma project setup + design tokens (colours, type, spacing) | Mandar + Dr. Supriya | Day 1–3 |
| Linear / Jira project setup — backlog imported from this plan | Mandar | Day 1 |
| Slack workspace + channels: `#dev`, `#mobile`, `#design`, `#deploy` | Mandar | Day 1 |
| DLT registration for MSG91 (SMS OTP) — 4-week process, start now | Mandar | Day 1 |
| Register on NHA ABDM sandbox | Mandar | Day 2 |

**Exit criteria:** CI passes on an empty commit. `php artisan migrate` runs clean. `npx expo start` shows blank app. Staging environment reachable.

---

### Sprint 1–4 — Foundation (Months 1–2)

**Goal:** A user can register, create a family, upload a record, and enter a vital reading. Internal alpha.

---

#### Sprint 1 (Weeks 1–2) — Auth & User Profile

**Backend tasks**

| Task | Story | Points |
|---|---|---|
| OTP generation + verification endpoint (`POST /auth/otp/send`, `POST /auth/otp/verify`) | US-001 | 5 |
| JWT access token (15 min) + refresh token (7 days, rotated) issuance | US-001 | 3 |
| `users` table migration + User model | US-001 | 2 |
| `POST /users/profile` — create/update profile (name, DOB, gender, blood group) | US-001 | 3 |
| Profile photo upload to S3 (pre-signed URL flow) | US-001 | 3 |
| Consent capture at registration (DPDP Act) — `consent_records` table + endpoint | Compliance | 3 |
| Rate limiting on OTP endpoints (5 attempts / 10 min per mobile) | Security | 2 |
| MSG91 SMS integration (DLT template: OTP) | US-001 | 3 |

**Mobile tasks**

| Task | Story | Points |
|---|---|---|
| Onboarding screens: splash → mobile entry → OTP input → profile setup | US-001 | 8 |
| JWT storage in MMKV (encrypted) + auto-refresh interceptor | US-001 | 3 |
| Bottom tab navigation scaffold (Home, Records, Vitals, Family, Profile) | US-001 | 3 |
| Profile screen: view + edit personal details | US-001 | 3 |
| Biometric lock scaffold (Expo LocalAuthentication — enable/disable only in Sprint 7) | — | 2 |

**Sprint 1 total:** ~46 points across backend + mobile (23 each). Adjust based on team velocity.

**Exit criteria:** New user registers via OTP, completes profile, sees bottom tab bar. JWT auth works end-to-end.

---

#### Sprint 2 (Weeks 3–4) — Family Member Management

**Backend tasks**

| Task | Story | Points |
|---|---|---|
| `family_groups` + `family_members` migrations | US-002 | 2 |
| `POST /family/members` — add member (guardian mode or invite) | US-002, US-003 | 5 |
| `GET /family/members` — list family with access levels | US-002 | 2 |
| `PUT /family/members/:id` — update relationship, access level | US-002 | 2 |
| `DELETE /family/members/:id` — soft-remove | US-002 | 1 |
| Invite flow: generate token, send SMS link | US-002 | 5 |
| `POST /family/invite/accept` — invited member links account | US-004 | 5 |
| `POST /family/invite/decline` — decline invite | US-004 | 2 |
| Revoke access endpoint | US-002 | 2 |

**Mobile tasks**

| Task | Story | Points |
|---|---|---|
| Family screen: member list with avatars + access level badges | US-002 | 5 |
| Add family member flow: relationship type → mobile number or guardian mode | US-002, US-003 | 8 |
| Member profile screen: details + access control toggle | US-002 | 3 |
| Family member switcher header (global — appears on Records, Vitals tabs) | US-002 | 5 |
| Invite acceptance deep-link handler | US-004 | 3 |

**Exit criteria:** Primary user adds a family member, sends invite. Invited member accepts on their phone and sees their own profile. Guardian mode records appear flagged.

---

#### Sprint 3 (Weeks 5–6) — Health Records Vault (Upload)

**Backend tasks**

| Task | Story | Points |
|---|---|---|
| `health_records` + `record_files` migrations | US-010 | 2 |
| `POST /records` — create record (metadata) | US-010 | 3 |
| S3 pre-signed URL generation for file upload | US-010 | 3 |
| `POST /records/:id/files` — register uploaded file after S3 upload | US-010 | 2 |
| ClamAV virus scan on upload webhook (Lambda trigger) | Security | 5 |
| MIME type validation (whitelist: PDF, JPG, PNG) | Security | 2 |
| `GET /records` — list with filters (member, category, date range) | US-010 | 3 |
| `GET /records/:id` — get single record with files | US-010 | 2 |
| `PUT /records/:id` — update metadata | US-010 | 2 |
| `DELETE /records/:id` — soft delete (recycle bin, 30-day) | US-012 | 2 |
| `GET /records/recycle-bin` + `POST /records/:id/restore` | US-012 | 2 |

**Mobile tasks**

| Task | Story | Points |
|---|---|---|
| Records screen: list view with category filter pills + search bar | US-014 | 5 |
| Upload flow: camera capture OR file picker → category → metadata form | US-010 | 8 |
| Record detail screen: file viewer (PDF / image) + metadata | US-010 | 5 |
| Edit record screen: update metadata | US-012 | 3 |
| Favourite toggle on record | US-012 | 1 |
| Custom tag input on record | US-012 | 2 |
| Delete record with confirmation + recycle bin screen | US-012 | 3 |

**Exit criteria:** User photographs a lab report, selects category, fills metadata, saves. Record appears in list. PDF opens in viewer.

---

#### Sprint 4 (Weeks 7–8) — Vitals + Simple Dashboard

**Backend tasks**

| Task | Story | Points |
|---|---|---|
| `vital_readings` migration | US-020 | 2 |
| `POST /vitals` — log a reading | US-020 | 3 |
| `GET /vitals` — list by member + vital type + date range | US-020 | 3 |
| `DELETE /vitals/:id` | US-020 | 1 |
| Abnormal threshold detection (server-side rules per vital type) | NFR | 5 |
| `GET /dashboard` — aggregate: recent records, upcoming reminders (stub), active conditions | US-001 | 5 |
| Push notification setup: FCM integration + `POST /devices` (register device token) | — | 5 |

**Mobile tasks**

| Task | Story | Points |
|---|---|---|
| Vitals screen: tabbed by vital type (BP, Sugar, Weight, etc.) | US-020 | 5 |
| Log vital form: value + context + notes | US-020 | 3 |
| Trend chart (Victory Native): 7/30/90/365 day toggles | US-020 | 8 |
| Reference range indicator (colour bands: normal/borderline/abnormal) | US-020 | 3 |
| Abnormal value alert card (inline, not intrusive) | NFR | 3 |
| Home dashboard: Health-at-a-glance card + recent records feed | — | 5 |
| Family health dashboard: member cards with pending action counts | US-002 | 5 |

**Internal Alpha:** End of Sprint 4. Deploy staging build. Founding team + 5 internal testers onboard.

---

### Sprint 5–6 — Core Loop (Month 3)

**Goal:** OCR works, reminders fire, health timeline is browsable, sharing is possible. Closed beta (50 users).

---

#### Sprint 5 (Weeks 9–10) — OCR & Reminders Engine

**Backend tasks**

| Task | Story | Points |
|---|---|---|
| Background job queue (Laravel Horizon + Redis) | — | 3 |
| OCR job: trigger Google Cloud Vision after file upload | US-010 | 5 |
| OCR result parser: extract date, lab name, test values from structured reports | US-010 | 8 |
| Store OCR data in `record_files.ocr_data` JSONB | US-010 | 2 |
| `reminders` table migration | US-021 | 2 |
| `POST /reminders` — create reminder (medication / appointment / vaccination) | US-021 | 3 |
| `GET /reminders` — list with upcoming filter | US-021 | 2 |
| `PUT /reminders/:id` — update/disable | US-021 | 2 |
| Reminder scheduler job: poll due reminders, dispatch push + SMS | US-021 | 5 |
| WhatsApp notification integration (Gupshup) for reminders | US-021 | 3 |

**Mobile tasks**

| Task | Story | Points |
|---|---|---|
| On-device OCR (ML Kit) during camera capture — pre-fill metadata form | US-010 | 8 |
| OCR result review screen: show extracted values, let user confirm/edit | US-010 | 5 |
| Reminders screen: list by upcoming date + member | US-021 | 3 |
| Create reminder flow: type → member → schedule → notify via | US-021 | 5 |
| Vaccination reminder: auto-suggest based on uploaded vaccination records | US-022 | 5 |
| Push notification handler: tap notification → deep link to relevant screen | US-021 | 3 |

---

#### Sprint 6 (Weeks 11–12) — Health Timeline, Search & Sharing

**Backend tasks**

| Task | Story | Points |
|---|---|---|
| `GET /timeline` — paginated, filtered health event feed (records + vitals) | — | 5 |
| Full-text search: `GET /search?q=` using PostgreSQL GIN index | US-014 | 5 |
| `shared_links` migration | US-013 | 2 |
| `POST /share/record` — generate time-limited link | US-013 | 3 |
| `POST /share/summary` — generate health summary share link | US-013 | 3 |
| `GET /share/:token` — public endpoint (no auth required); verify token, return data | US-013 | 5 |
| `DELETE /share/:id` — revoke shared link | US-013 | 2 |
| Health Summary PDF generation (Gotenberg/Puppeteer) | US-013 | 8 |
| QR code generation for share link | US-013 | 2 |
| Audit logging for all share events | Security | 3 |

**Mobile tasks**

| Task | Story | Points |
|---|---|---|
| Health timeline screen: chronological feed with category icons + member filter | — | 5 |
| Search screen: input → results grouped by category | US-014 | 5 |
| Smart folders: auto-grouped view by category + by family member | US-014 | 3 |
| Health summary screen: preview of generated PDF | US-013 | 3 |
| Share sheet: WhatsApp / email / copy link / show QR code | US-013 | 5 |
| Manage shared links screen: view active + revoke | US-013 | 3 |

**Closed Beta:** End of Sprint 6. Deploy production environment. Onboard 50 selected users.

---

### Sprint 7–8 — Polish & Security (Month 4)

**Goal:** Security hardened, data export works, doctor directory complete, App Store submission ready.

---

#### Sprint 7 (Weeks 13–14) — Security Hardening

**Backend tasks**

| Task | Story | Points |
|---|---|---|
| TLS 1.3 + HSTS configuration on ALB | Security | 2 |
| CORS lockdown: whitelist mobile + web origins only | Security | 2 |
| Input sanitisation middleware: all endpoints | Security | 3 |
| API rate limiting: per-user + per-IP (Laravel Throttle + Redis) | Security | 3 |
| `GET /audit-log` — user-visible audit history (own events) | Security | 3 |
| Data export: `POST /data-export` → async ZIP generation → email download link | NFR | 8 |
| Account deletion workflow: mark for deletion, 30-day grace, purge job | NFR | 5 |
| `doctors` table migration + CRUD endpoints | US-001 | 3 |
| Doctor visit history: auto-populate from linked records | — | 3 |

**Mobile tasks**

| Task | Story | Points |
|---|---|---|
| Biometric lock: enable/disable in settings, lock on app background | NFR | 5 |
| PIN fallback (6-digit) with configurable auto-lock timeout | NFR | 5 |
| Device trust management screen: view sessions, revoke device | Security | 3 |
| Doctor directory screen: list, add, edit, call/WhatsApp quick actions | — | 5 |
| Doctor detail screen: linked records + visit history | — | 3 |
| Settings screen: notifications config, biometrics, data export, account deletion | — | 5 |

---

#### Sprint 8 (Weeks 15–16) — App Store Submission

**Backend tasks**

| Task | Story | Points |
|---|---|---|
| OWASP API pen test (external contractor or self-audit checklist) | Security | 8 |
| Fix all P0/P1 pen test findings | Security | 8 |
| Certificate pinning: serve expected cert SHA in `/.well-known/` | Security | 3 |
| Production Terraform apply: ECS Fargate (2 tasks), RDS t3.medium, CloudFront | Infra | 5 |
| Sentry integration: backend error tracking | Ops | 2 |
| Datadog / Grafana: APM + dashboards + alerts | Ops | 3 |
| Better Uptime: health check + incident alerts | Ops | 2 |
| PostgreSQL automated backup + cross-region S3 replication configured | Infra | 3 |

**Mobile tasks**

| Task | Story | Points |
|---|---|---|
| Certificate pinning in React Native (for production builds) | Security | 3 |
| App Store / Play Store listing: screenshots, description, metadata | Launch | 5 |
| App icon + splash screen (all variants) | Launch | 3 |
| Sentry integration: mobile crash reporting | Ops | 2 |
| Push notification opt-in flow (iOS permission prompt) | — | 2 |
| Accessibility pass: screen reader labels, minimum touch targets | NFR | 5 |
| Performance profiling: app load < 2s, upload < 5s on 4G | NFR | 5 |
| **Submit to App Store (iOS) + Play Store (Android)** | Launch | 3 |

**Open Beta:** End of Sprint 8. 500 beta users. App Store submission in review.

---

### Sprint 9–10 — Launch Prep (Month 5)

**Goal:** Health Score, Prescription Reader, polished onboarding. Public launch.

---

#### Sprint 9 (Weeks 17–18) — Health Score & Prescription Reader

**Backend tasks**

| Task | Story | Points |
|---|---|---|
| Health Score computation endpoint: `GET /members/:id/health-score` | — | 8 |
| Score algorithm: profile completeness (20%) + last checkup recency (20%) + vaccination status (20%) + vitals normalcy (20%) + medication adherence (20%) | — | 5 |
| Prescription OCR parser: extract medicine name, dose, frequency from prescription images | — | 8 |
| Auto-create reminders from parsed prescription | — | 5 |
| Health Insights engine (MVP-lite): rule-based pattern flags on vitals series | — | 8 |

**Mobile tasks**

| Task | Story | Points |
|---|---|---|
| Health Score gauge widget on home dashboard | — | 5 |
| Score breakdown screen: per-component explanations | — | 3 |
| Prescription reader screen: camera capture → parsed medicines → confirm → create reminders | — | 8 |
| Health Insights card on dashboard: pattern flags with "I understand" dismiss | — | 5 |
| Pre-consultation checklist screen: current meds + allergies + recent vitals | — | 5 |

---

#### Sprint 10 (Weeks 19–20) — Onboarding Optimisation & Public Launch

**Backend tasks**

| Task | Story | Points |
|---|---|---|
| Onboarding analytics events (PostHog / Mixpanel — no PHI) | — | 3 |
| Referral program backend: unique referral codes, track conversions | — | 5 |
| Notification preference management: `PUT /users/notification-prefs` | — | 3 |
| Privacy policy + ToS endpoints (versioned content) | Compliance | 2 |
| Load test: 500 concurrent users, validate performance SLAs | NFR | 5 |

**Mobile tasks**

| Task | Story | Points |
|---|---|---|
| Onboarding walkthrough (3-screen intro for new users) | — | 5 |
| Empty state designs for all major screens | — | 3 |
| Referral screen: share link + track referrals | — | 3 |
| Performance tuning pass: image lazy loading, list virtualisation | NFR | 5 |
| Hindi localisation (i18n scaffold + key strings) | NFR | 8 |
| App Store review fixes (if any from Sprint 8 submission) | — | 5 |
| **V1 Public Launch** | Launch | — |

**Launch checklist:**
- [ ] Privacy Policy live at healthsync.in/privacy
- [ ] Terms of Service live at healthsync.in/terms
- [ ] Consent capture working at registration
- [ ] All P0/P1 security issues resolved
- [ ] Sentry + Datadog dashboards green
- [ ] App Store approved (iOS + Android)
- [ ] DLT registration for SMS complete
- [ ] AWS WAF enabled on ALB
- [ ] Runbook documented for on-call incidents

---

## 6. Post-MVP Roadmap (Months 6–18)

### V1.1 — Month 6–7: Subscription & Web App

| Feature | Description |
|---|---|
| Razorpay subscription | Free → Family plan ₹160/mo billing, trial period, cancellation flow |
| Subscription gating | Feature flags: unlimited uploads, OCR, Health Passport behind paid tier |
| Next.js web app (Phase 1) | Shareable record view page (already built); add web login + basic record browser |
| In-app support | Freshdesk / Intercom widget |
| Medication adherence tracking | Mark medication taken / missed; adherence % in Health Score |

### V1.2 — Month 8–9: AI Insights + Android Widget

| Feature | Description |
|---|---|
| Health Insights v2 | ML-assisted pattern detection (local on-device, no PHI to cloud) |
| Android home screen widget | Quick vitals log + today's reminders |
| Marathi + Tamil localisation | i18n expansion |
| Offline read mode | Full record vault browsable offline (SQLite cache) |
| Lab report abnormal range library | Curated reference ranges for 50 common lab tests |

### V1.3 — Month 10–12: Doctor Network & Dental Module

| Feature | Description |
|---|---|
| Doctor-side sharing portal | Doctors register on HealthSync; patients can share directly to doctor ID |
| Dental record module enhancements | Tooth chart, orthodontic progress tracker, dental X-ray tagging |
| Eye records module | Prescription glasses/lenses tracker, annual exam reminder |
| Health record QR card | Printable emergency card with QR → Health Passport |
| PostHog analytics dashboard | Activation, retention, feature usage analysis |

### V2 — Month 13–18: ABDM + EHR Integration

| Feature | Description | Dependency |
|---|---|---|
| ABHA ID linking | Connect HealthSync account to 14-digit ABHA ID | NHA sandbox approval |
| ABDM PHR app certification | Full PHR compliance for health locker operations | NHA review process |
| FHIR adapter layer | Map HealthSync records to FHIR R4 resources; expose FHIR API | FHIR schema prepared in MVP DB |
| eDhanvantari integration | Records auto-sync from KBA's clinic platform to patient's HealthSync | Internal API |
| Practo EHR connector | Read prescription + visit history from Practo with patient consent | Practo partner API |
| OpenEMR connector | FHIR-based import from OpenEMR instances | FHIR adapter |
| Insurance pre-auth export | Generate structured document set for insurance pre-authorisation | V2 FHIR layer |
| On-call doctor module (scoping) | Architecture decision record for V3 telemedicine integration | — |

---

## 7. Definition of Done

A user story or task is **Done** when ALL of the following are true:

- [ ] Code merged to `develop` via reviewed PR
- [ ] All automated tests pass (unit + integration)
- [ ] Test coverage for new code ≥ 70%
- [ ] No P0 or P1 Sentry errors introduced in staging
- [ ] API endpoint documented in Swagger
- [ ] Relevant audit log events fired and verified
- [ ] PHI handling reviewed: encryption at rest, no logging of sensitive values
- [ ] Feature works on Android 8+ and iOS 14+ (tested on device, not just simulator)
- [ ] Empty state + error state handled in the UI
- [ ] Accessibility: minimum touch targets (44×44px), screen reader label set
- [ ] PM / Product sign-off (Dr. Supriya for clinically relevant features)

---

## 8. API Design Conventions

### 8.1 Base Structure

```
Base URL:     https://api.healthsync.in/v1
Auth:         Authorization: Bearer <access_token>
Content-Type: application/json
```

### 8.2 Response Envelope

```json
// Success
{
  "success": true,
  "data": { ... },
  "meta": { "page": 1, "per_page": 20, "total": 143 }
}

// Error
{
  "success": false,
  "error": {
    "code": "RECORD_NOT_FOUND",
    "message": "The requested record does not exist or you do not have access.",
    "details": {}
  }
}
```

### 8.3 Key Endpoints Summary

```
AUTH
POST   /v1/auth/otp/send              Send OTP to mobile
POST   /v1/auth/otp/verify            Verify OTP, return tokens
POST   /v1/auth/refresh               Refresh access token
POST   /v1/auth/logout                Revoke refresh token

USERS
GET    /v1/users/me                   Get own profile
PUT    /v1/users/me                   Update profile
POST   /v1/users/me/photo             Upload profile photo
POST   /v1/devices                    Register FCM device token

FAMILY
GET    /v1/family/members             List family members
POST   /v1/family/members             Add member (invite or guardian)
PUT    /v1/family/members/:id         Update member / access level
DELETE /v1/family/members/:id         Remove member
POST   /v1/family/invite/accept       Accept invite (token in body)
POST   /v1/family/invite/decline      Decline invite

RECORDS
GET    /v1/records                    List (filters: member_id, category, from, to, q)
POST   /v1/records                    Create record
GET    /v1/records/:id                Get record + files
PUT    /v1/records/:id                Update record metadata
DELETE /v1/records/:id                Soft delete
POST   /v1/records/:id/upload-url     Get pre-signed S3 URL
POST   /v1/records/:id/files          Register file after upload
GET    /v1/records/recycle-bin        List soft-deleted
POST   /v1/records/:id/restore        Restore from recycle bin

VITALS
GET    /v1/vitals                     List (filters: member_id, type, from, to)
POST   /v1/vitals                     Log reading
DELETE /v1/vitals/:id                 Delete reading

REMINDERS
GET    /v1/reminders                  List (filters: member_id, upcoming)
POST   /v1/reminders                  Create reminder
PUT    /v1/reminders/:id              Update / disable
DELETE /v1/reminders/:id              Delete

DOCTORS
GET    /v1/doctors                    List personal directory
POST   /v1/doctors                    Add doctor
PUT    /v1/doctors/:id                Update
DELETE /v1/doctors/:id                Remove

SHARING
POST   /v1/share/record               Create record share link
POST   /v1/share/summary              Create health summary share link
GET    /v1/share                      List active share links
DELETE /v1/share/:id                  Revoke
GET    /public/share/:token           Public: access shared link (no auth)

SEARCH & TIMELINE
GET    /v1/search?q=                  Full-text search
GET    /v1/timeline                   Health event feed

DASHBOARD
GET    /v1/dashboard                  Home dashboard aggregate
GET    /v1/members/:id/health-score   Health Score

DATA & COMPLIANCE
POST   /v1/data-export                Request full data export
POST   /v1/account/delete             Initiate account deletion
GET    /v1/audit-log                  Own audit history
```

---

## 9. Testing Strategy

### 9.1 Layers

| Layer | Tool | Coverage target | When |
|---|---|---|---|
| Unit — backend business logic | PHPUnit (Laravel) | > 80% | Every PR |
| Unit — frontend components | Jest + React Testing Library | > 70% | Every PR |
| Integration — API endpoints | Supertest (Node) / PHPUnit Feature | > 70% | Every PR |
| E2E — critical mobile flows | Detox | Key flows only | Pre-release |
| Manual — device testing | Physical Android + iOS devices | All screens | Each sprint demo |
| Security | OWASP ZAP + manual pen test | Full audit | Before Sprint 8 |
| Load | k6 | 500 concurrent users | Sprint 10 |

### 9.2 Critical E2E Flows (Detox)

1. New user registers via OTP → completes profile → sees dashboard
2. Primary user adds guardian-managed child → uploads record tagged to child → child record appears filtered
3. User photographs lab report → OCR extracts values → record saved → appears in timeline
4. User shares health summary → recipient opens link → PDF renders (no login required)
5. User logs BP reading → trend chart updates → abnormal alert fires
6. Invited family member accepts invite → sees own records only (Self Only access)
7. User sets medication reminder → notification fires at due time → snooze works

### 9.3 Test Data

Maintain a seeded test database with:
- 3 family groups (solo user, 3-member family, 6-member large family)
- 50+ records across all 13 categories
- 180 days of vitals data per family member
- 10 active reminders at various schedules
- 5 active shared links (some expired, some revoked)

---

## 10. CI/CD Pipeline

```yaml
# .github/workflows/ci.yml (simplified)

on: [push, pull_request]

jobs:
  backend:
    runs-on: ubuntu-latest
    services:
      postgres: { image: postgres:16, env: ... }
      redis:    { image: redis:7 }
    steps:
      - Checkout
      - PHP 8.3 setup
      - composer install
      - php artisan migrate --seed
      - php artisan test --coverage  # fail if < 70%
      - phpstan (static analysis)

  mobile:
    runs-on: ubuntu-latest
    steps:
      - Checkout
      - Node 20 setup
      - npm ci
      - npm run lint
      - npm test -- --coverage  # fail if < 70%
      - npx expo export --platform all  # verify build

  deploy-staging:
    needs: [backend, mobile]
    if: github.ref == 'refs/heads/staging'
    steps:
      - Build Docker image → push to ECR
      - terraform apply (staging)
      - ECS force-new-deployment
      - Smoke test: curl /health

  deploy-production:
    needs: [backend, mobile]
    if: github.ref == 'refs/heads/main'
    environment: production  # requires manual approval
    steps:
      - Build Docker image → push to ECR
      - terraform apply (production)  # plan shown in approval step
      - ECS blue-green deployment
      - Smoke test + Sentry check
      - Better Uptime alert if health check fails
```

---

## 11. Security Checklist

### Before every release

- [ ] No secrets in code (`git secrets` scan)
- [ ] Dependencies scanned (Dependabot + Snyk — no critical CVEs)
- [ ] All new endpoints require authentication (unless explicitly public)
- [ ] All new endpoints log to `audit_events`
- [ ] PHI fields not present in any log output
- [ ] S3 keys are UUID-based (no patient name in path)
- [ ] Pre-signed URLs expire within 15 minutes
- [ ] New shared link tokens are cryptographically random (32 bytes)
- [ ] Rate limiting applied to any new unauthenticated endpoint

### Before V1 launch (one-time)

- [ ] OWASP Mobile Top 10 audit complete
- [ ] OWASP API Security Top 10 audit complete
- [ ] External pen test booked and completed
- [ ] AWS WAF enabled on ALB
- [ ] GuardDuty enabled on AWS account
- [ ] VPC flow logs enabled
- [ ] CloudTrail enabled
- [ ] All RDS access through private subnet only (no public endpoint)
- [ ] S3 bucket public access blocked (all buckets)
- [ ] TLS 1.3 enforced; TLS 1.0/1.1 disabled
- [ ] Certificate pinning working in production mobile builds

---

## 12. Compliance Milestones

| Milestone | Target Date | Owner |
|---|---|---|
| Privacy Policy drafted (DPDP Act compliant) | Before closed beta (Month 3) | Legal advisor |
| Terms of Service drafted | Before closed beta | Legal advisor |
| DLT registration for SMS OTP complete | Month 1 (start immediately) | Mandar |
| Consent capture working at onboarding | Sprint 1 | Backend Dev |
| Data deletion workflow live | Sprint 7 | Backend Dev |
| Audit log accessible to users | Sprint 7 | Backend Dev |
| Data export working | Sprint 7 | Backend Dev |
| PrivacyPolicy + ToS published on website | Before open beta (Month 4) | Mandar |
| ABDM sandbox registration | Month 1 | Mandar |
| ABDM sandbox integration testing | Month 6+ | Backend Dev |
| Data Fiduciary registration (DPDP Act) | When regulations notified | Legal advisor |
| Pvt. Ltd. company incorporated | Before V1 launch | Mandar + Dr. Supriya |
| IP (brand + product) registered | Before V1 launch | Legal advisor |
| Pen test completed | Before V1 launch (Month 5) | External contractor |

---

## 13. Risk Register

| # | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| R01 | DLT registration delayed (SMS OTP blocked) | High | Critical | Start on Day 1. Use email OTP as fallback. WhatsApp OTP as second fallback. |
| R02 | App Store rejection (healthcare category scrutiny) | Medium | High | Review Apple's health app guidelines before Sprint 8. Include medical disclaimer. Avoid any language suggesting clinical diagnosis. |
| R03 | OCR accuracy below 85% on Hindi lab reports | Medium | High | Test on 100 real Indian lab report samples before Sprint 5 ships. Fallback: always allow manual entry. Consider Google Vision vs AWS Textract. |
| R04 | Team velocity lower than estimated | Medium | High | Sprint 1 velocity is the baseline. If < 60% of estimated points, descope Sprint 5 extras, not core auth or records. |
| R05 | AWS cost overrun during beta | Low | Medium | Set billing alerts at ₹20K/month. Review costs weekly during beta. Downsize RDS if usage is low. |
| R06 | PHI data breach | Low | Critical | Encryption at rest + transit, pen test, no PHI in logs, WAF, GuardDuty. Incident response plan documented in runbook before launch. |
| R07 | Third-party OCR (Google Vision) phased out or priced change | Low | Medium | Abstract OCR behind a service interface. Can swap to AWS Textract or self-hosted PaddleOCR with one provider change. |
| R08 | ABDM API breaking changes in V2 period | Medium | Medium | Follow NHA developer mailing list. Abstract ABDM calls behind adapter layer. FHIR-native data model means less rework. |
| R09 | Co-founder bandwidth conflict (KBA + HealthSync) | Medium | High | Define clear weekly time commitment in founders' agreement. HealthSync sprint work ring-fenced. |
| R10 | Play Store policy change restricting health data apps | Low | High | Monitor Google Play policy updates. DPDP Act compliance and data transparency are the best shields. |

---

*© 2026 HealthSync. All rights reserved. This document is confidential and intended for the founding team and authorised engineering advisors.*

*Document version: 1.0 | Last updated: May 2026 | Next review: After Sprint 4 (Internal Alpha)*
