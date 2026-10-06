# Cohort course access (`local_cohortaccess`)

A Moodle local plugin that gives the members of a cohort **view access** to a
course, or to every course of a category, **without enrolling them**.

The plugin assigns a role (`role_assignments`) to the cohort members in the
course or category context. It never creates enrolments (`user_enrolments`).

Requires Moodle 5.0 or later (tested on 5.0 and 5.1).

## How it works

A **rule** links a cohort, a target (course or course category), a role and a
state (enabled / disabled).

- When a rule is created or enabled, every cohort member gets the role in the
  target context.
- When a user joins or leaves the cohort, the role is assigned or removed
  immediately (event observers).
- When a rule is edited (other target, other role), the old assignments are
  removed and the new ones created.
- Disabling a rule removes all its assignments; enabling it again restores them.
- When a cohort, course, category or role is deleted, the rules using it are
  deleted together with their assignments.
- A scheduled task (`\local_cohortaccess\task\sync`, hourly) resynchronises all
  rules and repairs any drift, including assignments left by deleted rules.

Every role assignment created by the plugin has
`component = local_cohortaccess` and `itemid = <rule id>`. The plugin **never**
removes a manual role assignment or one created by another plugin. When two
rules grant the same access, deleting one of them keeps the access granted by
the other.

## Setting up the role

Use a dedicated role (for example "Course viewer"), or an existing one that
fits. Check its definition in *Site administration → Users → Permissions →
Define roles*:

1. **Context types where this role may be assigned**: tick **Course** and
   **Category**. Other roles are not offered in the rule form.
2. **`moodle/course:view` = Allow**: **required**. This is what lets a user enter
   a course without being enrolled.
3. **`moodle/course:viewhiddencourses` = Allow**: only if target courses may be
   hidden.
4. **Allow role assignments**: the person creating rules must be allowed to
   assign the role in the target course or category. Site administrators always
   can.

The plugin never changes role definitions. It shows a non-blocking warning when
the chosen role lacks `moodle/course:view` (or `viewhiddencourses` for a hidden
course or a category).

> **Warning: a rule grants every capability of the role, not just viewing.**
> The role is assigned in the whole course, or in every course of the category.
> If the role has teacher-like capabilities, such as seeing participants
> (`moodle/course:viewparticipants`), viewing grades (`moodle/grade:viewall`),
> viewing reports or editing the course, cohort members get them wherever the
> rule applies. For view-only access, the role should only contain
> `moodle/course:view` (and `viewhiddencourses` if needed).

## Limitations ("viewing" access)

Users are not enrolled, so Moodle treats them as viewers (`is_viewing()`):

- They can see the course content (pages, files, resources).
- They cannot take part in activities that require enrolment (quizzes,
  assignments, etc.).
- They are not in the gradebook nor in the participants list (they can however
  *see* that list if the role has `moodle/course:viewparticipants`).
- The course does not show up in "My courses" or on the dashboard: give them the
  direct link to the course or category.
- If a cohort member is also enrolled in a target course and loses their last
  enrolment, Moodle core removes all their role assignments in that course,
  including the plugin's one. The scheduled task restores it within the hour.

## Administration

*Site administration → Plugins → Local plugins → Cohort course access*

Required capability: `local/cohortaccess:manage` (given to managers by default).

The list shows, for each rule: cohort, target, role, number of members and of
active role assignments, status (with a ⚠ when the role lacks a capability or
the target no longer exists), and the actions Edit / Enable-Disable /
Synchronise / Delete.

## Installation

- **From a release**: download `local_cohortaccess-<version>.zip` from the
  [GitHub releases](https://github.com/CEPV-Vevey/moodle-local_cohortaccess/releases),
  then use *Site administration → Plugins → Install plugins*. Alternatively,
  unzip it into `local/` (`public/local/` on Moodle 5.1+) so that the plugin ends
  up in `local/cohortaccess`.
- **From git**: clone the repository into `local/cohortaccess`.

Then visit *Site administration → Notifications* (or run
`php admin/cli/upgrade.php`).

Manual synchronisation:

```
php admin/cli/scheduled_task.php --execute='\local_cohortaccess\task\sync'
```

## Uninstalling

Uninstalling removes every role assignment created by the plugin. Manual role
assignments are kept.

## Development

PHPUnit:

```
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite local_cohortaccess_testsuite
```

GitHub Actions runs moodle-plugin-ci (phplint, phpcs, phpdoc, validate,
savepoints, mustache, phpunit) on Moodle 5.0 and 5.1.

Releasing: bump `$plugin->version` and `$plugin->release` in `version.php`,
commit, then push a matching tag (`release = '0.2.0'` → tag `v0.2.0`). The
release workflow checks the tag, builds the installable zip and attaches it to a
GitHub release.

Note: for local web testing, do not install the plugin through a symbolic link
(pages use `__DIR__ . '/../../config.php'`); copy it or use a bind mount.

## Credits

Developed by Yann Rapenne for the CEPV.

## License

GNU GPL v3 or later. See <https://www.gnu.org/licenses/gpl-3.0.html>.
