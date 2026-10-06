# Plan — plugin Moodle `local_cepv_cohortaccess`

## Context
CEPV veut donner à des membres de cohortes un accès **consultation** à des cours (ou à toutes les cours d'une catégorie) **sans inscription** : attribution de rôle Moodle (`role_assignments`) au contexte cours/catégorie, jamais de `user_enrolments`. Conséquence assumée (validée) : l'utilisateur est « visiteur » (`is_viewing`) — voit le contenu, ne fait pas quiz/devoirs, absent du carnet de notes et des participants, cours absent de « Mes cours » (basé sur `enrol_get_my_courses`).

Décisions utilisateur :
- Cible **Moodle 5.0** (`requires = 2025041400`), compatible **5.1** (`supported = [500, 501]`). Le code plugin ne dépend pas de l'arbo `public/` de 5.1.
- **Nouveau dépôt** `~/Projects/cepv_local_cohortaccess` (git init), plugin à la racine → déployé dans `local/cepv_cohortaccess`.
- Validation via **GitHub Actions** (moodle-plugin-ci) **+ local** dans la VM (installé le 2026-10-05) : Debian 13, PHP 8.4.26 CLI (curl, gd, intl, mbstring, xml, zip, soap, pgsql, sodium), Composer 2.8.8, PostgreSQL 17 (cluster `main`, port 5432, online).

Aucun Moodle dans `~/Projects` : cloner `MOODLE_500_STABLE` (ex. `~/Projects/moodle-dev`, hors dépôt plugin), symlink du plugin dans `local/cepv_cohortaccess`, vérifier les signatures douteuses directement dans le code core (version PHPUnit, `role_unassign_all`, `lib/form/cohort.php`, `lib/form/course.php`).

## Architecture

```
cepv_local_cohortaccess/
  version.php            component local_cepv_cohortaccess, version 2026100500, MATURITY_BETA, release 0.1.0
  settings.php           admin_externalpage dans 'localplugins' → /local/cepv_cohortaccess/index.php, cap :manage
  index.php              liste des règles + actions (sesskey, confirmation pour supprimer)
  edit.php               formulaire ajout/modification
  db/install.xml         table local_cepv_cohortaccess
  db/upgrade.php         stub xmldb_local_cepv_cohortaccess_upgrade
  db/uninstall.php       role_unassign_all(['component' => 'local_cepv_cohortaccess'])
  db/access.php          local/cepv_cohortaccess:manage (CONTEXT_SYSTEM, RISK_CONFIG|RISK_PERSONAL, manager)
  db/events.php          observers
  db/tasks.php           \local_cepv_cohortaccess\task\sync, minute 'R', toutes les heures
  classes/manager.php    toute la logique métier (CRUD règles + sync)
  classes/observer.php   délègue au manager
  classes/task/sync.php  scheduled_task → manager::sync_all()
  classes/form/rule_form.php
  classes/privacy/provider.php  null_provider (table sans donnée perso ; role_assignments = core_role)
  lang/en/local_cepv_cohortaccess.php, lang/fr/local_cepv_cohortaccess.php
  tests/manager_test.php, tests/observer_test.php, tests/task/sync_test.php
  .github/workflows/ci.yml
  README.md (FR : usage, limites visiteur, déploiement)
```

### Table `local_cepv_cohortaccess` (23 car., < limite 28 XMLDB)
`id, cohortid (FK cohort.id), targettype char(10) 'course'|'category', targetid, roleid (FK role.id), enabled int(1) default 1, timecreated, timemodified`. Index `(targettype, targetid)`. Pas de `usermodified` → pas de donnée personnelle → `null_provider`. Pas de `\core\persistent` (imposerait `usermodified`).

### `classes/manager.php` (cœur)
- `COMPONENT = 'local_cepv_cohortaccess'`
- `get_target_context(stdClass $rule): ?context` — `context_course::instance($id, IGNORE_MISSING)` / `context_coursecat::instance($id, IGNORE_MISSING)`.
- `create_rule / update_rule / delete_rule / set_enabled` — chaque mutation appelle `sync_rule()` (synchro immédiate, `core_php_time_limit::raise()` ; cohortes CEPV = centaines/milliers, OK en requête).
- `sync_rule(stdClass $rule): array{added, removed}` — **algorithme unique, idempotent, gère aussi modif/désactivation** :
  - attendus = `cohort_members` ⨝ `user` (deleted = 0) si règle activée et contexte cible existe, sinon ∅ ;
  - existants = `role_assignments WHERE component = :c AND itemid = :ruleid` ;
  - retirer chaque existant dont `roleid`/`contextid` ≠ config actuelle ou `userid` ∉ attendus → `role_unassign($ra->roleid, $ra->userid, $ra->contextid, COMPONENT, $rule->id)` ;
  - ajouter attendus manquants → `role_assign($rule->roleid, $userid, $ctx->id, COMPONENT, $rule->id)`.
  - ⇒ modification d'une règle = anciennes attributions (ancien rôle/cible) nettoyées automatiquement ; désactivation = tout retiré ; réactivation = resync.
- `remove_rule_assignments(int $ruleid)` → `role_unassign_all(['component' => COMPONENT, 'itemid' => $ruleid])`.
- `sync_all()` — boucle sur toutes les règles (tâche cron), renvoie totaux pour `mtrace`.
- `member_added(cohortid, userid)` / `member_removed(cohortid, userid)` — sur les règles de la cohorte ; ajout seulement si règle activée + contexte existe ; retrait via `role_unassign_all(['userid', 'component', 'itemid'])`.
- `cohort_deleted(cohortid)` — `cohort_delete_cohort()` supprime les membres **sans** événements member_removed → retirer attributions puis supprimer les règles.
- `target_deleted(type, id)` (cours/catégorie supprimés : le contexte core nettoie déjà les RA) et `role_deleted(roleid)` → supprimer les règles orphelines.
- `get_role_warnings(roleid, rule): string[]` — vérifie `role_capabilities` (contexte système, `CAP_ALLOW`) pour `moodle/course:view` ; si cible = cours caché (ou catégorie) vérifie aussi `moodle/course:viewhiddencourses`. Ne modifie jamais le rôle.
- `count_members(cohortid)`, `count_assignments(ruleid)` pour la liste.

**Sécurité des suppressions** : toute suppression filtre sur `component = 'local_cepv_cohortaccess'` + `itemid = ruleid` → attributions manuelles (component '') et autres plugins jamais touchées. Plusieurs règles donnant le même accès = lignes RA distinctes (itemid différent) → retirer une règle n'affecte pas l'autre ; accès = union.

### Observers (`db/events.php` → `classes/observer.php`)
`cohort_member_added`, `cohort_member_removed` (objectid = cohortid, relateduserid = userid), `cohort_deleted`, `course_deleted`, `course_category_deleted`, `role_deleted`. Pas besoin de `user_deleted` (core fait `role_unassign_all` sur l'utilisateur).

### Interface admin
- `settings.php` : `Administration du site → Plugins → Plugins locaux → Accès aux cours par cohorte`.
- `index.php` : `admin_externalpage_setup('local_cepv_cohortaccess')`, `html_table` : Cohorte | Cours/Catégorie (lien) | Rôle (`role_get_name`) | Membres (+ attributions actives) | État (badge Activée/Désactivée + ⚠ si avertissement rôle ou cible introuvable) | Actions Modifier / Activer-Désactiver / Synchroniser / Supprimer (`confirm()` + `require_sesskey()`). Bouton « Ajouter une règle ». Résultats sync via `\core\notification`.
- `rule_form.php` (moodleform) :
  - cohorte : élément standard `cohort` (autocomplete AJAX, `lib/form/cohort.php`) ;
  - `targettype` : radio/select course|category, avec `hideIf` ;
  - cours : élément standard `course` (autocomplete AJAX, `lib/form/course.php`) ;
  - catégorie : `autocomplete` alimenté par `core_course_category::make_categories_list()` ;
  - rôle : `autocomplete` sur `role_fix_names(get_all_roles())` ;
  - `enabled` : advcheckbox.
  - `validation()` : champs requis selon type, cible existe, **doublon** (même cohorte+cible+rôle) refusé.
  - Avertissements non bloquants (`get_role_warnings`) : affichés via `\core\notification::warning` après enregistrement et en ⚠ dans la liste. Texte FR exact du prompt : « Ce rôle ne possède pas moodle/course:view. Les utilisateurs pourraient ne pas pouvoir accéder au cours sans inscription. »

### Langues
EN + FR : `pluginname` (« Accès aux cours par cohorte »), libellés colonnes/actions/formulaire, avertissements, `privacy:metadata`, nom tâche, capacité.

## Tests PHPUnit (`advanced_testcase`, `resetAfterTest`)
Rôle de test = rôle custom créé avec `moodle/course:view` (`create_role` + `assign_capability`).
1. Règle cours → membres reçoivent RA (component/itemid corrects), **0 ligne `user_enrolments`**, `enrol_get_all_users_courses()` vide, `can_access_course()` vrai.
2. Règle catégorie → `has_capability('moodle/course:view', context_course)` vrai via héritage pour un cours de la catégorie.
3. `cohort_add_member` / `cohort_remove_member` → RA ajoutée/retirée immédiatement (observers).
4. Attribution manuelle préexistante (même rôle/contexte/user) conservée après suppression/désactivation de la règle.
5. Deux règles (2 cohortes) même cible/rôle : suppression d'une règle → accès conservé via l'autre.
6. Modification (changement de cours et de rôle) → anciennes RA retirées, nouvelles créées.
7. Désactivation → 0 RA ; réactivation → RA restaurées.
8. `cohort_delete_cohort` → RA retirées + règle supprimée ; suppression cours → règle supprimée.
9. Tâche sync : 2 exécutions → second run 0 ajout/0 retrait ; RA supprimée à la main puis restaurée ; RA en trop (user hors cohorte injecté avec component plugin) retirée.
10. `xmldb_local_cepv_cohortaccess_uninstall()` → plus aucune RA du composant, RA manuelles intactes.
11. `get_role_warnings` : rôle sans `course:view` → avertissement ; cours caché sans `viewhiddencourses` → avertissement.

## CI — `.github/workflows/ci.yml`
moodle-plugin-ci v4 (dernier tag), matrice : PHP 8.2/8.3 × `MOODLE_500_STABLE`, `MOODLE_501_STABLE`, pgsql (+ mariadb sur une ligne). Étapes : phplint, phpmd (non bloquant), phpcs `--max-warnings 0`, phpdoc, validate, savepoints, mustache, phpunit. Vérifier au codage que la version de moodle-plugin-ci gère l'arbo `public/` de 5.1 ; sinon 5.1 en `continue-on-error`.

## Étapes d'implémentation
1. `git init ~/Projects/cepv_local_cohortaccess`, squelette (version, lang, access, settings, install.xml, upgrade, uninstall, privacy).
2. `manager.php` + tests manager (TDD : écrire tests 1,2,4–7,9–11 d'abord).
3. Observers + tests 3, 8.
4. Tâche planifiée.
5. Formulaire + index/edit.
6. CI + README.
7. Commit initial (pas de push : remote GitHub à créer par l'utilisateur, ou `gh repo create` sur demande).

## Vérification
- Local (banc de test, étape 0) :
  1. `git clone --depth 1 -b MOODLE_500_STABLE https://github.com/moodle/moodle.git ~/Projects/moodle-dev` + `composer install` ;
  2. PostgreSQL : rôle + base `moodle_test` (`sudo -u postgres createuser -P moodle` / `createdb -O moodle moodle_test`) ;
  3. `config.php` minimal avec `$CFG->phpunit_prefix = 'phpu_'` et `$CFG->phpunit_dataroot` ;
  4. `ln -s ~/Projects/cepv_local_cohortaccess ~/Projects/moodle-dev/local/cepv_cohortaccess` ;
  5. `php admin/tool/phpunit/cli/init.php` puis `vendor/bin/phpunit --testsuite local_cepv_cohortaccess_testsuite` ;
  6. lint/style : `php -l`, et `moodle-plugin-ci` (composer) pour phpcs/phpdoc/validate/savepoints.
- CI GitHub Actions : tous les jobs verts (phpcs, phpdoc, validate, savepoints, phpunit) sur 5.0 et 5.1 — nécessite push vers un dépôt GitHub.
- Manuel sur Moodle de test : installer dans `local/cepv_cohortaccess`, créer rôle « Consultation cours » (`moodle/course:view`), règle cohorte→cours, se connecter en membre : accès direct au cours, cours absent de « Mes cours », aucune ligne dans Participants ; retirer de la cohorte → accès perdu ; lancer `php admin/cli/scheduled_task.php --execute='\local_cepv_cohortaccess\task\sync'`.
