# Email settings (Admin)

How to configure outgoing SMTP so the lab can email customers — without editing the server `.env` file.

**Preferred path:** **Admin → Email settings** (`/admin/email-settings`).

Server fallback and deploy notes: [`DEPLOY.md`](DEPLOY.md).

## What this is for

When SMTP is configured and enabled, the system can send:

- **Request received** — after customer intake
- **Results ready** — when results are released
- **Password reset** and other staff mail that uses Laravel’s mailer
- **Test email** — from this admin page

Customer emails are queued (`SendCustomerEmail`). A failed SMTP send is logged; it does **not** undo intake, release, or other lab workflow.

## Who can open it

Staff with the **admin** role. In the sidebar: **Email settings**.

## Step-by-step setup

### 1. Turn on admin SMTP

1. Open **Admin → Email settings**.
2. Check **Use these SMTP settings**.

If this box is **unchecked**, the server’s `.env` `MAIL_*` values are used instead. If that mailer is `log`, messages are only written to the log file and **not** delivered to anyone.

### 2. Fill in the server fields

| Field | Meaning | Typical values |
|-------|---------|----------------|
| **SMTP host** | Mail server hostname | See examples below |
| **Port** | SMTP port | `587` (STARTTLS) or `465` (SSL) |
| **Encryption** | Connection security | **STARTTLS (587)**, **SSL/TLS (465)**, or **None** |
| **Username** | SMTP login (usually the mailbox address) | `lab@yourdomain.com` |
| **Password / app password** | SMTP password | Leave blank to keep a password that is already saved |
| **From address** | Address customers see as the sender | Must be allowed by your provider |
| **From name** | Display name next to From | e.g. `NPPC Analytical & Diagnostic Laboratory` |

The password is stored encrypted using the app’s `APP_KEY`. Do not rotate `APP_KEY` casually — if you do, re-enter the SMTP password here.

### 3. Save

Click **Save settings**. You should see a success message asking you to send a test email.

### 4. Send a test email

1. In **Send test email**, enter a real inbox you can open (defaults to your staff email).
2. Click **Send test email**.
3. Confirm the message arrives (and check spam if needed).

Status on the page:

- **Configured · last test OK** — good to go
- **Configured · last test failed** — read the error under the test form
- **Configured · not tested** — save succeeded; still send a test
- **Not configured** — enable SMTP and fill required fields

**Recent email activity** on the same page lists the latest sends (test, request received, results ready, etc.) and their status.

## Example: Microsoft 365 / Outlook

| Field | Value |
|-------|--------|
| SMTP host | `smtp.office365.com` |
| Port | `587` |
| Encryption | STARTTLS (587) |
| Username | Full mailbox, e.g. `nppclab@yourdomain.com` |
| Password | Mailbox password or app password (per org policy) |
| From address | Same mailbox (or an alias your tenant allows) |
| From name | Lab display name |

Your Microsoft 365 admin may need to allow SMTP AUTH for that mailbox.

## Example: Gmail / Google Workspace

| Field | Value |
|-------|--------|
| SMTP host | `smtp.gmail.com` |
| Port | `587` |
| Encryption | STARTTLS (587) |
| Username | Full Gmail / Workspace address |
| Password | A Google **App Password** (not your normal login password), if 2-Step Verification is on |
| From address | Same address |
| From name | Lab display name |

Personal Gmail and Workspace policies differ; Workspace admins may restrict SMTP.

## Where mail shows up in the lab

- **Receiving** and **History** job pages list customer email attempts for that job order.
- Admins / receiving can **Resend** a logged email when needed (`POST /email-logs/{id}/resend`).
- Intake without a customer email is skipped and recorded as skipped — not treated as a hard failure.

## Troubleshooting

| Symptom | What to check |
|---------|----------------|
| Test fails immediately | Host, port, and encryption must match the provider (587 + STARTTLS vs 465 + SSL). |
| Authentication failed | Username is the full email; use an app password if the provider requires it. |
| Works after save, then fails later | Password field left blank keeps the old secret — re-enter if you changed the mailbox password. Or `APP_KEY` was rotated — re-enter the SMTP password. |
| Customer emails never arrive | Queue worker must be running (Supervisor `queue:work`). Test email on this page is sent immediately; customer mail goes through the queue. See [`DEPLOY.md`](DEPLOY.md). |
| Nothing is delivered at all | **Use these SMTP settings** is off and `.env` has `MAIL_MAILER=log`. Enable admin SMTP or set a real SMTP mailer in `.env`. |
| Firewall / hosting blocks SMTP | Outbound ports 587 or 465 must be allowed from the server. |
| Status OK but inbox empty | Check spam; confirm From address is allowed by the provider. |

## Related

- Deploy / `.env` fallback / queue worker: [`DEPLOY.md`](DEPLOY.md)
- Workflow overview (customer emails at intake and release): [`SYSTEM_OVERVIEW.md`](SYSTEM_OVERVIEW.md)
