# Changes

## 0.4.0 (unreleased)

- Rules can give access to a single user instead of a cohort: choose
  "Access granted to: User" in the rule form (requires
  `moodle/user:viewalldetails`). User rules are listed with cohort rules, with
  the same actions and warnings.
- Deleting a user deletes the rules giving them access.
- Privacy API: rules for a single user are exported and deleted with the user's
  data (the plugin was a `null_provider` before).
- Database: `cohortid` is now optional and a `userid` column is added
  (upgrade step, existing rules unchanged).

## 0.3.0 (2026-10-06)

- Compatible with Moodle 5.0 to 5.3 (`supported = [500, 503]`).
- CI runs on Moodle 5.0, 5.1, 5.2 and 5.3, with PostgreSQL 17 and MariaDB 11.4.

## 0.2.1 (2026-10-06)

- Category rules only warn about `moodle/course:viewhiddencourses` when the
  category (or a subcategory) contains a hidden course.

## 0.2.0 (2026-10-06)

- Rules list laid out like Moodle core management tables: eye icon to enable or
  disable a rule, disabled rules greyed out, core icons for synchronise, edit and
  delete, warnings shown next to the role or the target they concern.
- Editing a rule now pre-fills category cohorts (only system cohorts were shown).
- "Active role assignments: N" wording in the list.
- README: comparison with similar plugins, usage, capabilities, security,
  privacy and troubleshooting sections, screenshots.

## 0.1.1 (2026-10-06)

- Rules page moved to *Site administration → Users → Accounts*, right below
  *Cohorts*.

## 0.1.0 (2026-10-06)

- First public beta: rules giving cohort members a role in a course or course
  category, without enrolment; event-driven and hourly synchronisation;
  installable zip attached to GitHub releases.
