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
 * Rose Hulman 3d File Viewer.
 *
 * Routes by file extension:
 *   glb, gltf                                  -> <model-viewer>
 *   stl, obj, ply, fbx, dae, 3mf               -> three.js
 *   step, stp, iges, igs, brep, 3ds, wrl       -> Online 3D Viewer (WASM CAD import)
 *
 * @copyright  2024 onwards Rose-Hulman Institute of Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// ---- Configuration ---------------------------------------------------------
$modelviewerexts = ['glb', 'gltf'];
$threeexts       = ['stl', 'obj', 'ply', 'fbx', 'dae', '3mf'];
$cadexts         = ['step', 'stp', 'iges', 'igs', 'brep', '3ds', 'wrl'];
// Extra hosts (besides this server) that model files may be loaded from.
$extrahosts      = [];
// Pinned CDN versions. Test before bumping.
$threeversion    = '0.170.0';
$ovversion       = '0.16.0';

// ---- Input validation ------------------------------------------------------
$file = $_GET['file'] ?? '';   // PHP has already URL-decoded this.
$parts = parse_url($file);
$ext = strtolower(pathinfo($parts['path'] ?? '', PATHINFO_EXTENSION));
$allexts = array_merge($modelviewerexts, $threeexts, $cadexts);

$scheme = strtolower($parts['scheme'] ?? '');
$host = strtolower($parts['host'] ?? '');
$myhost = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));

$ok = $file !== ''
    && $parts !== false
    && in_array($ext, $allexts, true)
    && ($scheme === '' || in_array($scheme, ['http', 'https'], true))
    && ($host === '' || $host === $myhost || in_array($host, $extrahosts, true));

if (!$ok) {
    http_response_code(400);
    exit('Unsupported or invalid file.');
}

$shadow   = (float)($_GET['shadow'] ?? 1);
$exposure = (float)($_GET['exposure'] ?? .25);
$shadow   = max(0, min(10, $shadow));
$exposure = max(0, min(30, $exposure));

$e = function ($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
};
$jsonflags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

$mode = in_array($ext, $modelviewerexts, true) ? 'modelviewer'
      : (in_array($ext, $threeexts, true) ? 'three' : 'cad');
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=0.47,maximum-scale=1">
        <style>
            html, body { margin: 0; height: 100%; background-color: #f0f0f0; font-family: sans-serif; }
            model-viewer, #stage { width: 100%; height: 100%; display: block; }
            #status { position: absolute; top: 45%; width: 100%; text-align: center; color: #555; pointer-events: none; }
            #handle { cursor: pointer; text-align: center; font-size: 1.2em; line-height: 1em; background: #cbcbcb; padding: 4px; user-select: none; }
            #shelf { position: fixed; bottom: 0; width: 100%; left: 0; z-index: 9999; background: #e5e5e5; }
            #drawer { padding: 0 10px; display: none; font-size: .8em; }
            #drawer input { width: calc(100% - 20px); }
        </style>
<?php if ($mode === 'modelviewer'): ?>
        <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.5.0/model-viewer.min.js"></script>
<?php elseif ($mode === 'three'): ?>
        <script type="importmap">
        { "imports": {
            "three": "https://cdn.jsdelivr.net/npm/three@<?php echo $threeversion; ?>/build/three.module.js",
            "three/addons/": "https://cdn.jsdelivr.net/npm/three@<?php echo $threeversion; ?>/examples/jsm/"
        } }
        </script>
<?php else: ?>
        <script src="https://cdn.jsdelivr.net/npm/online-3d-viewer@<?php echo $ovversion; ?>/build/o3dv.min.js"></script>
<?php endif; ?>
    </head>
    <body>
<?php if ($mode === 'modelviewer'): ?>
        <model-viewer
            id="modelViewer"
            alt="<?php echo $e(basename($parts['path'])); ?>"
            src="<?php echo $e($file); ?>"
            shadow-intensity="<?php echo $shadow; ?>"
            camera-controls touch-action="pan-y"
            min-field-of-view="5deg"
            max-field-of-view="130deg"
            exposure="<?php echo $exposure; ?>"
            tone-mapping="neutral">
        </model-viewer>
        <script>
            var viewerApi = {
                setExposure: function (v) { document.getElementById('modelViewer').setAttribute('exposure', v); },
                setShadow: function (v) { document.getElementById('modelViewer').setAttribute('shadow-intensity', v); }
            };
        </script>

<?php elseif ($mode === 'three'): ?>
        <div id="stage"></div>
        <div id="status">Loading model&hellip;</div>
        <script>
            // Placeholder so the sliders never throw before the module finishes loading.
            var viewerApi = { setExposure: function () {}, setShadow: function () {} };
        </script>
        <script type="module">
            import * as THREE from 'three';
            import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
            import { STLLoader }     from 'three/addons/loaders/STLLoader.js';
            import { OBJLoader }     from 'three/addons/loaders/OBJLoader.js';
            import { PLYLoader }     from 'three/addons/loaders/PLYLoader.js';
            import { FBXLoader }     from 'three/addons/loaders/FBXLoader.js';
            import { ColladaLoader } from 'three/addons/loaders/ColladaLoader.js';
            import { ThreeMFLoader } from 'three/addons/loaders/3MFLoader.js';

            const url = <?php echo json_encode($file, $jsonflags); ?>;
            const ext = <?php echo json_encode($ext); ?>;
            const stage = document.getElementById('stage');
            const status = document.getElementById('status');
            let shadowIntensity = <?php echo $shadow; ?>;

            const renderer = new THREE.WebGLRenderer({ antialias: true });
            renderer.setPixelRatio(window.devicePixelRatio);
            renderer.toneMapping = THREE.NeutralToneMapping;
            renderer.toneMappingExposure = <?php echo $exposure; ?>;
            renderer.shadowMap.enabled = true;
            renderer.shadowMap.type = THREE.PCFSoftShadowMap;
            stage.appendChild(renderer.domElement);

            const scene = new THREE.Scene();
            scene.background = new THREE.Color(0xf0f0f0);
            const camera = new THREE.PerspectiveCamera(45, 1, 0.01, 10000);
            scene.add(new THREE.HemisphereLight(0xffffff, 0x888888, 2));
            const dir = new THREE.DirectionalLight(0xffffff, 2);
            dir.castShadow = true;
            dir.shadow.mapSize.set(2048, 2048);
            scene.add(dir, dir.target);

            const ground = new THREE.Mesh(
                new THREE.PlaneGeometry(1, 1),
                new THREE.ShadowMaterial({ opacity: 0 })
            );
            ground.rotation.x = -Math.PI / 2;
            ground.receiveShadow = true;
            scene.add(ground);

            const controls = new OrbitControls(camera, renderer.domElement);
            controls.enableDamping = true;

            function applyShadow() {
                ground.material.opacity = Math.min(shadowIntensity / 2, 1) * 0.6;
            }
            applyShadow();
            viewerApi.setExposure = v => { renderer.toneMappingExposure = parseFloat(v); };
            viewerApi.setShadow = v => { shadowIntensity = parseFloat(v); applyShadow(); };

            function resize() {
                const w = stage.clientWidth, h = stage.clientHeight;
                renderer.setSize(w, h);
                camera.aspect = w / h;
                camera.updateProjectionMatrix();
            }
            window.addEventListener('resize', resize);
            resize();

            const defaultMat = new THREE.MeshStandardMaterial({
                color: 0xb0b8c0, metalness: 0.1, roughness: 0.6, side: THREE.DoubleSide
            });

            // CAD-style formats are usually Z-up; three.js is Y-up.
            const zUp = ['stl', 'ply', '3mf'].includes(ext);

            const loaders = {
                stl:  [new STLLoader(), g => new THREE.Mesh(g, defaultMat)],
                ply:  [new PLYLoader(), g => {
                    g.computeVertexNormals();
                    const mat = g.hasAttribute('color')
                        ? new THREE.MeshStandardMaterial({ vertexColors: true, roughness: 0.6, side: THREE.DoubleSide })
                        : defaultMat;
                    return new THREE.Mesh(g, mat);
                }],
                obj:  [new OBJLoader(), o => {
                    // No MTL support for single-file uploads, so use the default material.
                    o.traverse(c => { if (c.isMesh) c.material = defaultMat; });
                    return o;
                }],
                fbx:  [new FBXLoader(), o => o],
                dae:  [new ColladaLoader(), o => o.scene],
                '3mf': [new ThreeMFLoader(), o => o],
            };

            const [loader, toObject] = loaders[ext];
            loader.load(url, result => {
                const obj = toObject(result);
                obj.traverse(c => { if (c.isMesh) { c.castShadow = true; c.receiveShadow = true; } });

                const wrapper = new THREE.Group();
                if (zUp) { obj.rotation.x = -Math.PI / 2; }
                wrapper.add(obj);
                scene.add(wrapper);
                wrapper.updateMatrixWorld(true);

                const box = new THREE.Box3().setFromObject(wrapper);
                const center = box.getCenter(new THREE.Vector3());
                const dims = box.getSize(new THREE.Vector3());
                const size = dims.length() || 1;
                wrapper.position.sub(center);

                // Ground plane sits under the model.
                ground.scale.setScalar(size * 4);
                ground.position.y = -dims.y / 2 - size * 0.001;

                // Light and shadow frustum sized to the model.
                dir.position.set(size, size * 1.5, size);
                dir.target.position.set(0, 0, 0);
                const sc = dir.shadow.camera;
                sc.left = sc.bottom = -size; sc.right = sc.top = size;
                sc.near = 0.01; sc.far = size * 6;
                sc.updateProjectionMatrix();

                camera.position.set(size * 0.8, size * 0.6, size * 0.8);
                camera.near = size / 1000;
                camera.far = size * 100;
                camera.updateProjectionMatrix();
                controls.target.set(0, 0, 0);
                controls.update();
                status.remove();
            }, undefined, err => {
                status.textContent = 'Could not load model.';
                console.error(err);
            });

            renderer.setAnimationLoop(() => { controls.update(); renderer.render(scene, camera); });
        </script>

<?php else: ?>
        <div id="stage"></div>
        <div id="status">Loading model&hellip;</div>
        <script>
            // Exposure/shadow controls don't apply to this viewer.
            var viewerApi = null;
            OV.SetExternalLibLocation('https://cdn.jsdelivr.net/npm/online-3d-viewer@<?php echo $ovversion; ?>/libs');
            var viewer = new OV.EmbeddedViewer(document.getElementById('stage'), {
                backgroundColor: new OV.RGBAColor(240, 240, 240, 255),
                defaultColor: new OV.RGBColor(176, 184, 192),
                edgeSettings: new OV.EdgeSettings(false, new OV.RGBColor(0, 0, 0), 1),
                onModelLoaded: function () { var s = document.getElementById('status'); if (s) { s.remove(); } }
            });
            viewer.LoadModelFromUrlList([<?php echo json_encode($file, $jsonflags); ?>]);
        </script>
<?php endif; ?>

<?php if ($mode !== 'cad'): ?>
        <div id="shelf">
            <div id="handle">&#9776;</div>
            <div id="drawer">
                <form action="#" onsubmit="return false;">
                    <label for="exposure_val">Change Exposure</label> - <span><?php echo $exposure; ?></span>
                    <input type="range" id="exposure_val" min="0" max="30" step=".25" value="<?php echo $exposure; ?>">
                    <label for="shadow_val">Change Shadow</label> - <span><?php echo $shadow; ?></span>
                    <input type="range" id="shadow_val" min="0" max="10" step=".25" value="<?php echo $shadow; ?>">
                </form>
            </div>
        </div>
        <script>
            (function () {
                var drawer = document.getElementById('drawer');
                document.getElementById('handle').addEventListener('click', function () {
                    drawer.style.display = drawer.style.display === 'block' ? 'none' : 'block';
                });
                function bind(id, fn) {
                    var el = document.getElementById(id);
                    el.addEventListener('input', function () {
                        el.previousElementSibling.textContent = el.value;
                        fn(el.value);
                    });
                }
                bind('exposure_val', function (v) { viewerApi.setExposure(v); });
                bind('shadow_val', function (v) { viewerApi.setShadow(v); });
            })();
        </script>
<?php endif; ?>
    </body>
</html>