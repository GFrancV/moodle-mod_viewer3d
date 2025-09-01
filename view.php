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

// Show intro if any.
if (!empty($moduleinstance->intro)) {
    echo $OUTPUT->box(format_module_intro('viewer3d', $moduleinstance, $cm->id), 'generalbox', 'intro');
}

// Show the 3D viewer.
?>
<div id="viewer3d-container" style="width: 100%; height: 500px;"></div>

<?php if ($candownload && !empty($stlurl)): ?>
<div class="mt-3">
    <a href="<?php echo $stlurl; ?>" class="btn btn-secondary" download>
        <?php echo get_string('downloadmodel', 'viewer3d'); ?>
    </a>
</div>
<?php endif; ?>

<!-- Three.js library and extensions -->
<script src="https://cdn.jsdelivr.net/npm/three@0.132.2/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.132.2/examples/js/loaders/STLLoader.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.132.2/examples/js/controls/OrbitControls.js"></script>

<script>
// Variables globales
let scene, camera, renderer, controls, mesh;
const container = document.getElementById('viewer3d-container');

// Inicializar la escena
function init() {
    // Crear escena
    scene = new THREE.Scene();
    scene.background = new THREE.Color(0xf0f0f0);

    // Crear cámara
    camera = new THREE.PerspectiveCamera(75, container.clientWidth / container.clientHeight, 0.1, 1000);
    camera.position.z = 5;

    // Crear renderer
    renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setSize(container.clientWidth, container.clientHeight);
    container.appendChild(renderer.domElement);

    // Añadir luces
    const ambientLight = new THREE.AmbientLight(0x404040);
    scene.add(ambientLight);

    const directionalLight = new THREE.DirectionalLight(0xffffff, 0.5);
    directionalLight.position.set(1, 1, 1);
    scene.add(directionalLight);

    // Añadir controles de órbita para rotación y zoom
    controls = new THREE.OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.25;
    controls.enableZoom = true;

    // Cargar el modelo STL
    loadSTLModel();

    // Manejar el redimensionamiento de la ventana
    window.addEventListener('resize', onWindowResize);

    // Iniciar el bucle de renderizado
    animate();
}

// Cargar el modelo STL
function loadSTLModel() {
    const loader = new THREE.STLLoader();

    // Cargar el archivo STL desde la URL proporcionada por Moodle
    const stlUrl = '<?php echo $stlurl; ?>';

    if (stlUrl) {
        loader.load(
            stlUrl,
            function (geometry) {
                const material = new THREE.MeshPhongMaterial({
                    color: 0x00aaff,
                    specular: 0x111111,
                    shininess: 200
                });
                mesh = new THREE.Mesh(geometry, material);

                // Centrar el modelo
                geometry.computeBoundingBox();
                const boundingBox = geometry.boundingBox;
                const center = new THREE.Vector3();
                boundingBox.getCenter(center);
                mesh.position.set(-center.x, -center.y, -center.z);

                // Ajustar la cámara para ver todo el modelo
                const maxDim = Math.max(
                    boundingBox.max.x - boundingBox.min.x,
                    boundingBox.max.y - boundingBox.min.y,
                    boundingBox.max.z - boundingBox.min.z
                );
                camera.position.z = maxDim * 2;

                scene.add(mesh);
            },
            function (xhr) {
                console.log((xhr.loaded / xhr.total * 100) + '% cargado');
            },
            function (error) {
                console.error('Error al cargar el modelo STL', error);
                container.innerHTML = '<div class="alert alert-danger">Error al cargar el modelo 3D</div>';
            }
        );
    } else {
        container.innerHTML = '<div class="alert alert-warning">No se encontró ningún archivo STL</div>';
    }
}

// Manejar el redimensionamiento de la ventana
function onWindowResize() {
    camera.aspect = container.clientWidth / container.clientHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(container.clientWidth, container.clientHeight);
}

// Función de animación
function animate() {
    requestAnimationFrame(animate);
    controls.update();
    renderer.render(scene, camera);
}

// Iniciar la aplicación cuando se cargue la página
document.addEventListener('DOMContentLoaded', init);
</script>

<?php
echo $OUTPUT->footer();
