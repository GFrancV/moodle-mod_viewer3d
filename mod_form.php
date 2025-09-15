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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Form for adding and editing mod_viewer3d instances
 *
 * @package    mod_viewer3d
 * @copyright  2025 GFrancV <https://www.gfrancv.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_viewer3d_mod_form extends moodleform_mod {
    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        // General fieldset.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $filemanageroptions = [];
        $filemanageroptions['accepted_types'] = ['.stl'];
        $filemanageroptions['maxbytes'] = 0;
        $filemanageroptions['maxfiles'] = 1;
        $filemanageroptions['subdirs'] = 0;

        $mform->addElement('filemanager', 'stlfile', get_string('stlfile', 'viewer3d'), null, $filemanageroptions);
        $mform->addRule('stlfile', null, 'required', null, 'client');
        $mform->addHelpButton('stlfile', 'stlfile', 'viewer3d');

        // Other standard elements that are displayed in their own fieldsets.
        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

     /**
      * Prepares the form before data are set.
      *
      * @param array $defaultvalues The default values of the form
      */
    public function data_preprocessing(&$defaultvalues) {
        $draftitemid = file_get_submitted_draft_itemid('stlfile');
        file_prepare_draft_area(
            $draftitemid,
            $this->context->id,
            'mod_viewer3d',
            'stlfile',
            0,
            ['subdirs' => 0, 'maxfiles' => 1]
        );
        $defaultvalues['stlfile'] = $draftitemid;
    }

    /**
     * Validation of the form data.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check if STL file is uploaded.
        if (empty($data['stlfile'])) {
            $errors['stlfile'] = get_string('required');
        }

        return $errors;
    }
}
