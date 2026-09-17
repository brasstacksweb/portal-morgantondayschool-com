# Titan Fund — Implementation Plan

**Status:** Draft — awaiting approval
**Date:** 2026-09-17

Adds a Titan Fund giving module to the portal: a progress banner on the homepage
showing dollars raised against the annual goal and family participation, a
donation dialog that hands off to Stripe Checkout, and a one-screen confirmation
with a confetti animation on return.

Offline gifts (checks, cash) are recorded by staff in the CMS. Online gifts are
recorded by the site from Stripe. The banner sums both.

---

## Requirements (confirmed)

| Decision | Choice |
|---|---|
| Campaign container | **A `titanFund` channel section, one entry per school year.** `postDate`/`expiryDate` define the live window, so the banner appears and disappears on its own (see Why a channel, not a global set). |
| Offline gifts | **A `craft\fields\Table` field on the campaign entry** — one row per gift. No custom table, no CRUD screens. Matching the existing `emails` Table field precedent. |
| PII in the CMS | **None.** Offline rows carry date, amount, and a free-text description only. |
| Online gifts | **Custom table `{{%titanfund_donations}}`**, written by Stripe. Stores amount + a hashed family key; **no donor name or email** (see Where donor PII lives). |
| Who can see gift records | New **`titanFundStaff`** user group. The offline gifts field is hidden from everyone else via the field layout's `userCondition` — no custom permission code. |
| Progress metric | **Dollars raised vs. goal, plus family participation** ("X of Y families"). |
| Gross vs. net | **Gross.** The amount charged is what counts, including a covered processing fee. No fee columns in v1. |
| Pledges | **Not modeled.** Every recorded gift is a paid gift. |
| Over goal | **Shown and celebrated.** The bar caps visually at 100% with a `goal-met` state; the percentage keeps climbing and the donate CTA stays prominent. |
| Campaign end | **No thank-you state.** The entry expires and the banner stops rendering. |
| Placement | **Homepage only**, stacked under the existing red `.home-banner`, on a white background. Built as two components so the button + dialog can be lifted onto other pages later. |
| Payment flow | **Stripe Checkout (hosted).** Plain POST form → server creates a Session → redirect to Stripe. **No Stripe.js, no publishable key, no card fields on this site.** |
| Amounts | CMS-editable presets **plus** an "other amount" field. No policy minimum or maximum (technical guards only). |
| Cover the fee | **Yes**, an opt-in checkbox that grosses the charge up. |
| Recurring gifts | **Out of scope.** One-time only. |
| Donor fields | **Family name and email, collected by Stripe Checkout** — not by a form on this site. |
| Logged-in parents | `userId` is attached to the gift when a parent is signed in. Donating does **not** require a login. |
| Receipts | **Stripe's receipt email only.** No Postmark send. Revisit if the school needs specific acknowledgement language. |
| Anti-abuse | **No reCAPTCHA.** Stripe Radar covers it; the endpoint only creates a Checkout Session. |
| Progress data delivery | **Client fetch** from `/json/titan-fund-progress`, so the cached homepage stays cached. |
| Admin reporting | **Stripe dashboard.** No CP donor list, no CSV export. |
| New dependencies | `stripe/stripe-php` (Composer) and `canvas-confetti` (npm — the project's first runtime JS dependency). |
| Out of scope for v1 | Recurring gifts, pledges, tribute/honoree fields, donor CP screens, CSV export, matching gifts, multi-campaign support, donation history for parents, net/fee reporting. |

---

## Architecture summary

A new `titanfund` module shaped like `modules/athletics`. The campaign and its
offline gifts are **CMS content** (an entry, staff-editable); online gifts are
**application data** (a custom table, written by Stripe).

```
Titan Fund Campaign (entry, section titanFund, no URL — "Titan Fund 2026–27")
 ├─ goalAmount      ──> whole dollars (reused positiveInteger field)
 ├─ familyCount     ──> participation denominator (reused positiveInteger field)
 ├─ giftPresets     ──> Table: preset donation amounts
 ├─ offlineGifts    ──> Table: date · amount · description · repeat gift
 │                       (visible only to titanFundStaff)
 └─ postDate/expiryDate ──> the live window; status('live') picks the campaign
      │
      └─ titanfund_donations rows ──> online gifts, one per Stripe payment
                                       (amountCents, familyKey, userId?)
```

Totals are the sum of the two sources, computed in one service and cached for 60
seconds:

```
raised       = SUM(offlineGifts.amount) + SUM(donations.amountCents WHERE status='paid')
familiesGiven = COUNT(offlineGifts rows WHERE not repeatGift)
              + COUNT(DISTINCT donations.familyKey WHERE status='paid')
```

### Why a channel, not a global set

A global set would have been marginally simpler to author, but the CP work is
nearly identical (a field layout either way) and the channel buys three things
for free:

- **History.** Last year's goal, offline gifts, and total stay intact instead of
  being overwritten each July.
- **An automatic on/off switch.** `postDate` and `expiryDate` already express
  "when is this campaign live," and `.status('live')` resolves it. Athletics
  needed `registrationOpens`/`registrationCloses` fields for the same idea;
  here Craft's native entry window covers it, so the banner turning itself off
  at year end costs zero fields and zero code. This is also what satisfies "no
  thank-you state" — there is simply nothing to render.
- **A meaningful `campaignEntryId`** on each online gift, so a gift is attached
  to the year it was given rather than inferred from a date range.

The cost is one extra section + entry type in project config, and staff must
remember to create next year's entry. The seasonal workflow below covers that.

### Why offline gifts are a Table field and not a table

A custom table would mean building a CP screen for staff to add a check, because
`craft\db\ActiveRecord` gets no admin UI. A Table field gets a row editor, a
field layout slot, versioning, and per-group visibility for free — and staff are
already in the CP editing the homepage.

The tradeoff is that offline gifts are content, not queryable rows: the totals
service loads the campaign entry and sums an array in PHP. At school scale
(dozens to low hundreds of rows per year) that is irrelevant. If the row count
ever became a problem, the migration path is a custom table plus a CP screen,
and the service interface would not change.

### Where donor PII lives

All of it stays in Stripe. Checkout collects the email (natively) and the family
name (via a Checkout `custom_fields` text input), and Stripe sends the receipt.
This site stores neither.

For participation counting the site needs to know whether two gifts came from the
same household, which it does with a **`familyKey`**: `sha256` of the normalized
email, mirroring `Signups::participantKey()`. It supports distinct-family counts
and deduping, and it cannot be read back into an address list.

Consequence to accept: a family that gives online **and** by check is counted
twice in participation unless staff tick the "repeat gift" box on the offline
row. That box is the manual override for both cases (same family giving twice
offline, or a family already counted online).

### Why Checkout and not the Payment Element

Checkout is a redirect, so this site never loads Stripe.js, never renders a card
field, and needs **no publishable key** — only a secret key and a webhook
secret. It also brings Apple/Google Pay, receipt emails, and Stripe's own
validation and localization. The donation dialog reduces to a plain POST form
with radio buttons, so it needs **no JavaScript at all** (same shape as the
athletics signup panel's plain POST forms).

The confetti moment survives the redirect: Stripe returns the donor to
`/?donation=success&session_id=...`, and the front end confirms that session and
celebrates.

### Why the donate form does not reuse `_components/form.twig`

Two reasons:

1. **The preset UX does not fit the generic field renderer.** Radio buttons for
   preset amounts plus an "other amount" number input is bespoke markup, not a
   list of `attributeTypes()`.
2. **`modules\components\models\Form::rules()` carries the reCAPTCHA rule.**
   Athletics does `array_merge(parent::rules(), ...)`, which is what pulls
   reCAPTCHA into every existing form. Since we deliberately skip reCAPTCHA
   here, `models\Donation` still **extends** the base Form (for `validateHash()`
   and the action/redirect path contract) but **overrides `rules()` without
   merging the parent's**, with a comment saying so.

This also keeps the homepage free of the reCAPTCHA script — `loadForm` stays
`false` on `index.twig`.

### Why the banner shell sits outside `{% cache %}`

The donate form contains `csrfInput()`, and a cached CSRF token is a stale CSRF
token. So the `fund-progress` include goes **outside** the homepage's
`{% cache if not devMode %}` block. That is one extra entry query per homepage
view, which is cheap.

That also removes the original reason to fetch the numbers on page load. Once the
banner is outside the cache, a server-rendered bar is strictly better: no empty
state, no layout shift, and correct figures with JavaScript disabled. So the bar
renders server-side at its true value, and the JSON endpoint exists for the one
case that genuinely needs it — re-reading the total after a donation is
confirmed, without a page load, so the donor sees their own gift move the bar
under the confetti.

The agreed outcome is unchanged: the homepage stays cached, and the figures are
never stale.

Because the bar is server-rendered, the CSS width transition only fires when the
bar is updated in place. The refresh therefore mutates the existing bar's
`--progress` custom property and label text rather than replacing the markup,
which is what lets the fill animate.

The announcement banner (`.home-banner`) moves outside the cache block along with
it — the Titan Fund banner stacks underneath it, and the two have to live in the
same uncached region. Side effect worth knowing: an urgent announcement now
appears immediately instead of waiting for the homepage cache to clear.

---

## 1. CMS configuration (`config/project/`)

All of this is authored in the dev CP (`CRAFT_ALLOW_ADMIN_CHANGES=true`) and
ships as project config YAML, since production has admin changes disabled.

### New fields

| Field | Type | Notes |
|---|---|---|
| `giftPresets` | `craft\fields\Table` | One column: `amount` (number, 0 decimals, min 1). Default rows 25 / 50 / 100 / 250. `minRows: 1`. |
| `offlineGifts` | `craft\fields\Table` | Columns below. `addRowLabel: 'Add a gift'`. |

`offlineGifts` columns:

| Handle | Heading | Type | Notes |
|---|---|---|---|
| `date` | Date | `date` | When the gift was received. Display only in v1. |
| `amount` | Amount | `number` | Whole dollars, min 1. Converted to cents by the service. |
| `description` | Description | `singleline` | Free text for staff — "Fall appeal check", "Spring gala". **No donor names.** |
| `repeatGift` | Repeat gift | `lightswitch` | On = do not count toward family participation (already-counted family). |

### Reused fields — no new field needed

| Handle on the entry type | Existing field | Use |
|---|---|---|
| `goalAmount` | `positiveInteger` (`craft\fields\Number`) | Goal in whole dollars. |
| `familyCount` | `positiveInteger` | Participation denominator. Left empty = participation is not shown. |
| `heading` | `text` (PlainText) | Banner headline, e.g. "2026–27 Titan Fund". |
| `overview` | `overview` (CKEditor) | Short banner copy and/or dialog intro. |

### New section and entry type

**Section `titanFund`** — channel, no URLs, versioning on, `propagationMethod: all`.

**Entry type `titanFundCampaign`** — `hasTitleField: true` (staff name it
"Titan Fund 2026–27"), icon `hand-holding-heart`. Field layout, one Content tab:

```
heading        (100%)
overview       (100%)
goalAmount     (50%)
familyCount    (50%)
giftPresets    (100%)
offlineGifts   (100%)  ← userCondition: user is in group titanFundStaff
```

The `offlineGifts` slot uses the field layout element's **`userCondition`** (the
`userCondition:` key already present throughout the committed entry type YAML) so
gift rows are invisible to staff outside the group. Admins pass all conditions.

### New user group

**`titanFundStaff`** — mirrors `athleticsStaff`. Permissions: edit entries in the
`titanFund` section.

**Craft admins are not exempt from a field's `userCondition`.**
`FieldLayoutComponent::showInForm()` evaluates the condition against the current
user with no admin bypass, so an admin who is not in `titanFundStaff` cannot see
the offline gifts field either. Admins who need to record gifts must be added to
the group. (The `class` entry type sidesteps this by conditioning on
`AdminConditionRule` instead; Craft conditions AND their rules, so "admins or
group members" is not expressible in one condition.)

### Seasonal workflow

1. Duplicate last year's campaign entry, retitle it, set the new `goalAmount`
   and `familyCount`, and clear `offlineGifts`.
2. Set `postDate` to the campaign start and `expiryDate` to the campaign end.
3. Set last year's entry's `expiryDate` to the day the new one starts (or simply
   leave it expired).

Only the live entry renders. If two are live at once the service takes the newest
`postDate` and logs a warning — a content mistake, not a data hazard.

---

## 2. Database

Migration: `migrations/m260917_000000_titanfund.php`

### `titanfund_donations`

One row per successful Stripe payment.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK | |
| `campaignEntryId` | INT NOT NULL | FK → `entries.id` CASCADE |
| `userId` | INT NULL | FK → `users.id` SET NULL — the gift outlives the account |
| `familyKey` | CHAR(64) NOT NULL | `sha256` of the normalized donor email |
| `amountCents` | INT NOT NULL | Gross amount charged, including a covered fee |
| `currency` | CHAR(3) NOT NULL | `'usd'` |
| `coveredFee` | BOOL NOT NULL | Whether the donor opted to cover the fee. Reporting only |
| `stripePaymentIntentId` | VARCHAR(255) NOT NULL | **Unique** — the idempotency guard |
| `stripeCheckoutSessionId` | VARCHAR(255) NULL | For tracing back to a Session |
| `status` | VARCHAR(16) NOT NULL | `paid` \| `refunded` |
| `dateCreated` | DATETIME | |
| `dateUpdated` | DATETIME | |
| `uid` | CHAR(36) | |

Indexes:

- unique `stripePaymentIntentId`
- `(campaignEntryId, status)` — the totals query
- `familyKey` — the distinct-family count

### Why a unique payment intent id is the whole concurrency story

Two code paths record the same gift: the Stripe webhook and the return-from-
Checkout confirmation. They can and will both fire, sometimes at once. Rather
than coordinate them, `Donations::record()` is a plain insert whose failure on
the unique index means "already recorded" — the same trick athletics uses for
duplicate participants, and the reason neither path needs a transaction.

This is also what makes the site work **before** the webhook exists: until the
school's Stripe account is available and the endpoint is configured, the
confirmation path alone records every gift where the donor lands back on the
site. The webhook then becomes purely additive insurance for donors who close
the tab.

### Why no fee columns

"Gross for now" means the charged amount is the recorded amount. Storing
`feeCents`/`netCents` would mean a second Stripe call per gift (the balance
transaction) for reporting the Stripe dashboard already does better. If net
reporting is ever wanted on-site, it is an additive migration.

---

## 3. New module code

`modules/titanfund/src/`, namespace `modules\titanfund`, registered in
`composer.json` autoload and `config/app.php` as id **`titan-fund`**.

```
TitanFundModule.php
records/Donation.php
models/Donation.php
services/Donations.php          ← totals, participation, writes (DB + entry)
services/Checkout.php           ← the only file that touches the Stripe SDK
controllers/DonationsController.php
controllers/WebhooksController.php
```

### `TitanFundModule.php`

Same shape as `AthleticsModule`: alias, controller namespace, `setComponents()`
for `donations` and `checkout`, and a `CraftVariable` binding exposing
`craft.donations`.

Unlike athletics, it registers site URL rules (athletics posts via
`actionInput()`, which needs none) because Stripe and the progress fetch need
clean URLs:

```php
$event->rules['titan-fund/checkout'] = 'titan-fund/donations/checkout';
$event->rules['titan-fund/confirm']  = 'titan-fund/donations/confirm';
$event->rules['titan-fund/webhook']  = 'titan-fund/webhooks/stripe';
```

### `services/Donations.php`

Deals in records, entries, and primitives. No Stripe.

```php
const STATUS_PAID = 'paid';
const STATUS_REFUNDED = 'refunded';
const STATE_ACTIVE = 'active';
const STATE_GOAL_MET = 'goal-met';

// Stripe's standard US card pricing. Confirm against the school's actual
// rate — nonprofits often negotiate lower.
const FEE_PERCENT = 0.029;
const FEE_FIXED_CENTS = 30;

public static function newDonation(array $attrs = []): DonationModel  // form factory
public function getCampaign(): ?Entry            // newest live titanFund entry
public function getProgress(Entry $campaign): array
public function record(array $data): bool        // idempotent insert
public function familyKey(string $email): string // sha256(normalized)
public function grossUpForFee(int $netCents): int
private function offlineTotals(Entry $campaign): array
private function onlineTotals(int $campaignEntryId): array
private function numberOrNull(mixed $value): ?int
```

`getProgress()` returns everything the banner needs, from one grouped query plus
the entry's own field data, cached 60s:

```php
[
    'goalCents' => int,
    'raisedCents' => int,
    'percent' => int,        // uncapped — 118 is a real answer
    'barPercent' => int,     // min(percent, 100), the fill width
    'state' => 'active'|'goal-met',
    'familiesGiven' => int,
    'familyCount' => ?int,   // null when the field is empty
    'participation' => ?int, // percent, null when familyCount is null
]
```

Cache key `titanfund:progress:<campaignId>`, 60s TTL, plus an explicit
invalidation inside `record()` so a fresh gift shows up immediately behind the
confetti. Entry edits are picked up within 60s; a `TagDependency` on the
campaign element is the tighter option if that lag ever annoys staff.

`grossUpForFee()`: `ceil((net + FEE_FIXED_CENTS) / (1 - FEE_PERCENT))`.

### `services/Checkout.php`

The only Stripe-aware service. Keeps the SDK out of `Donations` so the totals
logic stays unit-testable and the two concerns can fail independently.

```php
public function createSession(Entry $campaign, int $grossCents, bool $coveredFee, ?User $user): string  // returns the redirect URL
public function retrieveSession(string $sessionId): ?array   // normalized: paid?, amount, email, intent id, metadata
public function constructEvent(string $payload, string $signature): ?object
```

Session parameters:

- `mode: 'payment'`, `submit_type: 'donate'`
- one `line_items` entry via `price_data` + `product_data.name` = the campaign title
- `custom_fields`: a required `family_name` text input
- `customer_email` prefilled from `currentUser` when signed in
- `metadata` (on the session **and** `payment_intent_data`):
  `campaignEntryId`, `userId`, `coveredFee`
- `success_url`: `siteUrl('?donation=success&session_id={CHECKOUT_SESSION_ID}')`
- `cancel_url`: `siteUrl('?donation=canceled')`

Keys come from `App::env('STRIPE_SECRET_KEY')` / `STRIPE_WEBHOOK_SECRET`,
following the reCAPTCHA/VAPID precedent.

### `models/Donation.php`

Extends `modules\components\models\Form`. Attributes: `campaignId` (hashed
hidden), `amount` (preset radio value, cents), `customAmount` (dollars),
`coverFee`.

```php
public function rules(): array
{
    // Deliberately NOT array_merge(parent::rules(), ...): the base rule set
    // requires a reCAPTCHA token, and this form has none by design (Stripe
    // Radar covers abuse, and requiring it would load reCAPTCHA on the homepage).
    return [
        [['campaignId'], 'validateHash'],
        [['amount', 'customAmount'], 'integer'],
        [['coverFee'], 'boolean'],
        [['amount'], 'validateAmount'],
    ];
}
```

`validateAmount()` resolves the effective amount — `customAmount` wins when
greater than zero — and enforces **technical** guards only, not policy: at least
$1 (Stripe rejects charges under $0.50) and at most $999,999.

`getActionPath()` returns `titan-fund/donations/checkout`.

### `controllers/DonationsController.php`

`$allowAnonymous = ['checkout', 'confirm']` — giving does not require a login.

**`actionCheckout()`** — `requirePostRequest()`, validate the model, resolve the
campaign (must be the live one), gross up if `coverFee`, create the Session, and
`$this->redirect($url)`. On validation failure: flash the error and
`redirectToPostedUrl()` back to the homepage. No JSON; this is a plain form post.

**`actionConfirm()`** — GET with `session_id`, returns JSON. Retrieves the
session, requires `payment_status === 'paid'`, calls `Donations::record()`
(idempotent), and responds with the rendered thank-you markup and the amount
label. A missing or unpaid session returns a 404-ish failure so the front end
simply does not celebrate.

### `controllers/WebhooksController.php`

```php
public $enableCsrfValidation = false;   // Stripe cannot send a CSRF token
protected array|int|bool $allowAnonymous = true;
```

`actionStripe()` reads `getRawBody()`, verifies the signature, and handles
`checkout.session.completed` (record) and `charge.refunded` (mark refunded).
Returns 200 for anything handled or ignored, 400 only on a bad signature, so
Stripe does not retry on our own logic errors. Unknown event types are logged and
ignored.

**This path must be excluded from `brasstacksweb/craft-basic-auth`.** The plugin
challenges by env + request condition, so `titan-fund/webhook` needs an
exclusion in its CP settings for any environment where basic auth is on —
including this dev environment. Without it Stripe gets a 401 and retries for
days.

---

## 4. Templates

### New

**`templates/_components/fund-progress.twig`** — the banner. White background per
the design note, stacking below the red `.home-banner`. Renders the static shell
from CMS copy and leaves the numbers to the fetch:

```twig
<tl-fund-progress class="{{ handle }}" data-endpoint="/json/titan-fund-progress">
	<div data-progress>
		{# Filled by the fetch. Reserves height to avoid layout shift. #}
	</div>
	<nav>
		<button type="button" popovertarget="donate-dialog">
			{% include '_components/cta' with { title: ctaLabel } only %}
		</button>
		<dialog id="donate-dialog" popover>
			...
			{% include '_components/donate-form' with { campaign: campaign } only %}
		</dialog>
	</nav>
</tl-fund-progress>
```

The `<dialog popover>` + `popovertarget` pattern is the one athletics uses for
its signup dialog (`_components/team.twig`), so no new JS is involved in opening
it.

**`templates/_components/donate-form.twig`** — presets as radios, an always-
visible "other amount" number input, the cover-the-fee checkbox, `csrfInput()`,
`actionInput('titan-fund/donations/checkout')`, `redirectInput()`, and the hashed
`campaignId`. Kept separate from `fund-progress` precisely so the button +
dialog pair can be lifted onto other pages later without touching the banner.

The "other amount" input is always visible rather than revealed by a JS toggle,
which keeps the form zero-JS and avoids a hidden-field state machine.

**`templates/_components/fund-progress-bar.twig`** — the figures, the bar, and the
meta line. Included by both the banner and the JSON endpoint so the first render
and every refresh are byte-identical.

**`templates/json/titan-fund-progress.twig`** — mirrors
`templates/json/reminder-list.twig`: server-renders the bar markup (so currency
formatting stays in Twig) and returns it with the numbers.

```twig
{% header 'Content-Type: application/json' %}
{{ { markup: markup|spaceless, barPercent: p.barPercent, state: p.state }|json_encode|raw }}
```

Reached by Craft's template routing at `/json/titan-fund-progress`, same as the
reminder list. Not cached.

### Edited

**`templates/index.twig`** — include the banner immediately after the existing
`.home-banner` div and **outside** the `{% cache %}` block (CSRF, above):

```twig
{% set campaign = craft.donations.getCampaign() %}
...
{% if campaign %}
	{% include '_components/fund-progress' with { campaign: campaign } only %}
{% endif %}
{% cache if not devMode %}
	... existing article ...
{% endcache %}
```

The existing red `.home-banner` and its inline `{% css %}` block are left alone
— promoting it to a real component is a separate cleanup, not this feature's
business.

### Over-goal presentation

`state: 'goal-met'` adds `fund-progress--goal-met`, which switches the label to
"Goal met — 118% of our $50,000 goal" and keeps the bar full. The donate CTA does
not change, since the point is that giving stays open.

---

## 5. Styles and scripts

### Styles

- `src/styles/components/_fund-progress.scss` — `$handle: "fund-progress"`,
  white background (`--c-white`), green fill (`--c-green-dk`) on a light track
  (`--c-bg-md`), `--s-br-sm` radius, `font()` mixin for labels, `media()` for the
  narrow layout. Bar width comes from an inline `--progress` custom property and
  transitions on `.is-loaded`.
- `src/styles/components/_donate-form.scss` — preset radios as a wrapping row of
  pill buttons, the other-amount field, and the fee checkbox.
- Both registered alphabetically in `src/styles/index.scss` (after
  `components/details` and `components/form-field` respectively).

### Scripts

**`src/scripts/components/fund-progress.js`** — a `tl-fund-progress` custom
element, registered in `index.js` alongside the others. Three jobs:

1. On connect, fetch the endpoint, inject the markup, then `requestAnimationFrame`
   → add `is-loaded` so the bar animates from zero.
2. If `?donation=success&session_id=...` is present, `GET titan-fund/confirm`,
   and on success emit the existing `actions.loadModal` event with the returned
   thank-you markup (reusing the global `tl-modal`), fire the confetti, and
   re-fetch the progress so the bar ticks up with the donor's own gift included.
3. Strip the query string with `history.replaceState()` so a refresh does not
   re-celebrate. `?donation=canceled` is stripped silently.

Confetti uses `canvas-confetti`, skipped entirely when
`matchMedia('(prefers-reduced-motion: reduce)').matches`.

No changes to `events.js` are needed — `loadModal`, `openModal`, and the scroll
lock actions already exist. Adding a `confetti` action to the bus would be
indirection for a single caller.

### Dependencies

- `composer require stripe/stripe-php` — pin whatever resolves under the PHP 8.2
  platform constraint. Deployment already runs `composer install`.
- `npm i canvas-confetti` — this becomes the **first entry in `dependencies`**
  (everything today is a devDependency), bundled by the existing esbuild step.

### Environment

New in `.env` and **all three** `.env.example.*` files:

```
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
```

No publishable key: with hosted Checkout the browser never talks to Stripe
directly.

---

## 6. Build order — commit by commit

Commits 1–5 are fully testable without any Stripe access, using offline gifts
only. Nothing needs the school's account until commit 6.

### Commit 1 — Register the module

`modules/titanfund/src/TitanFundModule.php`, `composer.json` autoload,
`config/app.php`. Empty services. Verifies bootstrap and `craft.donations`.

### Commit 2 — Donations table

`migrations/m260917_000000_titanfund.php` + `records/Donation.php`. No writers
yet; `php craft up` and inspect the schema.

### Commit 3 — Content model (authored in the CP)

`giftPresets` and `offlineGifts` fields, the `titanFund` section and
`titanFundCampaign` entry type, the `titanFundStaff` group, and the
`userCondition` on the gifts field. Committed as project config YAML, plus a
real 2026–27 campaign entry with a goal, a family count, and a few test gifts.

### Commit 4 — Donations service

`services/Donations.php`: campaign resolution, offline + online totals,
participation, states, fee gross-up, and the cache. Verifiable from a Twig dump
with offline gifts only.

### Commit 5 — Banner, progress endpoint, styles

`_components/fund-progress.twig`, `_components/fund-progress-bar.twig`,
`json/titan-fund-progress.twig`, `_fund-progress.scss`, and the `index.twig`
include. No JavaScript and no donate button yet — the bar is server-rendered, and
a button whose dialog does not exist would just be a dead control. **At this
point the school can see a working progress bar driven entirely by CMS-entered
gifts** — a reasonable place to pause if Stripe access takes a while.

### Commit 6 — Stripe checkout

`stripe/stripe-php`, `services/Checkout.php`, `models/Donation.php`,
`controllers/DonationsController.php` (`checkout` only), the donate button and
dialog added to `_components/fund-progress.twig`,
`_components/donate-form.twig`, `_donate-form.scss`, env vars. Test with Stripe
test keys and card `4242 4242 4242 4242`.

### Commit 7 — Confirmation and confetti

`actionConfirm()`, the `canvas-confetti` dependency, and
`src/scripts/components/fund-progress.js` — the first JavaScript in the feature,
registered as `tl-fund-progress` in `index.js`. End-to-end: donate → redirect →
return → modal + confetti → bar animates to the new total.

### Commit 8 — Webhook

`controllers/WebhooksController.php`, the URL rule, and the basic-auth
exclusion. Tested locally with `stripe listen --forward-to
https://<dev-host>/titan-fund/webhook`. Proven idempotent by letting both the
webhook and the confirmation record the same gift.

---

## 7. Deployment

1. Merge to `main`; the GitHub Action syncs files and runs
   `composer install && php craft up && ./cachebust.sh`.
2. Add `STRIPE_SECRET_KEY` and `STRIPE_WEBHOOK_SECRET` to the production `.env`
   by hand (not in the repo). **The live secret key must not be added before the
   webhook endpoint exists**, or the first real gift may go unrecorded if the
   donor closes the tab.
3. Project config applies the new section, fields, entry type, and group on
   `php craft up`, since production has `allowAdminChanges=false`.
4. Create the live Stripe webhook endpoint for `checkout.session.completed` and
   `charge.refunded`, and copy its signing secret into `.env`.
5. Confirm basic auth (if enabled in production) excludes
   `titan-fund/webhook`. The plugin exposes this as `exceptedPaths` in its
   settings (it already lists `/site.webmanifest`), so the exclusion is a
   project-config change, not code.
6. Enable **successful payment receipts** in the Stripe dashboard — that is the
   only receipt the donor gets. Note that Stripe does not email receipts for
   test-mode payments to real addresses.
7. Have staff create the campaign entry and set `postDate`/`expiryDate`.

---

## Open questions

These do not block commits 1–5.

1. **Stripe account access**, and whether the account is already in use by
   another vendor (statement descriptor and branding are account-wide).
2. **Actual Stripe rate** for the fee gross-up. `FEE_PERCENT`/`FEE_FIXED_CENTS`
   are coded to standard US card pricing; nonprofit rates are often lower, and
   an over-estimate means donors slightly overpay the fee.
3. **Preset amounts** for the first year (the plan seeds 25 / 50 / 100 / 250, and
   staff can change them).
4. **Who maintains `familyCount`**, and whether "families" means enrolled
   households (so participation is comparable year over year).
5. **Receipt language.** If the school needs 501(c)(3) / "no goods or services"
   wording, Stripe's receipt cannot carry it and we add a Postmark
   acknowledgement — additive, using the existing `templates/_emails/` pattern.

## Future considerations

- Tribute / "in honor of" fields, via another Checkout custom field.
- A parent's own giving history, using the stored `userId`.
- Lifting the donate button + dialog onto interior pages (already structured
  for it).
- Net/fee reporting, via an additive migration and the balance transaction.
- Recurring gifts, which would mean Checkout subscription mode and a real
  customer record.
- A CP donor screen or CSV export, if Stripe's dashboard stops being enough.
