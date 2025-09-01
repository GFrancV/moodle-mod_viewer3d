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
 * Callback implementations for mod_viewer3d
 *
 * Documentation: {@link https://moodledev.io/docs/apis/plugintypes/mod}
 *
 * @package    mod_viewer3d
 * @copyright  2025 GFrancV <https://www.gfrancv.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Return if the plugin supports $feature.
 *
 * @param string $feature Constant representing the feature.
 * @return true | null True if the feature is supported, null otherwise.
 */
function viewer3d_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_INTRO => true,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_BACKUP_MOODLE2 => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        default => null,
    };
}

/**
 * Saves a new instance of the mod_viewer3d into the database.
 *
 * @param object $viewer3d An object from the form.
 * @param mod_viewer3d_mod_form $mform The form.
 * @return int The id of the newly inserted record.
 */
function viewer3d_add_instance($viewer3d, $mform = null) {
    global $DB, $CFG;

    $viewer3d->timecreated = time();
    $viewer3d->timemodified = time();

    // Save the STL file.
    $cmid = $viewer3d->coursemodule;
    $context = context_module::instance($cmid);

    // Process file uploads.
    if (!empty($viewer3d->stlfile)) {
        $viewer3d->stlfile = file_save_draft_area_files(
            $viewer3d->stlfile,
            $context->id,
            'mod_viewer3d',
            'stlfile',
            0,
            ['subdirs' => 0, 'maxfiles' => 1]
        );
    }

    $id = $DB->insert_record('viewer3d', $viewer3d);
    return $id;
}

/**
 * Updates an instance of the mod_viewer3d in the database.
 *
 * @param object $viewer3d An object from the form in mod_form.php.
 * @param mod_viewer3d_mod_form $mform The form.
 * @return bool True if successful, false otherwise.
 */
function viewer3d_update_instance($viewer3d, $mform = null) {
    global $DB, $CFG;

    $viewer3d->timemodified = time();
    $viewer3d->id = $viewer3d->instance;

    // Process file uploads.
    $cmid = $viewer3d->coursemodule;
    $context = context_module::instance($cmid);

    if (!empty($viewer3d->stlfile)) {
        $viewer3d->stlfile = file_save_draft_area_files(
            $viewer3d->stlfile,
            $context->id,
            'mod_viewer3d',
            'stlfile',
            0,
            ['subdirs' => 0, 'maxfiles' => 1]
        );
    }

    return $DB->update_record('viewer3d', $viewer3d);
}

/**
 * Removes an instance of the mod_viewer3d from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True if successful, false on failure.
 */
function viewer3d_delete_instance($id) {
    global $DB;

    $exists = $DB->get_record('viewer3d', ['id' => $id]);
    if (!$exists) {
        return false;
    }

    // Delete files associated with this activity.
    $cm = get_coursemodule_from_instance('viewer3d', $id);
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_viewer3d');

    $DB->delete_records('viewer3d', ['id' => $id]);

    return true;
}

/**
 * Returns the lists of all browsable file areas within the given module context.
 *
 * @param stdClass $course Course object
 * @param stdClass $cm Course module object
 * @param stdClass $context Context object
 * @return string[] Array of file areas
 */
function viewer3d_get_file_areas($course, $cm, $context) {
    return [
        'stlfile' => get_string('stlfile', 'viewer3d'),
    ];
}

/**
 * File browsing support for mod_viewer3d file areas.
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return null
 */
function viewer3d_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}

/**
 * Serves the files from the mod_viewer3d file areas.
 *
 * @param stdClass $course The course object
 * @param stdClass $cm The course module object
 * @param stdClass $context The context
 * @param string $filearea The name of the file area
 * @param array $args Extra arguments
 * @param bool $forcedownload Whether or not force download
 * @param array $options Additional options affecting the file serving
 * @return bool False if file not found, does not return if found - just sends the file
 */
function viewer3d_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=[]) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    if ($filearea !== 'stlfile') {
        return false;
    }

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/'.implode('/', $args).'/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_viewer3d', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }

    send_stored_file($file, 86400, 0, $forcedownload, $options);
}
