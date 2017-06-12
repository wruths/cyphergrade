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
 * @package    mod_cypherlab
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the backup steps that will be used by the backup_cypherlab_activity_task
 */

/**
 * Define the complete cypherlab structure for backup, with file and id annotations
 */
class backup_cypherlab_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $cypherlab = new backup_nested_element('cypherlab', array('id'), array(
            'name', 'intro', 'introformat', 'cypherlabtype',
            'resubmit', 'preventlate', 'emailteachers', 'var1',
            'var2', 'var3', 'var4', 'var5',
            'maxbytes', 'timedue', 'timeavailable', 'grade',
            'timemodified'));

        $submissions = new backup_nested_element('submissions');

        $submission = new backup_nested_element('submission', array('id'), array(
            'userid', 'timecreated', 'timemodified', 'numfiles',
            'data1', 'data2', 'grade', 'submissioncomment',
            'format', 'teacher', 'timemarked', 'mailed'));

        // Build the tree

        // Apply for 'cypherlab' subplugins optional stuff at cypherlab level (not multiple)
        // Remember that order is important, try moving this line to the end and compare XML
        $this->add_subplugin_structure('cypherlab', $cypherlab, false);

        $cypherlab->add_child($submissions);
        $submissions->add_child($submission);

        // Apply for 'cypherlab' subplugins optional stuff at submission level (not multiple)
        $this->add_subplugin_structure('cypherlab', $submission, false);

        // Define sources
        $cypherlab->set_source_table('cypherlab', array('id' => backup::VAR_ACTIVITYID));

        // All the rest of elements only happen if we are including user info
        if ($userinfo) {
            $submission->set_source_table('cypherlab_submissions', array('cypherlab' => backup::VAR_PARENTID));
        }

        // Define id annotations
        $cypherlab->annotate_ids('scale', 'grade');
        $submission->annotate_ids('user', 'userid');
        $submission->annotate_ids('user', 'teacher');

        // Define file annotations
        $cypherlab->annotate_files('mod_cypherlab', 'intro', null); // This file area hasn't itemid
        $submission->annotate_files('mod_cypherlab', 'submission', 'id');
        $submission->annotate_files('mod_cypherlab', 'response', 'id');

        // Return the root element (cypherlab), wrapped into standard activity structure
        return $this->prepare_activity_structure($cypherlab);
    }
}
