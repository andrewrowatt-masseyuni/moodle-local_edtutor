# Why `local_edtutor` has its own login-as

## Why a custom login-as?

Moodle core already provides login-as, so it is reasonable to ask why this
plugin ships its own. The short answer is that core's login-as is either **too
powerful or too narrow** for a tutor's needs. This plugin is a thin
least-privilege policy and UX layer on top of core's session machinery: it
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
3. **An always-available menu.** The user-menu hook
   (`classes/hook_listener/user_menu.php`, `add_loginas_items()`) lists each
   allocated student on every page, scoped to the tutor. Core's only entry is
   buried in course participant lists.
4. **Tighter guards.** `require_can_loginas()` blocks deleted, suspended,
   guest, site-admin, and self targets.

## Exiting a login-as session

The plugin matches core exactly: **the only way out of a login-as session is a
full logout followed by re-authentication**, mirroring the opening
`is_loggedinas()` guard of `course/loginas.php` — *"for security reasons you
need to log out and log in again"*. `loginas.php?userid=0` (the "Log out and
return to my account" menu entry) calls `require_logout()`, and any hit on
`loginas.php` while logged-in-as — including a stale login-as link in another
tab — does the same. `loginas_student()` refuses to run inside an existing
login-as session.

An earlier version of the plugin restored the tutor's backed-up real session in
place, so tutors could switch students or return to their own account without
logging out. A security review determined that keeping a restorable privileged
session alongside the impersonated one is a risk core deliberately avoids: the
logout guarantees a leftover impersonated session cannot be reused and that
every sesskey issued to either identity dies with it. The convenience was
removed in favour of core's model; the deliberate UX regression is that tutors
re-authenticate between students.

Two guards remain load-bearing:

- **The allocation gate.** Because a tutor holds a real login-as capability at
  system context, the `manager::is_allocated()` check is the only thing
  standing between them and impersonating arbitrary users. It must remain
  airtight.
- **The logout-only exit.** Nothing in the plugin may reintroduce an in-place
  restore of the real session; core's `user_loggedout` event provides the exit
  audit trail (core fires `user_loggedinas` on entry).

## Reuse of core

This plugin is not a reimplementation of login-as. It performs the actual
session swap with `\core\session\manager::loginas()` and exits through core's
`require_logout()`. Only the **allocation-based policy + UX** layer is bespoke.
If the requirement were merely "a teacher logs in as a student within one
course", core alone would suffice and this plugin would be unnecessary. It
earns its place specifically through cross-course scope, allocation-based least
privilege, and the always-available menu.
