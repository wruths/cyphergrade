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
 * Define all the restore steps that will be used by the restore_cypherlab_activity_task
 */

/**
 * Structure step to restore one cypherlab activity
 */
class restore_cypherlab_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $cypherlab = new restore_path_element('cypherlab', '/activity/cypherlab');
        $paths[] = $cypherlab;

        // Apply for 'cypherlab' subplugins optional paths at cypherlab level
        $this->add_subplugin_structure('cypherlab', $cypherlab);

        if ($userinfo) {
            $submission = new restore_path_element('cypherlab_submission', '/activity/cypherlab/submissions/submission');
            $paths[] = $submission;
            // Apply for 'cypherlab' subplugins optional stuff at submission level
            $this->add_subplugin_structure('cypherlab', $submission);
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_cypherlab($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timedue = $this->apply_date_offset($data->timedue);
        $data->timeavailable = $this->apply_date_offset($data->timeavailable);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        if ($data->grade < 0) { // scale found, get mapping
            $data->grade = -($this->get_mappingid('scale', abs($data->grade)));
        }

        // insert the cypherlab record
        $newitemid = $DB->insert_record('cypherlab', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);

        // Hide unsupported sub-plugins
        if (!$this->is_valid_cypherlab_subplugin($data->cypherlabtype)) {
            $DB->set_field('course_modules', 'visible', 0, array('id' => $this->get_task()->get_moduleid()));
        }
    }

    protected function process_cypherlab_submission($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->cypherlab = $this->get_new_parentid('cypherlab');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timemarked = $this->apply_date_offset($data->timemarked);

        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->teacher = $this->get_mappingid('user', $data->teacher);

        $newitemid = $DB->insert_record('cypherlab_submissions', $data);
        $this->set_mapping('cypherlab_submission', $oldid, $newitemid, true); // Going to have files
        $this->set_mapping(restore_gradingform_plugin::itemid_mapping('submission'), $oldid, $newitemid);
    }

    /**
     * This function will attempt to upgrade the newly restored cypherlab to an instance of mod_assign if
     * mod_cypherlab is currently disabled and mod_assign is enabled and mod_assign says it can upgrade this cypherlab.
     *
     * @return none
     */
    private function upgrade_mod_assign() {
        global $DB, $CFG;

        // The current module must exist.
        $pluginmanager = core_plugin_manager::instance();

        $plugininfo = $pluginmanager->get_plugin_info('mod_assign');

        // Check that the cypherlab module is installed.
        if ($plugininfo && $plugininfo->is_installed_and_upgraded()) {
            // Include the required mod assign upgrade code.
            require_once($CFG->dirroot . '/mod/assign/upgradelib.php');
            require_once($CFG->dirroot . '/mod/assign/locallib.php');

            // Get the id and type of this cypherlab.
            $newinstance = $this->task->get_activityid();

            $record = $DB->get_record('cypherlab', array('id'=>$newinstance), 'cypherlabtype', MUST_EXIST);
            $type = $record->cypherlabtype;

            $subplugininfo = $pluginmanager->get_plugin_info('cypherlab_' . $type);

            // See if it is possible to upgrade.
            if (assign::can_upgrade_cypherlab($type, $subplugininfo->versiondb)) {
                $cypherlab_upgrader = new assign_upgrade_manager();
                $log = '';
                $success = $cypherlab_upgrader->upgrade_cypherlab($newinstance, $log);
                if (!$success) {
                    throw new restore_step_exception('mod_assign_upgrade_failed', $log);
                }
            }
        }
    }

    protected function after_execute() {
        // Add cypherlab related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_cypherlab', 'intro', null);
        // Add cypherlab submission files, matching by cypherlab_submission itemname
        $this->add_related_files('mod_cypherlab', 'submission', 'cypherlab_submission');
        $this->add_related_files('mod_cypherlab', 'response', 'cypherlab_submission');
    }

    /**
     * Hook to execute cypherlab upgrade after restore.
     */
    protected function after_restore() {

        if ($this->get_task()->get_mode() != backup::MODE_IMPORT) {
            // Moodle 2.2 cypherlab upgrade
            $this->upgrade_mod_assign();
        }
    }

    /**
     * Determine if a sub-plugin is supported or not
     *
     * @param string $type
     * @return bool
     */
    protected function is_valid_cypherlab_subplugin($type) {
        static $subplugins = null;

        if (is_null($subplugins)) {
            $subplugins = get_plugin_list('cypherlab');
        }
        return array_key_exists($type, $subplugins);
    }
}
