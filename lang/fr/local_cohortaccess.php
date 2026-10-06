<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * French strings for local_cohortaccess.
 *
 * @package    local_cohortaccess
 * @copyright  2026 CEPV, Yann Rapenne
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions'] = 'Actions';
$string['activeassignments'] = 'Attributions de rôle actives : {$a}';
$string['addrule'] = 'Ajouter une règle';
$string['cohort'] = 'Cohorte';
$string['cohortaccess:manage'] = 'Gérer les règles d\'accès aux cours par cohorte';
$string['confirmdelete'] = 'Supprimer la règle donnant à la cohorte « {$a->cohort} » le rôle « {$a->role} » dans « {$a->target} » ? Toutes les attributions de rôle créées par cette règle seront retirées.';
$string['disable'] = 'Désactiver';
$string['duplicaterule'] = 'Une règle avec la même cohorte, la même cible et le même rôle existe déjà.';
$string['editrule'] = 'Modifier la règle';
$string['enable'] = 'Activer';
$string['enabled'] = 'Activée';
$string['members'] = 'Membres';
$string['norules'] = 'Aucune règle définie.';
$string['pluginname'] = 'Accès aux cours par cohorte';
$string['privacy:metadata'] = 'Le plugin Accès aux cours par cohorte ne stocke aucune donnée personnelle. Les attributions de rôle qu\'il crée sont stockées par le sous-système des rôles.';
$string['role'] = 'Rôle';
$string['rolenotassignable'] = 'Vous n\'êtes pas autorisé à attribuer ce rôle dans ce cours ou cette catégorie.';
$string['ruledeleted'] = 'Règle supprimée.';
$string['rulesaved'] = 'Règle enregistrée.';
$string['sync'] = 'Synchroniser';
$string['syncresult'] = 'Synchronisation terminée : {$a->added} attributions de rôle ajoutées, {$a->removed} retirées.';
$string['target'] = 'Cours / catégorie';
$string['targetcategory'] = 'Catégorie de cours';
$string['targetcourse'] = 'Cours';
$string['targetmissing'] = 'Le cours ou la catégorie cible n\'existe plus.';
$string['targettype'] = 'Type de cible';
$string['tasksync'] = 'Synchroniser les accès aux cours par cohorte';
$string['warningnocourseview'] = 'Ce rôle ne possède pas moodle/course:view. Les utilisateurs pourraient ne pas pouvoir accéder au cours sans inscription.';
$string['warningnoviewhidden'] = 'Ce rôle ne possède pas moodle/course:viewhiddencourses. Les utilisateurs ne pourront pas accéder aux cours cachés.';
