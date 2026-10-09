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
$string['beneficiary'] = 'Bénéficiaire';
$string['beneficiarytype'] = 'Accès accordé à';
$string['cohort'] = 'Cohorte';
$string['cohortaccess:manage'] = 'Gérer les règles d\'accès aux cours par cohorte';
$string['confirmdelete'] = 'Supprimer la règle donnant à la cohorte « {$a->beneficiary} » le rôle « {$a->role} » dans « {$a->target} » ? Toutes les attributions de rôle créées par cette règle seront retirées.';
$string['confirmdeleteuser'] = 'Supprimer la règle donnant à l\'utilisateur « {$a->beneficiary} » le rôle « {$a->role} » dans « {$a->target} » ? L\'attribution de rôle créée par cette règle sera retirée.';
$string['disable'] = 'Désactiver';
$string['duplicaterule'] = 'Une règle avec le même bénéficiaire, la même cible et le même rôle existe déjà.';
$string['editrule'] = 'Modifier la règle';
$string['enable'] = 'Activer';
$string['enabled'] = 'Activée';
$string['members'] = 'Membres';
$string['norules'] = 'Aucune règle définie.';
$string['pluginname'] = 'Accès aux cours par cohorte';
$string['privacy:metadata:core_role'] = 'Les règles donnent des rôles aux utilisateurs dans des cours ou des catégories. Ces attributions de rôle sont stockées par le sous-système des rôles.';
$string['privacy:metadata:local_cohortaccess'] = 'Règles donnant un rôle à une personne dans un cours ou une catégorie de cours.';
$string['privacy:metadata:local_cohortaccess:enabled'] = 'Indique si la règle est activée.';
$string['privacy:metadata:local_cohortaccess:roleid'] = 'Le rôle donné à l\'utilisateur.';
$string['privacy:metadata:local_cohortaccess:targetid'] = 'Le cours ou la catégorie de cours où le rôle est donné.';
$string['privacy:metadata:local_cohortaccess:targettype'] = 'Indique si la cible est un cours ou une catégorie de cours.';
$string['privacy:metadata:local_cohortaccess:timecreated'] = 'Date de création de la règle.';
$string['privacy:metadata:local_cohortaccess:timemodified'] = 'Date de dernière modification de la règle.';
$string['privacy:metadata:local_cohortaccess:userid'] = 'L\'utilisateur qui reçoit le rôle.';
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
$string['user'] = 'Utilisateur';
$string['usermissing'] = 'Cet utilisateur n\'existe plus.';
$string['warningnocourseview'] = 'Ce rôle ne possède pas moodle/course:view. Les utilisateurs pourraient ne pas pouvoir accéder au cours sans inscription.';
$string['warningnoviewhidden'] = 'Ce rôle ne possède pas moodle/course:viewhiddencourses. Les utilisateurs ne pourront pas accéder aux cours cachés.';
