# Render Backend Deployment

This repository deploys the PHP API only. The React frontend is maintained in a separate repository and is intentionally excluded from this backend repository and Docker image.

## Before Deploying

- Create an external MySQL database. The application uses MySQL-specific schema and SQL; Render's managed PostgreSQL database is not a drop-in replacement.
- Create a private S3-compatible bucket for request attachments and homepage images. Render's container filesystem is ephemeral, so local uploads are not durable.
- Prepare the production Google OAuth client ID, SMTP app password, PayMongo keys, and database/S3 connection details. Enter secrets only in Render's environment or secret-file settings, never in GitHub or this file.

## Deploy

1. In Render, create a Blueprint from the `Docuquest-Backend` GitHub repository. Render reads `render.yaml`, builds `Dockerfile`, and checks `/healthz`.
2. Set `APP_BASE_URL` to the backend's HTTPS origin, for example `https://docuquest-backend.onrender.com`.
3. Configure the external MySQL connection: `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, and `DB_NAME`. Keep `DB_DRIVER=mysql`. If the provider requires a CA certificate, add it as a Render secret file and set `DB_SSL_CA` to its mounted path, such as `/etc/secrets/mysql-ca.pem`.
4. Configure S3: set `DOCUMENT_STORAGE_BUCKET`, `DOCUMENT_STORAGE_REGION`, and the access keys. For a compatible provider such as Cloudflare R2, set `DOCUMENT_STORAGE_ENDPOINT`; endpoint path-style mode defaults to enabled when the variable is blank. Keep the bucket private.
5. In Render, review the generated `APP_KEY`, `JWT_SECRET`, and `REFRESH_TOKEN_KEY`. Keep them stable after users have accounts; rotating JWT/refresh secrets invalidates active sessions.
6. Configure `SMTP_USERNAME` and `SMTP_PASSWORD` with the sender account and an app password. Add the PayMongo secret and webhook signing secret when payment processing is enabled.
7. Set `CORS_ALLOWED_ORIGINS` to the exact HTTPS origin of the separately deployed frontend, with no trailing slash. For example: `https://docuquest-frontend.onrender.com`. Do not use `*`.
8. Run `app/database/docuquest.sql` once against the production MySQL database. For an existing schema, apply only the outstanding migrations listed in `app/README.md`. Create the first authorized admin through a controlled database operation; do not put account passwords in this repository.
9. Register `https://<backend-host>/api/paymongo/webhook` in PayMongo when payments are enabled.

## Frontend Connection

When deploying the separate frontend, set its build-time `VITE_API_BASE` to `https://<backend-host>/api`. Add the frontend's exact HTTPS origin to the backend's `CORS_ALLOWED_ORIGINS`, then redeploy the backend. Google OAuth authorized JavaScript origins should include the frontend origin.

## Environment Variables

`render.yaml` declares the application settings and marks values that must be entered privately as unsynchronized variables. Render generates the application and token secrets. Never commit a populated `.env`, a database CA/private key, AWS key, SMTP password, PayMongo secret, or OAuth client secret. The Google web client ID is public configuration, but should still be configured through Render rather than hardcoded.

`/healthz` is a liveness check only; it does not verify the database or S3 connection. Check Render logs after deployment and test registration, login, email, payments, and private file upload/download after configuring their external services.