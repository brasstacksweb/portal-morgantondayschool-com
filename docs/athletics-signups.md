# Athletics Signups — Implementation Plan

**Status:** Approved
**Date:** 2026-07-22 (approved 2026-07-23)

Extends the portal so authenticated parents can register children for school
athletic teams. Each sport gets a dedicated page listing the teams currently open
for signup, with a roster, a signup form, and a panel showing the parent's own
registrations.

---

## Requirements (confirmed)

| Decision | Choice |
|---|---|
| Who is registered | A **parent registers a child**. Participant details are captured per signup and are separate from the parent's Craft user account. One parent may register multiple children — including several on the same team. |
| Where signups are stored | **Custom DB table + `craft\db\ActiveRecord`**, matching the existing `forms_contact` / `user_class_subscriptions` pattern. |
| Roster visibility | **Any logged-in parent** can view rosters for any sport and team. |
| Signup status | Two states: **`interested` · `committed`**. |
| Managing an existing signup | An **action panel**, not form resubmission. Actions are **Commit** and **Withdraw**. |
| Capacity | **Soft.** A display target, not an enforced gate — exceeding it is the signal to field a second team, not an error. |
| Rules enforced in v1 | Registration window, duplicate prevention, minimum-players visibility. |
| Content model | Three channel sections — `sports` (with URLs), `teams`, `coaches`. No nested entries, no structures. |
| Team identity | A team is **immutable**: sport + age group + season + year. Each season creates new team entries; **existing entries are never reused** (see Seasonal workflow). Old ones are disabled and archive with their rosters. |
| De-curated teams | When a team leaves the sport's `activeTeams`, it simply disappears from the front end. Its signup rows stay inert in the DB — no orphan-signup UI is rendered. |
| Coaches | **Entries, not Craft users.** Contact records reusable across teams and seasons, with an additive upgrade path to User elements later. |
| Age group | **Plain text label on the team**, not an abstraction. See "Why age group is not an element type". |
| Admin visibility | The **same roster** every parent sees, with contact columns revealed to the `athleticsStaff` user group. No separate admin surface. |
| Deferred to coach follow-up | Medical details, emergency contacts, shirt size. Collected by the coach once a roster is confirmed, not at signup. |
| Out of scope for v1 | Waitlists. Notifications of any kind. CSV export. Data pruning. Payments. Duplicate-team prevention. Reusable child profiles. Machine-checked age eligibility. |

---

## Architecture summary

A new `athletics` module following the shape of `modules/notifications`. Sports,
teams, and coaches are **CMS content** (entries, staff-editable); signups are
**application data** (custom table, like `{{%user_class_subscriptions}}`).

The signup form reuses the existing `modules\components\models\Form` base,
`templates/_components/form.twig`, and the `tl-form` custom element. The signup
panel uses plain POST forms. **No new front-end JavaScript is required.**

```
Sport (entry, athletics/soccer)
 ├─ activeTeams ──────────> curated, ordered list of what's open right now
 │
Team (entry, no URL — "Soccer — U10 Boys (Fall 2026)")
 ├─ sport      ──────────> permanent structural link back to the Sport
 ├─ coaches    ──────────> Coach entries (contact records)
 └─ athletics_signups rows ──> Craft User (parent) + participant details
```

### Why two relations between Sport and Team

`sport` (on the team) and `activeTeams` (on the sport) are **not redundant**:

- **`sport` is structural and permanent.** It is what makes past seasons
  queryable — every team ever created stays linked to its sport.
- **`activeTeams` is editorial curation.** It controls what appears on the sport
  page for signup right now, and gives staff drag-ordering.

If `activeTeams` were the only link, removing last season's team from the field
would orphan its roster from any content-driven history page. The cost of having
both is that they can disagree (a team curated onto the wrong sport's page); that
is a soft check in the template or service, not a data hazard.

### Why age group is not an element type

Different sports use incompatible schemes — U8/U10/U12 for soccer, 3rd–5th /
6th–8th for basketball, JV/Varsity elsewhere. Any shared dropdown or category
group would have to be the union of all of them, offering staff a long list where
most options are wrong for the sport they are editing, and Craft cannot scope
those options per sport.

Plain text is also low-risk here specifically: the value is scoped to one team,
displayed verbatim, and never joined on. Drift between `U10` and `U-10` costs
nothing because nothing groups by it.

### Why there is no waitlist, and why that removes the concurrency risk

If a team draws more signups than expected, the answer is to **field a second
team** — a content action — rather than to queue people behind a gate. That
decision cascades further than it first appears:

- `status` needs only two values instead of three
- no `position` column (roster order is `dateCreated`)
- no promotion logic, and no promotion notifications
- **no locking transaction**

The transaction existed solely because two parents could race for the last
*enforced* spot. Making `capacity` a soft display target removes the gate, and
with it the race. `register()` becomes a plain insert.

The one remaining concurrency case — two simultaneous submissions for the same
child — is handled by the `(teamEntryId, participantKey)` unique index at the
database level. Catch the constraint violation and return the friendly duplicate
error.

**If a hard cap is ever wanted, the transaction has to come back with it.** Soft
capacity is what makes this simplification safe.

---

## 1. CMS configuration (`config/project/`)

Build these in the CP on dev (requires `CRAFT_ALLOW_ADMIN_CHANGES=true`, which is
env-gated in `config/general.php:27`), then commit the generated YAML.

### New fields (as built)

| Field | Handle | Type | Used as |
|---|---|---|---|
| Age Group | `ageGroup` | Plain Text, single-line | Team — `U10 Boys`, `JV Girls` display label |
| Season | `season` | Dropdown (fall / winter / spring / summer) | Team |
| Start Year | `startYear` | Number, integer | Team — `2026`, rendered "2026–27" in Twig |
| Positive Integer | `positiveInteger` | Number, integer | Team — **reused twice**, as `capacity` and `minimumPlayers` (both optional) |
| Email | `email` | Email | Coach |
| Sport | `sport` | Entries → Sports, max 1 | Team — **required** |
| Teams | `teams` | Entries → Teams, no limit | Sport — added with handle override `activeTeams` |
| Coaches | `coaches` | Entries → Coaches, no limit | Team |

Two implementation notes on how these were wired:

- **`capacity` / `minimumPlayers` share one field.** A single `positiveInteger`
  Number field is added to the Team layout twice, with per-instance handle
  overrides — the same technique the existing `class` type uses for `text`. Both
  instances stay optional; the nullable behavior in §4 is unchanged.
- **`email` is an Email field, not plain text.** Stronger than the originally
  planned `emailAddress` PlainText — templates read `coach.email`.

**On `startYear` being a number.** Free text invites `2026-27` / `2026–27` /
`26/27` drift. An integer is sortable in the CP element index and lets the
service compute the current year programmatically. A dropdown would be worse — it
lives in project config, so every new school year would need a deploy.

### Reused fields — no new field needed

- `text` (multiline PlainText) — Sport `heading`; Coach name via `coachName`
- `overview` — Sport overview; Coach `bio`; Team `eligibilityNotes`
- `details` — Team `practiceInfo`
- `image` — Sport `heroImage` / `previewImage`; Coach photo
- `color` — Sport
- `phoneNumber` (single-line PlainText) — Coach
- `datetime` — Team, used **twice** as `registrationOpens` and
  `registrationCloses`

Both registration-date instances are **required** in the Team layout, along with
`sport`, `ageGroup`, `season`, and `startYear`. That guarantees every team has a
real window and a complete title, which is what removes null-handling from the
state logic (see §4) and keeps the auto-generated title intact.

The `datetime`, `text`, and `positiveInteger` reuse all rely on Craft 5 allowing
one field in a layout multiple times under different handles — the existing
`class` entry type already does this with `text`.

### New sections and entry types

**`sports`** — Channel, has URLs

- `uriFormat: 'athletics/{slug}'`, `template: 'athletics/_entry.twig'`
- `enableVersioning: true`, `propagationMethod: all`
- Entry type **`sport`** (title shown): `heading`, `heroImage`, `previewImage`,
  `overview`, `color`, `details`, `activeTeams`

**`teams`** — Channel, no URLs (rendered on the sport page)

- Entry type **`team`**: `sport` (req), `coaches`, `ageGroup` (req),
  `season` (req), `startYear` (req), `capacity`, `minimumPlayers`,
  `registrationOpens` (req), `registrationCloses` (req), `eligibilityNotes`,
  `practiceInfo`
- `hasTitleField: false`, `titleFormat: '{sport} - {ageGroup} ({season} {startYear})'`

  Because a team is immutable, an auto-generated title is safe and eliminates
  naming drift. The `{sport}` reference resolves the related entry's title.
  **Still verify at runtime** — create one team and confirm the title renders the
  sport name rather than an element ID or blank.

**`coaches`** — Channel, no URLs

- Entry type **`coach`** (`hasTitleField: false`, `titleFormat: '{coachName}'`):
  `coachName` (req, via `text`), photo (via `image`), `bio`, `email`,
  `phoneNumber`

Coach entries expose `title` (the name), `photo`, `bio`, and `email`, close to the
shape `templates/classes/_entry.twig` uses for `entry.authors`, so a "Meet the
Coaches" section can reuse `_components/media-split` directly.

### On `eligibilityNotes`

Age cutoffs vary by age group *within* a sport, so this lives on the **team**,
not the sport — a sport-level field would force one blob covering every team.
General sport-wide copy still has the existing `overview` / `details` fields.

In v1 this is display copy only. See Future Considerations for the
machine-checked version.

### Seasonal workflow

Teams accumulate at roughly *sports × age groups* per season — on the order of a
few dozen entries a year, which a channel with a filterable element index handles
comfortably. Each season, staff duplicate the previous season's team entries
("Save as a new entry"), change `season` / `startYear`, and update the sport's
`activeTeams` field. Capacity, minimum, coaches, and practice info carry over.

**Rule: never reuse an existing team entry for a new season — always duplicate.**
A new season means a new entry with a new `teamEntryId`, which keeps each season's
signups in their own key space. Re-adding last year's *same* entry to
`activeTeams` would carry its old signup rows forward, and the
`(teamEntryId, participantKey)` unique index would then block a returning child
from signing up again with a duplicate error the parent cannot see or clear. The
"duplicate to make next season" workflow above avoids this automatically; this
note just makes the constraint explicit so no one shortcuts it.

### Permissions

The existing `parents` group needs no changes (front-end only).

**User group `athleticsStaff`** (`config/project/users/groups/`) serves two roles:

1. **CP content management** — it has full create / save / delete / view entry
   permissions on the Sports, Teams, and Coaches sections, plus `accesscp`. The
   athletic director manages the athletics content directly.
2. **Front-end marker** — `canViewContact()` (§3) checks membership to reveal
   guardian contact columns on the roster. This works regardless of the group's CP
   permissions; it is a plain group-membership test.

Handled `athleticsStaff` rather than `sports`, since a user group sharing a handle
with the `sports` *section* would make `getGroupByHandle()` and `section()` calls
easy to confuse.

**Membership is a manual step.** `Auth::getOrCreateUser()` in
`modules/notifications/src/services/Auth.php` assigns every new magic-link user to
`parents`. An athletic director signing in through the magic-link flow therefore
lands in `parents`, and someone must add them to `athleticsStaff` in the CP. Worth
knowing before anyone debugs "why can't they see phone numbers." (An AD who
instead logs in at the CP with a password is assigned directly.)

---

## 2. Database

New migration: `migrations/m260723_000000_athletics.php`

```
{{%athletics_signups}}
  id                     primaryKey
  userId                 integer notNull   FK → {{%users}}   CASCADE
  teamEntryId            integer notNull   FK → {{%entries}} CASCADE
  participantKey         char(64) notNull  sha256(lower(trim(first|last|dob)))
  participantFirstName   string notNull
  participantLastName    string notNull
  dateOfBirth            date notNull
  guardianEmail          string notNull    -- prefilled from the account, stored as a snapshot
  guardianPhone          string notNull
  interestedInCoaching   boolean notNull default false
  status                 string(16) notNull default 'interested'   -- interested | committed
  dateCreated / dateUpdated / uid

INDEXES
  unique (teamEntryId, participantKey)   -- duplicate prevention, and the only concurrency guard needed
  (teamEntryId, status)                  -- roster and count queries
  (userId)
```

### Notes on three choices worth reviewing

**`dateOfBirth`, not age.** A stored age is wrong within a year, and age cutoffs
are evaluated *as of a specific date*, which is only computable from a date of
birth. Age is derived for display.

**`participantKey`** is what makes duplicate prevention indexable. A unique index
across four string columns is brittle and long; a single hash column is neither.

There is deliberately **no `sportEntryId`**. Everything queries by `teamEntryId`,
and a team knows its sport through its `sport` relation, so a per-sport grouping
(e.g. a future export) is one lookup away. A denormalized sport column would be
written on every insert and never read.

**`guardianEmail` is a stored snapshot,** prefilled from `currentUser.email` but
not read from it at display time. The account email is a login identity; the
day-to-day contact may be a different parent or a grandparent holding the
account. A roster contact sheet needs what was entered.

**Withdrawal hard-deletes the row**, which keeps the unique index honest. The
deletion is written to the Craft log for audit.

### On `interestedInCoaching`

This is a **parent** attribute stored on a **participant** row, so a parent with
three children ticks it three times and can in principle contradict themselves.
That is an accepted tradeoff — the only real use is pulling a volunteer contact
list, which dedupes by `userId`.

**Do not read the raw column count as "number of volunteers."**

---

## 3. New module code

```
modules/athletics/src/
├── AthleticsModule.php
├── controllers/SignupsController.php
├── models/Signup.php
├── records/Signup.php
└── services/Signups.php
```

Also required:

- `composer.json` autoload entry `"modules\\athletics\\": "modules/athletics/src/"`,
  then `composer dump-autoload`
- Registration in the `$modules` array in `config/app.php:33`, alongside the
  existing four modules

### `AthleticsModule.php`

Mirrors `modules/notifications/src/NotificationsModule.php`, minus the console
and CP hooks:

- `setComponents(['signups' => Signups::class])`
- Site URL rules for `athletics/signups/save`, `athletics/signups/commit`, and
  `athletics/signups/withdraw`
- `CraftVariable` registration → `craft.signups.*` available in templates
  (variable name matches the component name, per the notifications module)

### `models/Signup.php` — extends `modules\components\models\Form`

Extends the base `Form` (not `forms\models\Form`), matching the notifications
`Subscriptions` / `Login` models: it needs `validateHash`, `validateRecaptcha`,
and the `attributeTypes/Sizes/Options/Labels` accessors, but none of the
`saveLocal` / `send*` abstract methods the forms base would require.

**The model validates shape only.** Business rules — team exists / is open / not a
duplicate — live in the controller, matching how `AuthController` checks
`canRequestToken` rather than baking it into the `Login` model. This keeps the
model decoupled from the service and the `entries` table.

Fields: `participantFirstName`, `participantLastName`, `dateOfBirth`,
`guardianEmail`, `guardianPhone`, `status`, `interestedInCoaching`,
`teamEntryId`. Seven visible inputs, uniform for both statuses — no conditional
fields.

- `teamEntryId` is declared `'hidden'` and validated with **`validateHash`**.
  This matters: it determines which roster a submission lands in and it is
  visible in page source, so it must be tamper-proof. It is the **only** entry ID
  the form posts. A `__construct` casts it to string (it arrives as an int at
  render time, a hashed string on submit), mirroring `Login::redirect`.
- `status` renders as a `radio` with exactly two options, `interested` and
  `committed`, validated against `Signups::STATUS_*`.
- `interestedInCoaching` is declared `public array` with a single checkbox option
  — `_components/form-field.twig` posts checkboxes as `name[]`, so a `bool`
  property would fail assignment. The controller casts it with `!empty()`.
- `guardianEmail` is prefilled from `currentUser.email` when the form is
  constructed in the template.
- `getActionPath()` → `'athletics/signups/save'`
- `getRedirectPath()` → the sport page URL (set via a `redirect` config at render
  time, server-side, so it is not user-controlled). On a 200, `tl-form` navigates
  there; the controller sets a success flash and the page reload shows it, with
  the user's team accordion auto-expanded so the new registration is visible.
  (400 validation errors are still handled inline over AJAX — no reload.)

**Implementation note on the coaching checkbox.** `_components/form-field.twig`
renders `checkbox` as an option group posting `name[]`, so a single boolean
cannot be typed as `bool` on the model — Yii will fail assigning an array to it.
Declare `public array $interestedInCoaching = []` with one option, and cast with
`!empty()` when writing the record. This avoids editing the shared form-field
component. The alternative — adding a `boolean` case to `form-field.twig` that
renders one checkbox with a hidden `0` fallback — is cleaner but touches shared
markup used by every form on the site.

### `services/Signups.php`

As built (commit 6). The service deals in records and primitives, so it stays
decoupled from the HTTP/form layer — the controller reads validated attributes off
the form model and hands them in.

```php
// writes
register(int $userId, int $teamEntryId, array $data): ?SignupRecord  // plain insert; catches the unique-index race
commit(int $signupId, int $userId): bool                            // interested → committed, ownership-checked
withdraw(int $signupId, int $userId): bool                          // deletes the row, ownership-checked
// reads
getSignupsForUserAndTeam(int $userId, int $teamEntryId): array      // records; feeds the signup panel
getRoster(int $teamEntryId, bool $includeContact = false): array    // committed rows, contact gated in-service
getCommittedCount(int $teamEntryId): int
getInterestedCount(int $teamEntryId): int
participantExists(int $teamEntryId, string $participantKey): bool   // backs the form duplicate check
// team state
isRegistrationOpen(Entry $team): bool
getRemainingCapacity(Entry $team): ?int                            // null when capacity unset; may be negative
getTeamState(Entry $team): string                                 // a STATE_* constant; see §4
// authorization
canViewContact(?User $user): bool
// helper
static participantKey(string $first, string $last, string $dob): string
```

Status and state values are exposed as `STATUS_*` / `STATE_*` constants on the
service so the model, controller, and templates share one vocabulary.

**Authorization is an ownership check, not a hashed ID.** `commit()` and
`withdraw()` load the row and verify `signup.userId === $userId` before acting —
the real control, stronger than hashing because it survives someone replaying
their own earlier hash. v1 keeps this strict (no admin override in the service);
admins manage signups through the CP / database.

**`getRoster()`'s `$includeContact` flag must be authorized in the service**, not
just in Twig. It re-checks eligibility itself via a small helper:

```php
canViewContact(?User $user): bool   // $user->admin || $user->isInGroup('athleticsStaff')
```

A template-only guard would leave guardian contact details one careless `include`
away from every parent on the site. Craft admins stay in the check alongside the
group — they can read the table directly regardless.

Keeping roster queries behind the service matters for one specific reason: it is
the seam where roster visibility would be narrowed if v1's "any logged-in parent"
rule is ever tightened.

### `controllers/SignupsController.php`

Follows `modules/notifications/src/controllers/SubscriptionsController.php`:
`requirePostRequest()` and `requireLogin()` in every action, no `$allowAnonymous`.

- **`actionSave()`** validates the form model (shape), then runs the business
  rules in order: loads the team via `section('teams')->id()->one()` (default
  status filter rejects disabled or non-team ids), checks
  `isRegistrationOpen()`, and checks `participantExists()` — a repeat becomes a
  clean field error on `participantFirstName` before the insert. Only then does it
  call `Signups::register()`; a null return (lost unique-index race) is a generic
  failure. Model-shape failures return `asModelFailure($model)`, producing the
  `{ message, errors }` shape `src/scripts/components/form.js:38` maps onto
  fields.
- **`actionCommit()` / `actionWithdraw()`** take a signup ID, delegate the
  ownership check to the service, set a flash message, and redirect back to the
  sport page.

**Duplicate submissions stay a validation error,** not an upsert: "Jane Smith is
already registered for this team." Resubmission is a guess at intent — it could be
an upgrade, a typo correction, or a sibling with a similar name. The panel makes
intent explicit instead.

---

## 4. Templates

```
templates/athletics/_entry.twig              # sport page (bare semantic markup only)
templates/_components/team-list.twig         # self-padding accordion wrapper + flash/empty
templates/_components/team.twig              # full-width accordion item: state + panel + roster + form
templates/_components/signup-panel.twig      # the parent's own registrations
templates/_components/roster.twig            # roster table for one team
```

`athletics/_entry.twig` is bare semantic markup, matching the `index.twig` /
`classes/_entry.twig` convention: a classless `<article>` wrapping a `<header>`
(the shared `_components/hero` — heading + image + overview, same as the class
detail page) and a `<section>` (the `team-list` component). It carries **no
page-level padding or styling** — every component self-pads via `@include pad`.

`team-list` owns the padding/max-width, the flash notice, and the empty state,
then loops `entry.activeTeams`, rendering each as a `team` accordion. Accordions
start **collapsed when there is more than one team**; a lone team stays open, and
**any team where the current user has signups is expanded** (so a just-submitted
registration is visible after the success redirect).

Each team is a full-width `<details>` accordion modeled on `_components/accordion-list`'s
markup and chevron behavior, but without that component's split header — the
summary carries the age-group heading, season/coach meta, a state badge, and the
committed count; the body holds the state message, notes, roster, panel, and
signup form.

### Homepage listing

The homepage (`index.twig`) lists the current athletics offerings below the class
listing, replicating the `activities` convention: a `_components/card-list` of
sport cards. `_partials/entry/sport.twig` renders a sport as a `_components/card`
(preview image, sport name, a `pageCta` "Sign Up" link to the sport page) —
mirroring `_partials/entry/activity.twig`, which is what makes `sport.render()`
resolve (Craft renders elements via `_partials/entry/<entryTypeHandle>`).

The query lists sports that have at least one active team whose registration
window has not closed (`registrationCloses >= now`) — i.e. the upcoming-season
offerings you can still sign up for. The section heading is currently hardcoded
`'Athletics'`; wire it to a homepage field (e.g. `athleticsHeading`) if the other
section headings' editability is wanted here too.

### The signup panel

Shown above the form whenever the current user has signups for that team. Because
a parent may register several children on one team (twins, or siblings in the same
age band — the unique index permits it), this is a list, not a single record:

```
Your registrations for U10 Boys
  Jane Smith    Committed     [Withdraw]
  Ben Smith     Interested    [Commit] [Withdraw]

  + Register another child   → discloses the signup form
```

The form is hidden behind a disclosure rather than removed, so adding a sibling
still works.

Each action is a plain `<form method="post">` with `actionInput()` +
`redirectInput()` back to the sport page — deliberately **not** wrapped in
`tl-form`, so the browser performs a normal POST and reload. This matches the
hidden-form pattern already in `_components/subscriptions.twig` and needs no
JavaScript.

### Team state

`getTeamState()` combines the registration window with the minimum and capacity
figures. Counts are of `committed` rows only — that is what actually fields a
team. `interested` is shown as a secondary figure.

| Condition | State | Message |
|---|---|---|
| Before `registrationOpens` | `pending` | "Registration opens {date}" — roster only, no form |
| Open, committed < `minimumPlayers` | `forming` | "7 of 10 needed to field a team — 3 more to go" |
| Open, committed < `capacity` | `confirmed` | "Team confirmed · 3 spots left" |
| Open, committed ≥ `capacity` | `over target` | "Target met — additional signups may form a second team" |
| After `registrationCloses` | `closed` | Roster only, no form |

`registrationOpens` / `registrationCloses` are **required fields**, so the window
comparisons are always plain date math — no null branches. `capacity` and
`minimumPlayers` stay nullable: unset `minimumPlayers` skips `forming`; unset
`capacity` means `over target` is unreachable and no "spots left" figure is shown.

Required is enforced only at CP save time, so `isRegistrationOpen()` should still
treat a null window defensively — show no form rather than throw. Because this is
a greenfield feature, no team entry predates the required fields, so in practice
the guard never fires; it is one cheap line, not the full branch set the nullable
version needed.

**Signup and Commit stay available in `over target`** — that is the whole point of
a soft cap. **Withdraw remains available in every state**, including `closed`,
since people drop out after registration ends. **Commit is disabled once
`closed`**, as it would add to a finalized roster.

### Privacy and admin visibility

The roster renders **participant name only** to any authenticated user.
`guardianEmail`, `guardianPhone`, and the `interestedInCoaching` flag are appended
as extra columns **only for `athleticsStaff` members and Craft admins**,
authorized in the service as described in §3.

This is the entirety of the admin visibility story in v1 — the athletic director
signs in through the same magic-link flow parents use, so one page serves both
audiences and there is no separate admin surface to build or maintain.
`dateOfBirth` is not rendered to anyone; it exists only for future age-eligibility
checks (see `cutoffDate` in Future Considerations).

The sport template also sets `metaNoIndex = true`, as
`templates/classes/_entry.twig` does.

---

## 5. Styles and scripts

- `src/styles/components/_team-list.scss`, `_team.scss`, `_roster.scss`, and
  `_signup-panel.scss`, with matching `@use` lines added to
  `src/styles/index.scss`. `_team-list` owns the page padding/max-width; the entry
  template itself carries no styling.
- **JavaScript: none.** The signup form reuses `tl-form`; the panel actions are
  plain POST forms.

### Independent cleanup — `form-field.js`

`src/scripts/components/form-field.js:17` declares `constructor(el)`, but custom
element constructors receive no arguments, so `el` is `undefined` and
`toggleVisibility(el, …)` throws. The conditional-field feature it implements has
never been exercised — no form model currently overrides `attributeConditionals()`
— so the bug is latent.

This plan does not use conditional fields, so **this is not a prerequisite**. It
is worth the one-line fix (`el` → `this`) on its own merits before anyone else
reaches for the feature.

---

## 6. Build order — commit by commit

Each commit is self-contained, leaves `main` runnable, and is reviewable on its
own. The two build-time risk checks flagged elsewhere land in specific commits,
noted below.

### Commit 1 — Register the module

- `composer.json` autoload entry `"modules\\athletics\\": "modules/athletics/src/"`
  → `composer dump-autoload`
- `config/app.php:33`: add `athletics` to `$modules` and the bootstrap list
- `AthleticsModule.php`: `init()` sets the alias, `controllerNamespace`, and the
  `craft.signups` variable via `setComponents(['signups' => Signups::class])`
- `services/Signups.php`: created here as an empty `Component` subclass; methods
  arrive in Commit 6

No URL rules yet — nothing to route to. **Verify:** `php craft` runs, the site
loads, and `craft.signups` resolves in a template.

### Commit 2 — Signups table

- `migrations/m260723_000000_athletics.php` — creates `{{%athletics_signups}}`
  with the two composite indexes, the `userId` index, and both foreign keys
- `records/Signup.php` — the `ActiveRecord`

**Verify:** `php craft up` applies cleanly; the table and indexes exist.

### Commits 3–5 — Content model (DONE, authored in the CP)

Built directly in the control panel on the `sports-signups` branch; the generated
`config/project/` YAML is staged for commit. This covers:

- All new fields from §1 (`ageGroup`, `season`, `startYear`, `positiveInteger`,
  `email`, `sport`, `teams`, `coaches`)
- The `coach`, `team`, and `sport` entry types and their `coaches`, `teams`,
  `sports` sections, with all relations wired and the required flags set
- The `athleticsStaff` user group

These can land as one commit or a few; the ordering constraints that mattered
during authoring (coaches before teams, sections before relation fields) are moot
now that the config exists. **Still verify at runtime once the template stub
exists (Commit 8, or a throwaway stub sooner):** create a sport with one team and
confirm the team's auto-title renders the sport *name*, not an ID or blank — the
one `titleFormat`-with-relation check from §1.

### Commit 6 — Signups service

- Flesh out `register` / `commit` / `withdraw`, the counts, `getRoster`
  (+ `canViewContact`), `getTeamState`, `isRegistrationOpen`,
  `getSignupsForUserAndTeam`

Depends on the table (Commit 2) and the team fields (Commit 4). **Verify:** a
functional test or a `php craft tinker`-style check that a signup inserts, the
duplicate index rejects a repeat, and `getTeamState()` returns the right string
across the window boundaries.

### Commit 7 — Form model, controller, routes

- `models/Signup.php`, `controllers/SignupsController.php`
- URL rules in `AthleticsModule` for `save` / `commit` / `withdraw`

**This commit is where the `interestedInCoaching` checkbox array-typing detail
lands** (§3). **Verify:** a POST from devtools/curl returns the success or
validation JSON shape, and a row lands in the table with the right status.

### Commit 8 — Templates and styles

- `athletics/_entry.twig` (replacing the stub), `_components/team.twig`,
  `_components/roster.twig`, `_components/signup-panel.twig`
- `_roster.scss`, `_team.scss`, `_signup-panel.scss` + `@use` lines in
  `src/styles/index.scss`

**Verify:** a full click-through in the browser — sign up, commit, withdraw,
register a sibling — as a `parents` user, then confirm the contact columns appear
as an `athleticsStaff` user and not otherwise.

### Commit 9 (independent) — `form-field.js` fix

The one-line `el` → `this` fix from §5. Unrelated to the feature; can land any
time, before or after the rest.

---

Commits 1–8 are the feature. There is no polish tail — the notification, export,
and admin-page work that earlier drafts placed in later phases has been cut from
v1.

---

## 7. Deployment

No changes required. `.github/workflows/main.yml:40` already runs
`composer install && php craft up && ./cachebust.sh`, and `craft up` applies both
pending migrations and project config.

The only environment prerequisite is `CRAFT_ALLOW_ADMIN_CHANGES=true` on dev, so
the new fields, entry types, and sections can be authored in the CP and committed
as project config YAML.

---

## Future considerations

Ordered roughly by how likely they are to be wanted.

- **CSV roster export.** The natural next addition — the on-page contact columns
  cover looking someone up, but not a bulk mail-merge. Either a button on the sport page
  or the `Element::EVENT_DEFINE_ADDITIONAL_BUTTONS` hook on the team entry —
  `NotificationsModule.php:70` already demonstrates that pattern in this codebase.
- **Coach follow-up details.** Medical notes, emergency contacts, and shirt size
  were deliberately cut from signup — asking for them from a parent who only
  ticked "interested" suppresses exactly the soft signups the status is meant to
  capture. When a roster is confirmed, the coach collects them. If that should
  later live in the portal, it is a separate authenticated flow against an
  existing signup row, not additional fields on this form.
- **Notifications.** Signup confirmations, and a weekly digest to the athletic
  director covering committed and interested counts against minimum and capacity
  with at-risk teams flagged. The infrastructure exists — a production crontab
  with `CRON_TZ=America/New_York` is already documented at
  `spec/notification-system.md:345` for the Sunday class-notification job. A
  digest is preferable to a threshold-crossing alert, which flaps as people
  withdraw and can only ever report the good news.
- **Waitlists and hard capacity.** Cut because overflow is better solved by
  fielding a second team. If ever reinstated, the locking transaction must come
  back with the enforced cap — see "Why there is no waitlist" above.
- **Downgrade (committed → interested).** Withdraw currently removes the row
  entirely, on the assumption that someone backing out wants off the list. A third
  action is roughly ten lines of service code if parents ask for it.
- **Machine-checked age eligibility.** Add a `cutoffDate` datetime to the team;
  `dateOfBirth` + `cutoffDate` gives exact age-as-of-cutoff, which is how leagues
  actually express the rule. This is the natural successor to `eligibilityNotes`
  and a better fit for age-based sports than grade ranges.
- **Data retention.** This table holds minors' names, dates of birth, and guardian
  contact details indefinitely. Worth revisiting once the feature is in use —
  likely a manual `prune` console command filtered on the team's `startYear`,
  with the window set by school policy rather than by us.
- **Coaches as Craft users.** The upgrade is deliberately **additive**: add a
  Users relation field to the `coach` entry type and link the record once that
  person has an account. Templates keep reading `coach.email`,
  `coach.photo`, `coach.bio` unchanged — only login and permission logic would
  read `coach.user`. No data migration, no field handle changes, no template
  rewrites. This is the payoff for making the coach entry the stable identity
  rather than putting loose text fields on each team.
- **Reusable child profiles.** Parents currently re-enter participant details for
  every signup. A `{{%athletics_participants}}` table owned by the user, with
  `athletics_signups.participantId` replacing the denormalized name/DOB columns,
  would remove the re-typing.
- **Payments.** Requires Craft Commerce or a Stripe integration.
- **Duplicate-team prevention.** Craft has no cross-field unique constraint, so
  nothing stops two teams sharing (sport, ageGroup, season, startYear). An
  `Entry::EVENT_BEFORE_SAVE` handler could reject it if it becomes a problem.
- **Coach-restricted rosters.** v1 shows rosters to any logged-in parent. If
  narrowed later, the seam is `getRoster()` plus a `coaches` check.
