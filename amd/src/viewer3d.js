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
 * Viewer3D module to display STL files using Three.js
 *
 * @module     mod_viewer3d/viewer3d
 * @copyright  2025 GFrancV <https://www.gfrancv.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_string as getString} from 'core/str';

let scene, camera, renderer, controls, mesh;
const container = document.getElementById('viewer3d-container');

/**
 * Load the STL model from the provided URL
 *
 * @param {string} stlurl - URL of the STL file
 * @returns {void}
 */
function loadSTLModel(stlurl) {
  // eslint-disable-next-line no-undef
  const loader = new THREE.STLLoader();

  // Cargar el archivo STL desde la URL proporcionada por Moodle
  const stlUrl = stlurl;

  if (stlUrl) {
    loader.load(
      stlUrl,
      function(geometry) {
        // eslint-disable-next-line no-undef
        const material = new THREE.MeshPhongMaterial({
          color: 0x00aaff,
          specular: 0x111111,
          shininess: 200
        });
        // eslint-disable-next-line no-undef
        mesh = new THREE.Mesh(geometry, material);

        // Centrar el modelo
        geometry.computeBoundingBox();
        const boundingBox = geometry.boundingBox;
        // eslint-disable-next-line no-undef
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
      function(xhr) {
        // eslint-disable-next-line no-console
        console.log((xhr.loaded / xhr.total * 100) + '% cargado');
      },
      function(error) {
        // eslint-disable-next-line no-console
        console.error(getString('nofoundstl', 'viewer3d'), error);
        container.innerHTML = `<div class="alert alert-danger">${getString('nofoundstl', 'viewer3d')}</div>`;
      }
    );
  } else {
    container.innerHTML = `<div class="alert alert-warning">${getString('nofoundstl', 'viewer3d')}</div>`;
  }
}

/**
 * Update the renderer and camera size when resizing the window
 *
 * @returns {void}
 */
function onWindowResize() {
  camera.aspect = container.clientWidth / container.clientHeight;
  camera.updateProjectionMatrix();
  renderer.setSize(container.clientWidth, container.clientHeight);
}

/**
 * Animation loop
 *
 * @returns {void}
 */
function animate() {
  requestAnimationFrame(animate);
  controls.update();
  renderer.render(scene, camera);
}

export const init = (stlurl) => {
  // Crear escena
  // eslint-disable-next-line no-undef
  scene = new THREE.Scene();
  // eslint-disable-next-line no-undef
  scene.background = new THREE.Color(0xf0f0f0);

  // Crear cámara
  // eslint-disable-next-line no-undef
  camera = new THREE.PerspectiveCamera(75, container.clientWidth / container.clientHeight, 0.1, 1000);
  camera.position.z = 5;

  // Crear renderer
  // eslint-disable-next-line no-undef
  renderer = new THREE.WebGLRenderer({antialias: true});
  renderer.setSize(container.clientWidth, container.clientHeight);
  container.appendChild(renderer.domElement);

  // Añadir luces
  // eslint-disable-next-line no-undef
  const ambientLight = new THREE.AmbientLight(0x404040);
  scene.add(ambientLight);

  // eslint-disable-next-line no-undef
  const directionalLight = new THREE.DirectionalLight(0xffffff, 0.5);
  directionalLight.position.set(1, 1, 1);
  scene.add(directionalLight);

  // Añadir controles de órbita para rotación y zoom
  // eslint-disable-next-line no-undef
  controls = new THREE.OrbitControls(camera, renderer.domElement);
  controls.enableDamping = true;
  controls.dampingFactor = 0.25;
  controls.enableZoom = true;

  // Cargar el modelo STL
  loadSTLModel(stlurl);

  // Manejar el redimensionamiento de la ventana
  window.addEventListener('resize', onWindowResize);

  // Iniciar el bucle de renderizado
  animate();
};
document.addEventListener('DOMContentLoaded', init);