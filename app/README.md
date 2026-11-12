# DocuQuest

DocuQuest uses LavaLust as its backend and React as its only UI. React source lives in `frontend/`; the Vite build is emitted to `public/react/`, and LavaLust serves that build at `/`. The PHP views are no longer part of the active page flow. All account, request, status, file, message, and notification operations go through same-origin `/api/*` routes. The React client polls updates and notifications every five seconds.

## Setup

1. Create the MySQL schema by running `app/database/docuquest.sql` in Navicat or your MySQL client. For an existing database, apply any outstanding SQL migrations under `app/database/migrations/` once.
2. Copy `.env.example` to `.env` and configure `DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, and `DB_NAME`. Set `APP_BASE_URL` only if automatic URL detection is not correct. For Aiven, configure `DB_SSL_CA` with the downloaded CA certificate path.
3. Generate JWT secrets with `php lava jwt:generate`. This fills `JWT_SECRET` and `REFRESH_TOKEN_KEY` in `.env`; keep them private and do not replace them after issuing tokens unless you intend to invalidate all sessions.
4. For Google sign-in, create a Web OAuth client ID in Google Cloud Console. Configure the OAuth consent screen and add your local and production site origins under **Authorized JavaScript origins** (for example, `http://127.0.0.1:5173` and your deployed HTTPS origin). No redirect URI is needed for the popup flow. Set `GOOGLE_CLIENT_ID` in the project `.env` and the same value as `VITE_GOOGLE_CLIENT_ID` in `frontend/.env.local` (copy `frontend/.env.example`). During testing, add test accounts to the consent screen. The client ID is public; never put a client secret in the frontend.
5. Run `composer install` from the project root to install the Gmail SMTP mailer and AWS SDK used by cloud document storage.
6. To email requesters when admins update progress, set `SMTP_USERNAME` to the sending Gmail address and `SMTP_PASSWORD` to a Google App Password. Google requires 2-Step Verification to create an App Password. Keep it private in `.env`; do not use your regular Gmail password.
7. Install the refresh-token table by running `app/database/migrations/002_refresh_tokens.sql` once. The framework API helper uses this table to rotate and revoke refresh tokens.
8. For an existing database, run `app/database/migrations/003_request_files.sql` once to add private attachment metadata. Fresh databases already get this table from `app/database/docuquest.sql`.
9. For an existing database, run `app/database/migrations/004_request_release_details.sql` once to add progress requirements and release scheduling. Fresh databases already get these nullable fields from `app/database/docuquest.sql`.
10. For an existing database, run `app/database/migrations/005_request_message_images.sql` once to add private message image metadata. Fresh databases already get this table from `app/database/docuquest.sql`.
11. For an existing database, run `app/database/migrations/006_homepage_settings.sql` once to add admin-managed homepage content and image settings. Fresh databases already get this table from `app/database/docuquest.sql`.
12. For an existing database, run `app/database/migrations/007_create_payment_table.sql` once to add the request payment table. Fresh databases already get this table from `app/database/docuquest.sql`.
13. For an existing database, run `app/database/migrations/009_request_feedback.sql` once to add requester process ratings. Fresh databases already get this table from `app/database/docuquest.sql`.
14. For an existing database, run `app/database/migrations/010_request_requirement_uploads.sql` once to support individually uploaded files for each admin-set requirement. Fresh databases already get these columns from `app/database/docuquest.sql`.
15. For an existing database, run `app/database/migrations/011_requirement_upload_reviews.sql` once to add pending, validated, and rejected states for requirement uploads. Previously verified requirement uploads are backfilled as validated. Fresh databases already get these columns from `app/database/docuquest.sql`.
16. For an existing database, run `app/database/migrations/012_detailed_request_feedback.sql` once to add five separate requester experience ratings. Fresh databases already get these columns from `app/database/docuquest.sql`.
17. For an existing database, run `app/database/migrations/013_arta_request_feedback.sql` once to store the expanded Citizen Satisfaction Measurement responses. Fresh databases already include this field in `app/database/docuquest.sql`.
18. Install and build React from the project root:

	```sh
	cd frontend
	npm install
	npm run build
	```

The build is ignored by version control and must be generated before deploying the PHP site. Configure Apache/Laragon to use `public/` as the document root.

## Local Development

Run the LavaLust server from the project root:

```sh
php lava run 8000
```

In another terminal, run Vite:

```sh
cd frontend
npm run dev
```

Open the Vite URL it prints, normally `http://127.0.0.1:5173`. Vite proxies `/api` to the Laragon URL configured in `frontend/vite.config.js`. For the built app, open `http://127.0.0.1:8000`.

Uploaded files are restricted to PDF, JPG, and PNG, with up to 3 files at 2 MB each. Local mode stores files under `runtime/uploads/request-files/`, outside the public document root. To use AWS S3 or an S3-compatible provider such as Cloudflare R2 or Supabase Storage, set `DOCUMENT_STORAGE_DRIVER=s3`, the bucket and region, and `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` in `.env`. Set `DOCUMENT_STORAGE_ENDPOINT` to the provider's S3-compatible endpoint; path-style requests are enabled by default for custom endpoints. Use `DOCUMENT_STORAGE_PATH_STYLE=false` to disable them when needed. Keep the bucket private: file metadata is listed only through `/api/admin/files`, and request/message file contents are served through authenticated API routes that require the admin role. Existing local files remain local when switching drivers; copy them to the configured bucket before switching an existing deployment.

For PayMongo Hosted Checkout, set `PAYMONGO_SECRET_KEY` and `PAYMONGO_WEBHOOK_SECRET` in the server `.env`, and choose enabled account methods in `PAYMONGO_PAYMENT_METHODS` (default: `gcash,card,qrph`). Register `https://your-domain/api/paymongo/webhook` in the PayMongo dashboard for the `checkout_session.payment.paid` event and use the signing secret shown for that endpoint. Keep both secrets out of frontend configuration. Admins set the request fee from its detail panel; requesters pay from that same panel. PayMongo's signed webhook and server-side checkout verification update the payment row. The API refuses `Ready for release` and `Completed` until the matching amount is confirmed paid.

## API

Protected routes use `Authorization: Bearer <access_token>`. Public registration always creates a regular user; an authorized staff account can be promoted directly in MySQL for initial setup with `UPDATE users SET role = 'admin' WHERE email = 'staff@example.gov';`. Admins can create and list additional admin accounts in Settings. Admins sign in at `/admin`; the React route and protected API endpoints reject non-admin accounts.

| Method | Route | Purpose |
|---|---|---|
| POST | `/api/create` | Register a requester |
| POST | `/api/login` | Sign in and issue access/refresh tokens |
| POST | `/api/google-login` | Verify a Google ID token and issue DocuQuest tokens for an active requester |
| POST | `/api/logout` | Revoke the refresh token |
| POST | `/api/refresh` | Rotate tokens |
| GET | `/api/profile` | Current account |
| GET | `/api/dashboard` | Requests, totals, statuses, and document types |
| GET | `/api/admin/accounts` | Admin-only list of administrator accounts |
| POST | `/api/admin/accounts` | Admin-only creation of an administrator account; requires name, email, password, and password confirmation |
| GET | `/api/admin/archive?q=&status=&document_type=&date=` | Admin-only searchable archive of past requests; optional date uses `YYYY-MM-DD` in Asia/Manila |
| GET | `/api/admin/feedback` | Admin-only compiled requester ratings, average, distribution, and comments |
| GET | `/api/admin/files?q=` | Admin-only searchable list of request documents and message images, grouped by requester |
| POST | `/api/admin/requests/payment` | Admin-only set or update the amount due for a request; emails payment instructions |
| POST | `/api/paymongo/webhook` | Verify PayMongo's signed `checkout_session.payment.paid` event |
| POST | `/api/requests/delete` | Admin-only permanent deletion of a previous request and its related database records |
| POST | `/api/requests` | Submit a document request |
| POST | `/api/requests/payment/checkout` | Requester starts or resumes PayMongo checkout for an assigned fee |
| GET | `/api/requests/payment/confirm?request_id={id}` | Verify payment with PayMongo after returning to the request page |
| GET | `/api/requests/show?id={id}` | Request details, activity, and messages |
| POST | `/api/requests/feedback` | Submit one 1–5-star rating and optional process comment for an owned completed request |
| GET | `/api/requests/file?id={file_id}` | Authorized request file download for the request owner or an admin |
| POST | `/api/requests/status` | Admin status update |
| POST | `/api/requests/requirements/upload` | Requester uploads one PDF, JPG, or PNG for an individual active requirement |
| POST | `/api/requests/requirements/verify` | Admin verifies or un-verifies a requirement after its matching file is uploaded |
| GET | `/api/requests/status-feed?id={id}` | Current request status |
| GET | `/api/requests/messages?id={id}` | Request conversation |
| GET | `/api/requests/message-file?id={file_id}` | Admin-only message image content for preview |
| POST | `/api/requests/message` | Send a conversation message |
| GET | `/api/notifications` | Current account notifications |
| POST | `/api/notifications/read` | Mark notifications as read |
| POST | `/api/notifications/delete` | Delete one notification belonging to the signed-in account |

Request submissions require a Philippine mobile number in `+639XXXXXXXXX` format and a current-address value selected from Naujan's 71 barangays. The client displays `+63` as a fixed prefix and accepts exactly 10 digits after it; the LavaLust API enforces both values independently of the UI.

The admin's **Today's requests** queue shows only submissions from the current Asia/Manila calendar day, oldest first for a fair intake order. At Manila midnight, those requests automatically appear under **Previous requests**; records and attachments are not moved or deleted. The archive groups requests by submission date, shows Manila-local date and time, and supports search, status, document type, and past-date filters. Requesters continue to see their full request history. Admin queue, archive, and notifications refresh every five seconds while the workspace is open.

When an admin saves a progress update, LavaLust records the note, release requirements, and scheduled release in the request and its activity history, then emails the requester. Marking a request **Ready for release** requires a future release appointment. The API response tells the admin whether SMTP delivery succeeded; on failure, the progress update remains saved and the API reports that email needs attention.

The external LavaLust API Tester can use `http://127.0.0.1:8000/api` as its base URL for the matching create, login, logout, profile, and refresh actions. Its generic user-list/update/delete actions are not part of DocuQuest's public API.

For production, serve the site over HTTPS, keep `.env` and database certificates out of version control, and grant admin access only to authorized staff.

## Google Sign-In Flow

The Google button appears on requester sign-in only. Google Identity Services returns an ID token to React, which posts it to `/api/google-login`. PHP verifies that token with Google's token-info endpoint, checks the configured audience, issuer, expiry, and verified email, then finds or creates a regular requester account. Existing accounts are matched only by Google's verified email; inactive and staff accounts are rejected. DocuQuest then issues its normal access and refresh tokens, so the rest of the app continues using the existing API authentication flow. Google credentials are never stored by DocuQuest.
