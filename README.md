# Accès aux cours par cohorte (`local_cepv_cohortaccess`)

Plugin local Moodle (5.0, compatible 5.1) qui donne aux membres d'une cohorte un
accès **en consultation** à un cours, ou à tous les cours d'une catégorie,
**sans les inscrire**.

Le plugin attribue un rôle Moodle (`role_assignments`) dans le contexte du cours
ou de la catégorie. Il ne crée jamais d'inscription (`user_enrolments`).

## Fonctionnement

Une **règle** associe : une cohorte, une cible (cours ou catégorie), un rôle, un
état (activée / désactivée).

- À la création ou à l'activation d'une règle, chaque membre de la cohorte reçoit
  le rôle dans le contexte cible.
- Ajout / retrait d'un membre de la cohorte : le rôle est attribué / retiré
  immédiatement (observateurs d'événements).
- Modification d'une règle (autre cible, autre rôle) : les anciennes attributions
  sont retirées, les nouvelles créées.
- Désactivation : toutes les attributions de la règle sont retirées ;
  réactivation : elles sont recréées.
- Suppression d'une cohorte, d'un cours, d'une catégorie ou d'un rôle : les règles
  concernées sont supprimées avec leurs attributions.
- Une tâche planifiée (`\local_cepv_cohortaccess\task\sync`, toutes les heures)
  resynchronise toutes les règles et corrige les écarts.

Chaque attribution créée par le plugin porte `component = local_cepv_cohortaccess`
et `itemid = <id de la règle>`. Le plugin ne retire **jamais** une attribution
manuelle ni une attribution créée par un autre plugin. Si deux règles donnent le
même accès, supprimer l'une ne retire pas l'accès donné par l'autre.

## Rôle à utiliser

Au CEPV, utiliser le rôle existant **« Collègue enseignant »**. Avant de créer la
première règle, vérifier sa définition (*Administration du site → Utilisateurs →
Permissions → Définir les rôles → Collègue enseignant*) :

1. **Types de contexte où ce rôle peut être attribué** : cocher **Cours** et
   **Catégorie de cours**. Sinon, le rôle n'est pas proposé dans le formulaire des
   règles.
2. **`moodle/course:view` = Autoriser** : **indispensable**. C'est cette capacité qui
   permet d'entrer dans un cours sans y être inscrit.
3. **`moodle/course:viewhiddencourses` = Autoriser** : seulement si des cours cibles
   peuvent être cachés.
4. **Autorisations d'attribution** (onglet *Autoriser l'attribution de rôles*) : la
   personne qui crée les règles doit pouvoir attribuer « Collègue enseignant ». Les
   administrateurs du site le peuvent toujours.

Le plugin ne modifie jamais la définition des rôles. Il affiche un avertissement
(non bloquant) si le rôle choisi n'a pas `moodle/course:view` (ou
`viewhiddencourses` pour un cours caché ou une catégorie).

> **Attention : le rôle donne toutes ses capacités, pas seulement la
> consultation.** Une règle attribue le rôle dans tout le cours, ou dans tous les
> cours de la catégorie. Si « Collègue enseignant » a des capacités d'enseignant,
> par exemple voir les participants (`moodle/course:viewparticipants`), consulter
> les notes (`moodle/grade:viewall`), voir les rapports d'activité ou modifier le
> cours, les membres de la cohorte les obtiennent partout où la règle s'applique.
> Pour une consultation pure, le rôle ne doit contenir que `moodle/course:view` (et
> au besoin `viewhiddencourses`). Si « Collègue enseignant » sert déjà à autre
> chose avec plus de droits, créer plutôt un rôle dédié.

## Limites (accès « visiteur »)

L'utilisateur n'est pas inscrit : Moodle le traite en visiteur (`is_viewing`).

- Il voit le contenu du cours (pages, fichiers, ressources).
- Il ne peut pas faire les activités qui exigent une inscription (tests, devoirs,
  etc.).
- Il n'apparaît ni dans le carnet de notes ni dans la liste des participants
  (il peut en revanche la *voir* si le rôle a `moodle/course:viewparticipants`).
- Le cours n'apparaît pas dans « Mes cours » ni dans le tableau de bord : il faut
  lui donner le lien direct du cours (ou de la catégorie).

## Administration

`Administration du site → Plugins → Plugins locaux → Accès aux cours par cohorte`

Capacité requise : `local/cepv_cohortaccess:manage` (attribuée par défaut aux
gestionnaires).

La liste affiche pour chaque règle : cohorte, cible, rôle, nombre de membres et
d'attributions actives, état (avec ⚠ si le rôle manque d'une capacité ou si la
cible n'existe plus) et les actions Modifier / Activer-Désactiver / Synchroniser /
Supprimer.

## Installation

1. Copier le dépôt dans `local/cepv_cohortaccess` (Moodle 5.1 :
   `public/local/cepv_cohortaccess`).
2. `Administration du site → Notifications` (ou `php admin/cli/upgrade.php`).

Synchronisation manuelle :

```
php admin/cli/scheduled_task.php --execute='\local_cepv_cohortaccess\task\sync'
```

## Désinstallation

La désinstallation retire toutes les attributions de rôle créées par le plugin.
Les attributions manuelles sont conservées.

## Développement

Tests PHPUnit :

```
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite local_cepv_cohortaccess_testsuite
```

GitHub Actions (`.github/workflows/ci.yml`) exécute moodle-plugin-ci (phplint,
phpcs, phpdoc, validate, savepoints, mustache, phpunit) sur Moodle 5.0 et 5.1.

Note : en local, ne pas installer le plugin par lien symbolique pour un test web
(les pages utilisent `__DIR__ . '/../../config.php'`) — copier ou utiliser un
`mount --bind`.

## Licence

GNU GPL v3 ou ultérieure.
