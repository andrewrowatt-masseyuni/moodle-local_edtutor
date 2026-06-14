# Why `local_edtutor` has its own login-as

## Why a custom login-as?

Moodle core already provides login-as, so it is reasonable to ask why this
plugin ships its own. The short answer is that core's login-as is either **too
powerful or too narrow** for a tutor's needs, and it **cannot switch between
users or return to your own account without a full logout**. This plugin is a
thin least-privilege policy and UX layer on top of core's session machinery: it
still uses `\core\session\manager::loginas()` for the actual session swap and
only adds the parts core deliberately does not offer.

## What Moodle core provides

Core's entry point is `course/loginas.php`, gated entirely on the
`moodle/user:loginas` capability. It works in one of two modes:

- **System context** (`course/loginas.php`, the `has_capability(...,
  $systemcontext)` branch) — lets the holder impersonate almost any user on the
  site (everyone except site admins). This is admin/manager-grade and far too
  powerful to grant to tutors.
- **Course context** (the `require_capability(..., $coursecontext)` branch) —
  lets a teacher impersonate an *enrolled* user, but **only inside that one
  course**, subject to separate-groups restrictions, and reachable only from the
  course participants UI. It cannot see the student's other courses.

Core also **cannot switch or return without a full logout**:

- `\core\session\manager::loginas()` early-returns if you are already
  logged-in-as, so you cannot hop straight from one user to another.
- `course/loginas.php` (its opening `is_loggedinas()` guard) forces
  `require_logout()` and a re-login to get back to yourself —
  *"for security reasons you need to log out and log in again"*.

## What this plugin adds

On top of core's session swap, `local_edtutor` adds:

1. **A purpose-scoped capability.** `local/edtutor:loginas`
   (`db/access.php`) is decoupled from `moodle/user:loginas`, and every
   impersonation is gated by an allocation check, `manager::is_allocated()`
   (`classes/loginas.php`, `require_can_loginas()`). A tutor can become *only
   their own allocated students*, never arbitrary users. Core has no allocation
   concept, so its system capability is all-or-nothing.
2. **Cross-course reach without the admin capability.** The plugin calls
   `\core\session\manager::loginas($studentid, \context_system::instance())`
   (`classes/loginas.php`, `loginas_student()`), giving the tutor a
   system-level `loginascontext` so they can roam *all* of the student's
   courses — but authorised by the allocation, not the dangerous core
   capability.
3. **Switch students / return to self without logging out.**
   `restore_real_user()` (`classes/loginas.php`) is the deliberate inverse of
   core's `loginas()`: it restores the backed-up `REALSESSION`/`REALUSER` in
   place. `loginas_student()` uses it so a tutor can go student A -> student B
   -> their own account seamlessly. Core forces a logout/login between each.
4. **An always-available menu.** The user-menu hook
   (`classes/hook_listener/user_menu.php`, `add_loginas_items()`) lists each
   allocated student plus a "Return to my account" entry on every page, scoped
   to the tutor. Core's only entry is buried in course participant lists.
5. **Tighter guards and an audit trail.** `require_can_loginas()` blocks
   deleted, suspended, guest, site-admin, and self targets, and the return leg
   fires a custom `loginas_returned` event (core fires `user_loggedinas` on
   entry but has no return event).

## Security trade-off

The "return/switch without logout" behaviour is the one thing core
**intentionally avoids**. Core forces a logout between sessions so that a
leftover impersonated session cannot be reused, and so caches are fully cleaned
(the `sessionforceclean` notice). Restoring the real session in place
reintroduces exactly that risk.

This is a conscious trade-off for the tutor workflow, where logging out between
every allocated student would be punishing. Two things keep it safe and must
stay that way:

- **The allocation gate is load-bearing.** Because a tutor holds a real
  login-as capability at system context, the `manager::is_allocated()` check is
  the only thing standing between them and impersonating arbitrary users. It
  must remain airtight.
- **Fallback to a full logout.** If the session backups are missing when
  `restore_real_user()` runs, it falls back to `require_logout()` rather than
  leaving the tutor in an ambiguous state.

## Reuse of core

This plugin is not a reimplementation of login-as. It still performs the actual
session swap with `\core\session\manager::loginas()`, and `restore_real_user()`
deliberately mirrors core's session-manipulation contract (the same
`REALSESSION`/`REALUSER` handling, including the matching phpcs ignores). Only
two things are bespoke: the **return/switch-without-logout** behaviour that core
omits, and the **allocation-based policy + UX** layer. If the requirement were
merely "a teacher logs in as a student within one course", core alone would
suffice and this plugin would be unnecessary. It earns its place specifically
through cross-course scope, allocation-based least privilege, and frictionless
switching.
