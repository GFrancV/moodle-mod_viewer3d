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
 * View 3D Viewer instance
 *
 * @package    mod_viewer3d
 * @copyright  2025 GFrancV <https://www.gfrancv.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

// Course module id.
$id = optional_param('id', 0, PARAM_INT);

// Activity instance id.
$t = optional_param('t', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('viewer3d', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('viewer3d', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    $moduleinstance = $DB->get_record('viewer3d', ['id' => $t], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $moduleinstance->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('viewer3d', $moduleinstance->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/viewer3d:view', $context);

// Trigger module viewed event.
$event = \mod_viewer3d\event\course_module_viewed::create([
    'objectid' => $moduleinstance->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('viewer3d', $moduleinstance);
$event->trigger();

$PAGE->set_url('/mod/viewer3d/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Get the STL file URL.
$fs = get_file_storage();
$files = $fs->get_area_files($context->id, 'mod_viewer3d', 'stlfile', 0, 'sortorder', false);

$stlurl = '';
if ($files) {
    $file = reset($files);
    $stlurl = moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        $file->get_component(),
        $file->get_filearea(),
        $file->get_itemid(),
        $file->get_filepath(),
        $file->get_filename()
    )->out();
}

// Check if user can download the model.
$candownload = has_capability('mod/viewer3d:download', $context);

// Output starts here.
echo $OUTPUT->header();

// Three.js library and extensions.
echo '<script src="https://cdn.jsdelivr.net/npm/three@0.132.2/build/three.min.js"></script>';
echo '<script src="https://cdn.jsdelivr.net/npm/three@0.132.2/examples/js/loaders/STLLoader.js"></script>';
echo '<script src="https://cdn.jsdelivr.net/npm/three@0.132.2/examples/js/controls/OrbitControls.js"></script>';

$contextdata = [
    'stlurl' => $stlurl,
    'candownload' => $candownload,
];
echo $OUTPUT->render_from_template('mod_viewer3d/viewer', $contextdata);

$PAGE->requires->js_call_amd('mod_viewer3d/viewer3d', 'init', [$stlurl]);

echo $OUTPUT->footer();
