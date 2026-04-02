# Notification System

## Overview

A custom Craft CMS module (`modules/notifications`) that allows parents to subscribe to push and email notifications for their children's classes. Teachers and admins can send notifications directly from the Craft CP.

## Technical Environment

- **CMS:** Craft CMS v5
- **Module:** `modules/notifications` (namespace `modules\notifications`)
- **Email Service:** Postmark (via Craft's native mailer)
- **Push Notifications:** Web Push API via `minishlink/web-push` PHP library
- **Frontend:** Vanilla JS custom element (`<tl-subscriptions>`)

---

## Database Schema

Migration: `migrations/m251210_000000_notifications.php`

### `user_class_subscriptions`
Tracks which classes each parent follows.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK | |
| `userId` | INT | FK → `users.id` CASCADE |
| `classEntryId` | INT | FK → `entries.id` CASCADE |
| `dateCreated` | DATETIME | |
| `dateUpdated` | DATETIME | |
| `uid` | CHAR(36) | |

Unique index on `(userId, classEntryId)`.

### `user_push_subscriptions`
Stores Web Push API subscription data per device.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK | |
| `userId` | INT | FK → `users.id` CASCADE |
| `endpoint` | VARCHAR(255) | Unique |
| `p256dhKey` | TEXT | |
| `authKey` | TEXT | |
| `lastUsed` | DATETIME | Updated on each use |
| `dateCreated` | DATETIME | |
| `dateUpdated` | DATETIME | |
| `uid` | CHAR(36) | |

### `magic_link_tokens`
Manages passwordless authentication tokens.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK | |
| `email` | VARCHAR(255) | |
| `token` | CHAR(64) | Unique — hex-encoded `random_bytes(32)` |
| `expiresAt` | DATETIME | 15 minutes after creation |
| `usedAt` | DATETIME | NULL until used |
| `dateCreated` | DATETIME | |
| `uid` | CHAR(36) | |

Indexes on `token` (unique), `email`, `expiresAt`.

### `notification_logs`
Tracks when notifications are sent for rate limiting.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK | |
| `userId` | INT | FK → `users.id` CASCADE — the teacher/admin who sent |
| `classEntryId` | INT | FK → `entries.id` CASCADE |
| `pushCount` | INT | Recipients reached via push |
| `emailCount` | INT | Recipients reached via email |
| `dateCreated` | DATETIME | When the notification was sent |
| `dateUpdated` | DATETIME | |
| `uid` | CHAR(36) | |

Index on `(userId, classEntryId)` and `dateCreated`.

---

## Module Structure

```
modules/notifications/
└── src/
    ├── NotificationsModule.php
    ├── controllers/
    │   ├── AuthController.php
    │   ├── SubscriptionsController.php
    │   └── NotificationsController.php
    ├── console/controllers/
    │   └── NotificationsController.php
    ├── services/
    │   ├── Auth.php
    │   ├── Subscriptions.php
    │   └── Notifications.php
    ├── models/
    │   ├── Login.php
    │   └── Subscriptions.php
    └── records/
        ├── MagicLinkToken.php
        ├── UserClassSubscription.php
        ├── UserPushSubscription.php
        └── NotificationLog.php
```

---

## Routes

### Site Routes

| Method | URL | Controller Action |
|---|---|---|
| `POST` | `notifications/auth/send-magic-link` | `AuthController::actionSendMagicLink` |
| `GET` | `notifications/auth/verify` | `AuthController::actionVerify` |
| `POST` | `notifications/subscriptions/subscribe-push` | `SubscriptionsController::actionSubscribePush` |
| `POST` | `notifications/subscriptions/unsubscribe-push` | `SubscriptionsController::actionUnsubscribePush` |
| `POST` | `notifications/subscriptions/save` | `SubscriptionsController::actionSave` |

### CP Routes

| Method | URL | Controller Action |
|---|---|---|
| `POST` | `notifications/notifications/send` | `NotificationsController::actionSend` |

### Template Routes (Craft CMS native)

| URL | Template |
|---|---|
| `/login` | `templates/login/index.twig` |
| `/login/check-email` | `templates/login/check-email.twig` |
| `/subscriptions` | `templates/subscriptions.twig` |

---

## Authentication — Magic Link Flow

Parents log in via passwordless magic link. No passwords are stored.

### Send Magic Link (`POST notifications/auth/send-magic-link`)

1. Validates the submitted email address via `Login` model rules.
2. Generates a 64-character hex token using `random_bytes(32)`.
3. Stores token in `magic_link_tokens` with a 15-minute expiry.
4. Sends an email via the `_emails/magic-link` template:
   - New users: subject "Welcome! Complete your registration for Titan Link", CTA "Complete Registration"
   - Existing users: subject "Your login link for Titan Link", CTA "Log In"
5. Redirects to `/login/check-email` on success (does not reveal whether the account exists).

Magic link URL format: `{hostInfo}/notifications/auth/verify?auth_token={token}&redirect={encodedPath}`

### Verify Token (`GET notifications/auth/verify`)

1. Reads `auth_token` from the query string.
2. Looks up the token — validates it is not expired and not already used.
3. Marks the token as used (`usedAt` timestamp).
4. Gets or creates the Craft user for that email:
   - New users: username = email, first name derived from the email prefix, assigned to the `parents` user group, activated immediately.
   - Existing users: retrieved as-is.
5. Creates a Craft session via `Craft::$app->getUser()->login($user)`.
6. Redirects to the `redirect` query param, restricted to `/` or `/subscriptions` (any other value falls back to `/`).

### Token Security

- Tokens are cryptographically random (`random_bytes(32)`).
- 15-minute expiry enforced server-side.
- Single-use — `usedAt` is set on first use; subsequent uses are rejected.
- The `cleanupExpiredTokens()` method in `Auth` service handles cleanup of expired tokens and used tokens older than 24 hours.

---

## Class Subscriptions

### Subscriptions Page (`/subscriptions`)

- Requires authentication — unauthenticated users are redirected to `/login?redirect={currentUrl}`.
- Renders the `_components/subscriptions` component with a form backed by the `Subscriptions` model.
- Displays all classes (section: `classes`) as checkboxes, labelled with class title and author names.
- Pre-checks any classes the current user is already subscribed to.
- On successful save, redirects back to `/subscriptions` with a flash success message.

### Save Class Subscriptions (`POST notifications/subscriptions/save`)

1. Requires login.
2. Validates submitted `classes[]` array (each value must be an integer).
3. Replaces all existing subscriptions for the user in a single transaction: deletes all, then re-inserts selected ones.

### Subscribe to Push (`POST notifications/subscriptions/subscribe-push`)

1. Requires login.
2. Accepts `endpoint`, `p256dhKey`, `authKey` from POST body.
3. If a record with the same `endpoint` already exists, updates `lastUsed` only.
4. Otherwise creates a new `UserPushSubscription` record.

### Unsubscribe from Push (`POST notifications/subscriptions/unsubscribe-push`)

1. Requires login.
2. Accepts `endpoint` from POST body.
3. Deletes the matching `UserPushSubscription` record for that user/endpoint pair.

---

## Push Notification Component

The `<tl-subscriptions>` custom element (backed by `src/scripts/components/subscriptions.js`) manages the browser-side push subscription lifecycle.

### Behavior on Load

1. Checks `isPushNotificationSupported()` — displays an unsupported message and hides the subscribe button if false.
2. Checks `requiresInstallForNotifications()` (iOS Safari without PWA) — displays "Add to Home Screen" instructions if true.
3. Reads `Notification.permission`:
   - `granted`: If `localStorage.unsubscribed === 'true'`, shows re-subscribe prompt; otherwise calls `subscribe()` silently.
   - `denied`: Displays browser-specific instructions to re-enable, hides subscribe button.
   - `default`: Displays a prompt to enable notifications.

### Subscribe Flow

1. Registers (or reuses) the `/sw.js` service worker.
2. Checks for an existing push subscription via `pushManager.getSubscription()`.
3. If one exists, posts it to the server to ensure it is recorded.
4. If not, calls `pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: vapidPublicKey })`.
5. POSTs `endpoint`, `p256dhKey` (base64url-encoded), and `authKey` (base64url-encoded) to `notifications/subscriptions/subscribe-push` along with a CSRF token.
6. Adds `is-active` class to the component on success.

### Unsubscribe Flow

1. Calls `registration.pushManager.getSubscription()` and calls `subscription.unsubscribe()`.
2. POSTs `endpoint` to `notifications/subscriptions/unsubscribe-push`.
3. Sets `localStorage.unsubscribed = 'true'` and reloads the page.

### VAPID Key

The public VAPID key is passed to the component via `data-vapid-public-key` attribute, populated from the `VAPID_PUBLIC_KEY` env variable in the `_components/subscriptions.twig` template.

### Service Worker (`public/sw.js`)

Handles two events:

- **`push`**: Parses JSON payload `{ title, body, url, tag }`, shows a system notification with icon `/android-chrome-192x192.png`.
- **`notificationclick`**: Closes the notification and opens `event.notification.data.url` in a new window.

---

## Admin Notification Sending (Craft CP)

### Button Injection

`NotificationsModule` listens to `Entry::EVENT_DEFINE_ADDITIONAL_BUTTONS`. The "Publish Notification" button is injected on the entry edit page when:

- The entry is not a draft and has an ID.
- The entry type handle is `class`.
- The current user is an admin. *(Note: there is a TODO in the code to also allow the class's authors once the feature is ready for launch.)*

### Send Notification (`POST notifications/notifications/send`)

CP-side AJAX action called by the admin template.

**Request parameters:**

| Parameter | Description |
|---|---|
| `entryId` | The class entry ID |
| `rateLimitPeriod` | `'day'` or `'week'` (default: `'day'`) |
| `rateLimitCount` | Max sends allowed in the period (default: `1`) |
| `message` | Optional custom message (max 120 chars) |

**Flow:**

1. Requires login.
2. Checks `notification_logs` to enforce rate limit — if the current user has already sent `rateLimitCount` notifications for this class within the `rateLimitPeriod`, returns a failure response.
3. Fetches user IDs subscribed to the class and their push subscriptions.
4. Reads the `notificationEmails` table field from the class entry (column: `email`).
5. If there are no push subscriptions and no email addresses, returns a failure.
6. Sends push notifications (if any subscribers) with payload `{ title, body, url }`.
7. Sends email notifications (if any addresses) using the `_emails/class-notification` template.
8. Logs the send to `notification_logs` with counts of successful deliveries.
9. Returns a success message with delivery counts, e.g. `"Notifications sent to 3/4 push subscribers and 2/2 email recipients."`

### Rate Limiting Logic (`Notifications::canSendNotification`)

Counts rows in `notification_logs` for the given `userId` + `classEntryId` within the period:
- `day`: `DATE(dateCreated) = CURDATE()`
- `week`: `YEARWEEK(dateCreated, 1) = YEARWEEK(NOW(), 1)`

Returns `true` if count is below `$limit`.

The CP admin template disables the button client-side if `craft.notifications.canSendNotification(currentUser.id, entryId)` returns false (uses default: 1 per day).

---

## Email Notifications

Emails are sent via Craft's native mailer (configured with Postmark).

### Magic Link Email (`templates/_emails/magic-link.twig`)

Variables: `subject`, `isNewUser`, `magicLink`, `ctaText`

Renders a simple HTML email with a large CTA button and a note that the link expires in 15 minutes.

### Class Notification Email (`templates/_emails/class-notification.twig`)

Variables: `subject`, `message`, `classUrl`

Renders an HTML email with:
- The subject as an `<h2>`
- Optional custom message in a styled blockquote
- "View Class Page" button linking to `classUrl`
- "View Portal Home" button linking to the site root

---

## Weekly Automated Reminders

### Console Command

```
php craft notifications/notifications/send
```

**Cron schedule:** Sunday 7:30 PM Eastern — the production server runs UTC, so `CRON_TZ=America/New_York` must be set in the crontab before the job entry:

```
CRON_TZ=America/New_York
30 19 * * 0 cd /srv/users/mds24pro/apps/mds24pro && php craft notifications/notifications/send
```

### Logic

For each live class entry in the `classes` section:

1. Calculates a threshold of approximately 50 hours ago (current time minus 2 days and 2 hours), representing Friday 5 PM relative to a Sunday evening run.
2. Checks `notification_logs` — if any notification was sent for this class after that threshold, skips it (a teacher already sent a notification this week).
3. Otherwise, sends both push and email notifications using the same delivery methods as the admin send action.
4. Automated reminders use the generic body: `"Click to see what's new."` with no custom message.
5. Logs the send to `notification_logs` with `userId = 1` (system user).

---

## Craft CMS Configuration Requirements

### User Groups
- **`parents`** (handle) — assigned automatically to all new users created via magic link.

### Class Entries
- **Section handle:** `classes`
- **Entry type handle:** `class`
- **`notificationEmails` field:** A table field with at minimum an `email` column — used for email notification delivery.
- **Authors field:** Used to display author names in the subscriptions form checkbox labels.

### Twig Variable Extensions

Registered on `CraftVariable::EVENT_INIT`:

- `craft.notifications` → `Notifications` service
- `craft.subscriptions` → `Subscriptions` service

---

## Environment Variables

```env
VAPID_PUBLIC_KEY=       # Web Push VAPID public key
VAPID_PRIVATE_KEY=      # Web Push VAPID private key
VAPID_SUBJECT=          # Web Push subject (e.g. mailto:admin@yourschool.com)
```

Email settings are managed through Craft's native mailer configuration (Postmark).

---

## Browser Support

| Browser | Push Notifications |
|---|---|
| Chrome (Android / Desktop) | Supported |
| Firefox | Supported |
| Safari (macOS) | Supported |
| Safari (iOS) | Requires PWA installation ("Add to Home Screen") |
| Internet Explorer | Not supported |

Unsupported browsers receive an appropriate message; the system functions without push notifications enabled (email notifications still work).

---

## Security

- **CSRF protection** on all POST requests via Craft's native `csrfInput()`.
- **Authentication required** for all subscription and notification endpoints (`requireLogin()`).
- **Open redirect prevention** in `actionVerify()` — the `redirect` param is validated against an allowlist (`/`, `/subscriptions`).
- **Cryptographically secure tokens** — `random_bytes(32)` hex-encoded.
- **Single-use tokens** — `usedAt` is set immediately upon validation.
- **15-minute token expiry** — enforced server-side on every verification attempt.
- **User data isolation** — push subscriptions are scoped to `userId` on both save and delete.
