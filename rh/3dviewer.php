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
 * @copyright  2024 onwards Rose-Hulman Institute of Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Data variables that we are looking out for.
$shadow = $_GET["shadow"] ?? 1;
$exposure = $_GET["exposure"] ?? .25;
?>
<html>
    <head>
        <meta name="viewport" content="width=device-width,initial-scale=0.47,maximum-scale=1">
        <script
            type="module"
            src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.5.0/model-viewer.min.js">
        </script>
        <script
            src="https://code.jquery.com/jquery-3.7.1.slim.min.js"
            integrity="sha256-kmHvs0B+OpCW5GVHUNjv9rOmY0IvSIRcf7zGUDTDQM8="
            crossorigin="anonymous">
        </script>
        <style>
            model-viewer {
                width: 100%;
                height: 100%;
            }

            #handle {
                cursor: pointer;
                text-align: center;
                font-size: 1.2em;
                line-height: 1em;
                background: #cbcbcb;
                padding: 4px;
            }

            #shelf {
                position: fixed;
                bottom: 0;
                width: 100%;
                left: 0;
                z-index: 9999;
                background: #e5e5e5;
            }

            #drawer {
                padding: 0 10px;
                display: none;
            }

            input {
                width: calc(100% - 20px);
            }
        </style>
    </head>
    <body style="background-color: #f0f0f0;">
        <model-viewer
            id="modelViewer"
            alt="<?php echo urldecode($_GET["file"]); ?>"
            src="<?php echo urldecode($_GET["file"]); ?>"
            shadow-intensity="<?php echo $shadow; ?>"
            camera-controls touch-action="pan-y"
            min-field-of-view='5deg'
            max-field-of-view='130deg'
            exposure="<?php echo $exposure; ?>"
            tone-mapping="neutral">
        </model-viewer>
        <div id="shelf">
            <div id="handle" onclick="jQuery('#drawer').toggle();">☰</div>
            <div id="drawer" style="display: none;">
                <form action="#" style="font-size: .8em">
                    <label for="exposure_val">Change Exposure</label> - <span><?php echo $exposure; ?></span>
                    <input type="range" id="exposure_val" min='0' max='30' step='.25' value='<?php echo $exposure; ?>' onchange="jQuery(this).prev('span').text(this.value);jQuery('#modelViewer').attr('exposure', this.value);">
                    <label for="shadow_val">Change Shadow</label> - <span><?php echo $shadow; ?></span>
                    <input type="range" id="shadow_val" min='0' max='10' step='.25' value='<?php echo $shadow; ?>' onchange="jQuery(this).prev('span').text(this.value);jQuery('#modelViewer').attr('shadow-intensity', this.value);">
                </form>
            </div>
        </div>
    </body>
</html>
