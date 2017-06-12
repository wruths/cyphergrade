<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * cypherlab_base is the base class for cypherlab types
 *
 * This class provides all the functionality for an cypherlab
 *
 * @package   mod_cypherlab
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds an cypherlab instance
 *
 * Only used by generators so we can create old cypherlabs to test the upgrade.
 *
 * @param stdClass $cypherlab
 * @param mod_cypherlab_mod_form $mform
 * @return int intance id
 */
function cypherlab_add_instance($cypherlab, $mform = null) {
    global $DB;

    $cypherlab->timemodified = time();
    $cypherlab->courseid = $cypherlab->course;
    $returnid = $DB->insert_record("cypherlab", $cypherlab);
    $cypherlab->id = $returnid;
    return $returnid;
}

/**
 * Deletes an cypherlab instance
 *
 * @param $id
 */
function cypherlab_delete_instance($id){
    global $CFG, $DB;

    if (! $cypherlab = $DB->get_record('cypherlab', array('id'=>$id))) {
        return false;
    }

    $result = true;
    // Now get rid of all files
    $fs = get_file_storage();
    if ($cm = get_coursemodule_from_instance('cypherlab', $cypherlab->id)) {
        $context = context_module::instance($cm->id);
        $fs->delete_area_files($context->id);
    }

    if (! $DB->delete_records('cypherlab_submissions', array('cypherlab'=>$cypherlab->id))) {
        $result = false;
    }

    if (! $DB->delete_records('event', array('modulename'=>'cypherlab', 'instance'=>$cypherlab->id))) {
        $result = false;
    }

    if (! $DB->delete_records('cypherlab', array('id'=>$cypherlab->id))) {
        $result = false;
    }

    grade_update('mod/cypherlab', $cypherlab->course, 'mod', 'cypherlab', $cypherlab->id, 0, NULL, array('deleted'=>1));

    return $result;
}

/**
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function cypherlab_supports($feature) {
    switch($feature) {
        case FEATURE_BACKUP_MOODLE2:          return true;

        default: return null;
    }
}
