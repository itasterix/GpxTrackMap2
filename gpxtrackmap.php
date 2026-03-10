<?php
/**
 * @author      DP informatica
 * based on the original work of Frank Ingermann - info@frankingermann.de
 * @copyright   DP informatica
 * @package     GPXTrackMap - GPX track display on maps using the OpenLayers API
 *              Content Plugin for Joomla 5.x
 * @version     2.0.1 - Modernized for Joomla 5 + PHP 8+
 * @link        https://dpinformatica.altervista.org/
 *
 * @license GNU/GPL v3 or later
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *  GNU General Public License for more details.
 *
 *  For the GNU General Public License, see <http://www.gnu.org/licenses/>.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Path;
use Joomla\Archive\Archive;

class PlgContentGPXTrackMap extends CMSPlugin
{
    protected array  $_params          = [];
    protected string $_absolute_path   = '';
    protected string $_rootfolder      = ''; // WITH trailing /
    protected string $_rootpath        = ''; // WITHOUT trailing /
    protected string $_live_site       = '';
    protected string $_plugin_dir      = '';
    protected string $_markers_dir     = '';
    protected string $_warnings        = '';
    protected string $_gtmversion      = 'V2.0.1';

    // -------------------------------------------------------------------------
    // Session-like counters stored in application object (no raw $_SESSION)
    // -------------------------------------------------------------------------

    private function getCounter(string $key): int
    {
        return (int) Factory::getApplication()->getUserState($key, -1);
    }

    private function setCounter(string $key, int $value): void
    {
        Factory::getApplication()->setUserState($key, $value);
    }

    private function unsetCounter(string $key): void
    {
        Factory::getApplication()->setUserState($key, null);
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function __construct(&$subject, array $config = [])
    {
        parent::__construct($subject, $config);

        // Load translations directly from plugin root folder.
        // parse_ini_file + ReflectionClass injects strings into Joomla Language object.
        $iniFile = JPATH_PLUGINS . '/content/gpxtrackmap/plg_content_gpxtrackmap.ini';
        if (file_exists($iniFile)) {
            $strings = parse_ini_file($iniFile);
            if (is_array($strings) && count($strings) > 0) {
                $lang = Factory::getApplication()->getLanguage();
                $ref  = new \ReflectionClass($lang);
                $prop = $ref->getProperty('strings');
                $prop->setAccessible(true);
                $existing = $prop->getValue($lang) ?? [];
                $prop->setValue($lang, array_merge($existing, array_change_key_case($strings, CASE_UPPER)));
            }
        }

        $this->unsetCounter('gtmcount');
        $this->unsetCounter('gtmcountarticles');
    }

    // -------------------------------------------------------------------------
    // onContentPrepare
    // -------------------------------------------------------------------------

    public function onContentPrepare(string $context, object &$article, mixed &$params, int $limitstart): bool
    {
        if (!preg_match('@\{gpxtrackmap\}(.*)\{/gpxtrackmap\}@Us', $article->text)) {
            return true;
        }

        if ($this->getCounter('gtmcountarticles') === -1) {
            $this->setCounter('gtmcountarticles', -1);
        }

        $app = Factory::getApplication();

        $this->_absolute_path = JPATH_SITE;
        $this->_live_site     = rtrim(Uri::base(), '/');
        $this->_plugin_dir    = $this->_live_site . '/plugins/content/gpxtrackmap/';
        $this->_markers_dir   = $this->_plugin_dir . 'markers/';

        if (preg_match_all('@\{gpxtrackmap\}(.*)\{/gpxtrackmap\}@Us', $article->text, $matches, PREG_PATTERN_ORDER) > 0) {

            // In Joomla 5 plugin language files are installed to /administrator/language/
            $lang = $app->getLanguage();
            $lang->load('plg_content_gpxtrackmap', JPATH_ADMINISTRATOR, null, true);

            $counterArticles = $this->getCounter('gtmcountarticles') + 1;
            $this->setCounter('gtmcountarticles', $counterArticles);

            $plginpath = JPATH_PLUGINS . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'gpxtrackmap' . DIRECTORY_SEPARATOR;

            $scriptfn = $plginpath . 'gpxtrackmap.js';

            if (!file_exists($scriptfn)) {
                echo '<h2 style="color:red;"><em>GPXTrackMap Plugin error: Template script file ' . $scriptfn . ' not found!</em></h2>';
                return true;
            }

            if ($this->getCounter('gtmcount') === -1) {
                $this->setCounter('gtmcount', -1);
            }

            if ($counterArticles === 0) {
                $scriptsrc_ol  = $this->params->get('scriptsrc-ol', '');
                $scriptsrc_osm = $this->params->get('scriptsrc-osm', '');

                if ($scriptsrc_ol  === '') { $scriptsrc_ol  = '/plugins/content/OpenLayers.2.13.2.full.js'; }
                if ($scriptsrc_osm === '') { $scriptsrc_osm = '/plugins/content/OpenStreetMap.js'; }

                $head_js = '<script src="' . $scriptsrc_ol  . '"></script>' . "\n" .
                           '<script src="' . $scriptsrc_osm . '"></script>' . "\n";


                $document = $app->getDocument();

                if ($document instanceof HtmlDocument) {
                    $document->addCustomTag($head_js);

                    $head_css = '<style type="text/css">'
                        . ' div.gpxtrack div.olMap img, div.gpxtrack div.olMap svg {max-width:inherit !important;}'
                        . ' div.gpxtrack img.olTileImage {max-width:1000px !important;}'
                        . ' div.gpxtrack div.olControlLayerSwitcher label {display:inline;font-size:11px;font-weight:bold;border-top:2px;}'
                        . ' div.gpxtrack input.olButton {margin-right:3px;margin-top:0;}'
                        . '</style>' . "\n";
                    $document->addCustomTag($head_css);
                }
            }

            if (!$this->params->get('gpxroot')) {
                $this->_rootfolder = '/images/gpx/';
            } else {
                $this->_rootfolder = $this->params->get('gpxroot');
                if (substr($this->_rootfolder, -1) !== '/') {
                    $this->_rootfolder .= '/';
                }
            }

            $this->_rootpath = substr($this->_rootfolder, 0, -1);

            foreach ($matches[0] as $match) {

                $scripttext = file_get_contents($scriptfn);

                $gtmcount = $this->getCounter('gtmcount') + 1;
                $this->setCounter('gtmcount', $gtmcount);

                $gtmcode  = '';
                $gpx_code  = preg_replace('@\{.+?\}@', '', $match);
                $gpx_array = explode(',', $gpx_code);
                $gpx_file  = $gpx_array[0];

                if (str_starts_with($gpx_file, 'http://') || str_starts_with($gpx_file, 'https://')) {
                    $externalgpx  = 1;
                    $gpx_path     = $gpx_file;
                    $gpx_file     = parse_url($gpx_file, PHP_URL_PATH);
                    $localpath    = parse_url($this->_live_site, PHP_URL_PATH);
                    $gpx_file     = substr($gpx_file, strlen($localpath));
                    $gpx_filepath = $this->_absolute_path . $gpx_file;
                    $path_parts   = pathinfo($gpx_file);
                    $gpx_file     = $path_parts['basename'];
                    $gpx_dir      = $this->_absolute_path . DIRECTORY_SEPARATOR . $path_parts['dirname'] . '/';
                    $gpx_basepath = $this->_live_site . $path_parts['dirname'] . '/';
                } else {
                    $externalgpx  = 0;
                    $gpx_dir      = $this->_absolute_path . DIRECTORY_SEPARATOR . $this->_rootfolder;
                    $gpx_basepath = $this->_live_site . $this->_rootfolder;
                    $gpx_path     = $gpx_basepath . $gpx_file;
                    $gpx_filepath = $this->_absolute_path . $this->_rootfolder . $gpx_file;
                }

                if (!File::exists($gpx_filepath)) {
                    $this->_warnings .= '<h2 style="color:red;"><em>GPXTrackMap Plugin error: GPX file "'
                        . $this->_rootfolder . $gpx_file . '" not found!</em></h2>';
                } else {
                    $this->_params = [];

                    if (count($gpx_array) >= 2) {
                        for ($i = 1; $i < count($gpx_array); $i++) {
                            $parameter_temp = explode('=', $gpx_array[$i]);
                            if (count($parameter_temp) >= 2) {
                                $this->_params[strtolower(trim($parameter_temp[0]))] = trim($parameter_temp[1]);
                            }
                        }
                    }

                    $this->collectParams();

                    $tpldir = rtrim($plginpath, DIRECTORY_SEPARATOR);

                    if (!isset($this->_params['tpl'])) {
                        $this->_params['tpl'] = $this->params->get('tpldefault');
                    }
                    $tpl = $this->_params['tpl'];

                    $tplfn = match ((int)$tpl) {
                        1  => $this->params->get('tpl1'),
                        2  => $this->params->get('tpl2'),
                        3  => $this->params->get('tpl3'),
                        4  => $this->params->get('tpl4'),
                        5  => $this->params->get('tpl5'),
                        6  => $this->params->get('tpl6'),
                        7  => $this->params->get('tpl7'),
                        8  => $this->params->get('tpl8'),
                        9  => $this->params->get('tpl9'),
                        10 => $this->params->get('tpl10'),
                        default => (!ctype_digit((string)$tpl) ? $tpl : ''),
                    };

                    $templateDir = JPATH_SITE . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR
                        . $app->getTemplate();

                    $lngtag       = $lang->getTag();
                    $tpldir_local = $tpldir . DIRECTORY_SEPARATOR . $lngtag;
                    $dirs = [$tpldir_local, JPATH_SITE . $this->_rootfolder . $lngtag, $templateDir . DIRECTORY_SEPARATOR . $lngtag];
                    $phs  = ['%PLUGINDIR%', '%GPXDIR%', '%TEMPLATEDIR%'];

                    $tplfn_local = str_replace($phs, $dirs, $tplfn);

                    if (!file_exists($tplfn_local)) {
                        $dirs  = [$tpldir, JPATH_SITE . $this->_rootpath, $templateDir];
                        $tplfn = str_replace($phs, $dirs, $tplfn);
                    } else {
                        $tplfn = $tplfn_local;
                    }

                    $tplfn = str_replace('\\', '/', $tplfn);

                    if (!file_exists($tplfn)) {
                        $this->_warnings .= '<h2 style="color:red;"><em>GPXTrackMap Plugin error: Layout template file "'
                            . $tplfn . '" not found, using default layout.</em></h2>';
                        $tpltext = '<div class="gpxtrack">'
                            . '<div class="gpxtrackinfo"><p>%TRACKPOINTS% trackpoints; distance: %DISTANCE-KM% km; time: %DURATION%</p></div>'
                            . '<div class="gpxtrackmap">%TRACKMAP%</div>'
                            . '<div class="gpxtrackdiagram">%TRACKDIAGRAM%</div>'
                            . '<div class="gpxtracklink">%TRACKLINK%</div>'
                            . '</div>';
                    } else {
                        $tpltext = file_get_contents($tplfn);
                    }

                    $mapvar = 'map' . $gtmcount;

                    // addslashes() ensures translated strings with quotes don't break the JS syntax
                    $baselayer    = "{'title': '" . addslashes(Text::_('PLG_CONTENT_GTM_BASELAYER')) . "'}";
                    $mapcontrols  = '';

                    if ($this->_params['mapnav']       == 1) { $mapcontrols .= "new OpenLayers.Control.Navigation({documentDrag: true}),\n"; }
                    if ($this->_params['mappan']       == 1) { $mapcontrols .= "new OpenLayers.Control.PanZoomBar(),\n"; }
                    if ($this->_params['mapzoombtns']  == 1) { $mapcontrols .= "new OpenLayers.Control.Zoom(),\n"; }
                    if ($this->_params['mapswitch']    == 1) { $mapcontrols .= "new OpenLayers.Control.LayerSwitcher($baselayer),\n"; }
                    if ($this->_params['mapscale']     == 1) { $mapcontrols .= "new OpenLayers.Control.ScaleLine({geodesic:true,maxWidth:150}),\n"; }
                    if ($this->_params['mapoverview']  == 1) { $mapcontrols .= "new OpenLayers.Control.OverviewMap(),\n"; }
                    if ($this->_params['mapmousepos']  == 1) { $mapcontrols .= "new OpenLayers.Control.MousePosition(),\n"; }
                    if ($this->_params['mapgraticule'] == 1) {
                        $mapcontrols .= 'new OpenLayers.Control.Graticule({displayInLayerSwitcher: true, targetSize: 300,' . "\n"
                            . "layerName: '" . addslashes(Text::_('PLG_CONTENT_GTM_MAPGRATICULE_LABEL')) . "'," . "\n"
                            . 'intervals: [ 45, 30, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1, 0.05, 0.01 ] }),' . "\n";
                    }

                    $nomousewheelzoom = '';
                    if ($this->_params['mapwheelzoom'] != 1) {
                        $nomousewheelzoom = "controls = {$mapvar}.getControlsByClass('OpenLayers.Control.Navigation');\n"
                            . "for(var i = 0; i < controls.length; ++i) controls[i].disableZoomWheel();\n";
                    }

                    // --- Layer definitions ---

                    $ldmapnik = ' var layerMapnik = new OpenLayers.Layer.OSM.Mapnik("'
                        . addslashes(Text::_('PLG_CONTENT_GTM_MAPLAYER_OPENSTREETMAP_MAPNIK'))
                        . '"); ' . $mapvar . '.addLayer(layerMapnik); ' . "\n";

                    $ldTfApiKey = ($this->_params['tfapikey'] !== '') ? '?apikey=' . $this->_params['tfapikey'] : '';

                    $ldcycle = "\n var layerCycleMap = new OpenLayers.Layer.OSM(\""
                        . addslashes(Text::_('PLG_CONTENT_GTM_MAPLAYER_OPENSTREETMAP_CYCLEMAP')) . '", [' . "\n"
                        . '"https://tile.thunderforest.com/cycle/${z}/${x}/${y}.png' . $ldTfApiKey . '"],' . "\n"
                        . "{ attribution : 'maps &copy; <a href=\"https://thunderforest.com/\">Thunderforest</a>,"
                        . " data &copy; <a href=\"https://openstreetmap.org/copyright\">OpenStreetMap</a> contributors <a href=\"https://opendatacommons.org/licenses/odbl/\">ODbl</a>' "
                        . '});' . "\n" . $mapvar . '.addLayer(layerCycleMap); ' . "\n\n";

                    $ldmapnikde = ' var layerMapnikDE = new OpenLayers.Layer.OSM("'
                        . addslashes(Text::_('PLG_CONTENT_GTM_MAPLAYER_OPENSTREETMAP_MAPNIK_DE')) . '", [' . "\n"
                        . '"https://a.tile.openstreetmap.de/tiles/osmde/${z}/${x}/${y}.png",' . "\n"
                        . '"https://b.tile.openstreetmap.de/tiles/osmde/${z}/${x}/${y}.png",' . "\n"
                        . '"https://c.tile.openstreetmap.de/tiles/osmde/${z}/${x}/${y}.png"],' . "\n"
                        . '{sphericalMercator: true, tileOptions:{crossOriginKeyword: null}}); '
                        . $mapvar . '.addLayer(layerMapnikDE); ' . "\n";



                    $ldtransport = ' var layerTransport = new OpenLayers.Layer.OSM("'
                        . addslashes(Text::_('PLG_CONTENT_GTM_MAPLAYER_THUNDERFOREST_TRANSPORT')) . '", [' . "\n"
                        . '"https://tile.thunderforest.com/transport/${z}/${x}/${y}.png' . $ldTfApiKey . '"],' . "\n"
                        . '{sphericalMercator: true, numZoomLevels: 19,'
                        . "attribution : 'maps &copy; <a href=\"https://thunderforest.com/\">Thunderforest</a>,"
                        . " data &copy; <a href=\"https://openstreetmap.org/copyright\">OpenStreetMap</a> contributors <a href=\"https://opendatacommons.org/licenses/odbl/\">ODbl</a>' "
                        . '}); ' . $mapvar . '.addLayer(layerTransport); ' . "\n";

                    $ldlandscape = ' var layerLandscape = new OpenLayers.Layer.OSM("'
                        . addslashes(Text::_('PLG_CONTENT_GTM_MAPLAYER_THUNDERFOREST_LANDSCAPE')) . '", [' . "\n"
                        . '"https://tile.thunderforest.com/landscape/${z}/${x}/${y}.png' . $ldTfApiKey . '"],' . "\n"
                        . '{sphericalMercator: true, numZoomLevels: 19,'
                        . "attribution : 'maps &copy; <a href=\"https://thunderforest.com/\">Thunderforest</a>,"
                        . " data &copy; <a href=\"https://openstreetmap.org/copyright\">OpenStreetMap</a> contributors <a href=\"https://opendatacommons.org/licenses/odbl/\">ODbl</a>' "
                        . '}); ' . $mapvar . '.addLayer(layerLandscape); ' . "\n";

                    $ldoutdoors = ' var layerOutdoors = new OpenLayers.Layer.OSM("'
                        . addslashes(Text::_('PLG_CONTENT_GTM_MAPLAYER_THUNDERFOREST_OUTDOORS')) . '", [' . "\n"
                        . '"https://tile.thunderforest.com/outdoors/${z}/${x}/${y}.png' . $ldTfApiKey . '"],' . "\n"
                        . '{sphericalMercator: true, numZoomLevels: 19,'
                        . "attribution : 'maps &copy; <a href=\"https://thunderforest.com/\">Thunderforest</a>,"
                        . " data &copy; <a href=\"https://openstreetmap.org/copyright\">OpenStreetMap</a> contributors <a href=\"https://opendatacommons.org/licenses/odbl/\">ODbl</a>' "
                        . '}); ' . $mapvar . '.addLayer(layerOutdoors); ' . "\n";



                    $ldopentopo = ' var layerOpenTopo = new OpenLayers.Layer.OSM("'
                        . addslashes(Text::_('PLG_CONTENT_GTM_MAPLAYER_OPENTOPOMAP')) . '", [' . "\n"
                        . '"//a.tile.opentopomap.org/${z}/${x}/${y}.png",' . "\n"
                        . '"//b.tile.opentopomap.org/${z}/${x}/${y}.png",' . "\n"
                        . '"//c.tile.opentopomap.org/${z}/${x}/${y}.png"],' . "\n"
                        . '{sphericalMercator: true, numZoomLevels: 18,'
                        . "attribution : 'map data &copy; <a href=\"https://openstreetmap.org/copyright\">OpenStreetMap</a> contributors"
                        . " <a href=\"https://opendatacommons.org/licenses/odbl/\">ODbl</a>',"
                        . 'tileOptions:{crossOriginKeyword: null}}); '
                        . $mapvar . '.addLayer(layerOpenTopo); ' . "\n";





                    $maplayer  = $this->_params['maplayer'];
                    $maplayers = $this->_params['maplayers'];

                    $ldcustom1 = $ldcustom2 = $ldcustom3 = '';
                    $ldcustom1fn = $plginpath . 'CustomMapLayer1.js';
                    $ldcustom2fn = $plginpath . 'CustomMapLayer2.js';
                    $ldcustom3fn = $plginpath . 'CustomMapLayer3.js';

                    if (File::exists($ldcustom1fn)) { $ldcustom1 = str_replace('%MAPVAR%', $mapvar, file_get_contents($ldcustom1fn)); }
                    if (File::exists($ldcustom2fn)) { $ldcustom2 = str_replace('%MAPVAR%', $mapvar, file_get_contents($ldcustom2fn)); }
                    if (File::exists($ldcustom3fn)) { $ldcustom3 = str_replace('%MAPVAR%', $mapvar, file_get_contents($ldcustom3fn)); }

                    $maplayerscode = match ((int)$maplayer) {
                        1  => $ldcycle,
                        2  => $ldmapnikde,
                        4  => '',
                        5  => '',
                        6  => '',
                        7  => '',
                        8  => ($ldcustom1 !== '') ? $ldcustom1 : $this->warnCustom($ldcustom1fn),
                        9  => ($ldcustom2 !== '') ? $ldcustom2 : $this->warnCustom($ldcustom2fn),
                        10 => ($ldcustom3 !== '') ? $ldcustom3 : $this->warnCustom($ldcustom3fn),
                        11 => $ldtransport,
                        12 => $ldlandscape,
                        13 => $ldoutdoors,
                        15 => $ldopentopo,
                        default => $ldmapnik,
                    };

                    if (is_array($maplayers)) {
                        foreach ($maplayers as $ml) {
                            $ml = (int)$ml;
                            if ($ml === $maplayer) continue;
                            $maplayerscode .= match ($ml) {
                                0  => $ldmapnik,
                                1  => $ldcycle,
                                2  => $ldmapnikde,
                                        4  => '',
                                5  => '',
                                6  => '',
                                7  => '',
                                8  => ($ldcustom1 !== '') ? $ldcustom1 : $this->warnCustom($ldcustom1fn),
                                9  => ($ldcustom2 !== '') ? $ldcustom2 : $this->warnCustom($ldcustom2fn),
                                10 => ($ldcustom3 !== '') ? $ldcustom3 : $this->warnCustom($ldcustom3fn),
                                11 => $ldtransport,
                                12 => $ldlandscape,
                                13 => $ldoutdoors,
                                        15 => $ldopentopo,
                                        default => '',
                            };
                        }
                    }

                    $hillshading = '';
                    if ($this->_params['maphillshading'] >= 1) {
                        $hillshading = 'var hillshading = new OpenLayers.Layer.TMS("'
                            . Text::_('PLG_CONTENT_GTM_MAPHILLSHADING_LABEL') . '",' . "\n"
                            . '"https://a.tiles.wmflabs.org/hillshading/",' . "\n"
                            . "{type: 'png', getURL: osm_getTileURL, displayOutsideMaxExtent: true,\n"
                            . 'isBaseLayer: false, numZoomLevels: 16, transparent: true, "visibility": true}); '
                            . $mapvar . '.addLayer(hillshading); ' . "\n";

                        if ($this->_params['maphillshading'] >= 2) {
                            $hillshading .= 'var hillshading2 = new OpenLayers.Layer.TMS("'
                                . Text::_('PLG_CONTENT_GTM_MAPHILLSHADING_LABEL') . ' (2)",' . "\n"
                                . '"https://a.tiles.wmflabs.org/hillshading/",' . "\n"
                                . "{type: 'png', getURL: osm_getTileURL, displayOutsideMaxExtent: true,\n"
                                . 'isBaseLayer: false, numZoomLevels: 16, transparent: true, "visibility": true}); '
                                . $mapvar . '.addLayer(hillshading2); ' . "\n";
                        }
                    }

                    $markerpath    = $this->_markers_dir;
                    $startmarkerfn = $this->markerFilename($this->_params['startmarker'], $this->_params['markerset']);
                    $endmarkerfn   = $this->markerFilename($this->_params['endmarker'],   $this->_params['markerset']);

                    if ($startmarkerfn === '' && $endmarkerfn === '' && $this->_params['wpshow'] == 0 && $this->_params['wpsymbols'] == 0) {
                        $markerlayer = '';
                        $markercode  = '';
                    } else {
                        $markerlayer = ' layerMarkers' . $gtmcount . ' = new OpenLayers.Layer.Markers("Marker"); '
                            . $mapvar . '.addLayer(layerMarkers' . $gtmcount . '); ';
                        $markercode  = '';

                        if ($startmarkerfn !== '') {
                            $markercode .= ' var startpoint = this.features[0].geometry.components[0];' . "\n"
                                . ' var startsize = new OpenLayers.Size(21, 25);' . "\n"
                                . ' var startoffset = new OpenLayers.Pixel(-(startsize.w/2), -startsize.h);' . "\n"
                                . ' var starticon = new OpenLayers.Icon("' . $markerpath . $startmarkerfn . '",startsize,startoffset);' . "\n"
                                . ' layerMarkers' . $gtmcount . '.addMarker(new OpenLayers.Marker(new OpenLayers.LonLat(startpoint.x, startpoint.y),starticon));' . "\n";
                        }
                        if ($endmarkerfn !== '') {
                            $markercode .= ' var endpoint = this.features[0].geometry.components[this.features[0].geometry.components.length-1];' . "\n"
                                . ' var endsize = new OpenLayers.Size(21, 25);' . "\n"
                                . ' var endoffset = new OpenLayers.Pixel(-(endsize.w/2), -endsize.h);' . "\n"
                                . ' var endicon = new OpenLayers.Icon("' . $markerpath . $endmarkerfn . '",endsize,endoffset);' . "\n"
                                . ' layerMarkers' . $gtmcount . '.addMarker(new OpenLayers.Marker(new OpenLayers.LonLat(endpoint.x, endpoint.y),endicon));' . "\n";
                        }
                    }

                    $trackdashstyle = match ((int)$this->_params['trackstyle']) {
                        1 => '"dot"',
                        2 => '"dash"',
                        3 => '"dashdot"',
                        4 => '"longdash"',
                        5 => '"longdashdot"',
                        default => '"solid"',
                    };

                    $extractcode = ($this->_params['wpshow'] == 1)
                        ? 'extractWaypoints: true, extractRoutes: true, extractAttributes: true'
                        : 'extractWaypoints: false, extractRoutes: true, extractAttributes: true';

                    if (($this->_params['zoomlevel'] > 0) && ($this->_params['zoomlevel'] <= 20)) {
                        $zoomcode = ' this.map.zoomToExtent(this.getDataExtent(),true);'
                            . ' this.map.zoomTo(' . $this->_params['zoomlevel'] . ');' . "\n";
                    } else {
                        $zoomcode = ' this.map.zoomToExtent(this.getDataExtent(),true);';
                        $lvl = (int)$this->_params['zoomout'];
                        if ($lvl !== 0 && $lvl > -15 && $lvl < 15) {
                            if ($lvl > 0) {
                                for ($i = 1; $i <= $lvl; $i++) { $zoomcode .= ' this.map.zoomOut();' . "\n"; }
                            } else {
                                for ($i = -1; $i >= $lvl; $i--) { $zoomcode .= ' this.map.zoomIn();' . "\n"; }
                            }
                        }
                    }

                    $ticode = '';
                    $edurl  = '';
                    $spdurl = '';
                    $wptcode = '';

                    $this->_params['haseledata'] = 0;
                    $this->_params['hasspddata'] = 0;

                    if ($this->_params['ti'] == 1 || $this->_params['ed'] == 1 || $this->_params['spd'] == 1 || $this->_params['wpshow'] != 0) {
                        $tivars = $this->getGpxFileInfo($gpx_dir, $gpx_file);

                        if ($tivars[2] == 0) { $markercode = ''; }

                        if ($this->_params['wpshow'] == 1 && $tivars['wptcount'] > 0) {
                            $wptcode = $this->makeWptCode($tivars['wptcount'], $tivars['wpts'], $mapvar, $gtmcount);
                        }

                        $edurl  = $gpx_basepath . $tivars[0];
                        $spdurl = $gpx_basepath . $tivars[1];
                    } else {
                        $tivars = [];
                    }

                    // window event code: use vanilla JS (no MooTools, no jQuery required)
                    $windoweventcode = ($this->params->get('usejquery', 0) == 1)
                        ? "jQuery(window).on('load',function()"
                        : "window.addEventListener('load',function()";

                    $mapclass      = 'gpxtrackmap';
                    $gpxlayername  = 'GPX Track';

                    $gpx_path = str_replace(' ', '%20', $gpx_path);

                    $fsctrls  = '';
                    if ($this->_params['mapfullscreen'] == 1) {
                        $fsctrlsfn = $plginpath . ($this->_params['mappan'] == 1
                            ? 'fullscreencontrols_navbar.html'
                            : 'fullscreencontrols_buttons.html');
                        if (file_exists($fsctrlsfn)) {
                            $fsctrls    = file_get_contents($fsctrlsfn);
                            $scripttext .= "\n" . $fsctrls;
                        }
                    }

                    $srch = ['%MAPVAR%','%GPXPATH%','%NOMOUSEWHEELZOOM%','%MAPLAYERS%','%MARKERLAYER%','%GPXLAYERNAME%',
                             '%MAPCONTROLS%','%ZOOMCODE%',
                             '%TRACKCOLOR%','%TRACKWIDTH%','%TRACKOPACITY%','%TRACKDASHSTYLE%','%MARKERCODE%','%MAPCLASS%',
                             '%WPCOLOR%','%WPRADIUS%','%EXTRACTCODE%','%HILLSHADINGLAYER%',
                             '%WPTCODE%',
                             '%MAPWIDTH%','%MAPHEIGHT%','%MAPFULLSCREEN_ENTER%','%MAPFULLSCREEN_EXIT%'];

                    $repl = [$mapvar,$gpx_path,$nomousewheelzoom,$maplayerscode,$markerlayer,$gpxlayername,
                             $mapcontrols,$zoomcode,
                             $this->_params['trackcolor'],$this->_params['trackwidth'],$this->_params['trackopacity'],$trackdashstyle,
                             $markercode,$mapclass,
                             $this->_params['wpcolor'],$this->_params['wpradius'],$extractcode,$hillshading,
                             $wptcode,
                             $this->_params['mapwidth'],
                             $this->_params['mapheight'],
                             Text::_('PLG_CONTENT_GTM_MAPFULLSCREEN_ENTER'),
                             Text::_('PLG_CONTENT_GTM_MAPFULLSCREEN_EXIT')];

                    $mapcode = str_replace($srch, $repl, $scripttext);

                    $mapcode .= '<div class="' . $mapclass . '"';
                    if ($this->_params['mapwidth'] !== '0' || $this->_params['mapheight'] !== '0') {
                        $mapcode .= ' style="';
                        if ($this->_params['mapwidth'] !== '0')  { $mapcode .= 'width:'  . $this->_params['mapwidth']  . '; '; }
                        if ($this->_params['mapheight'] !== '0') { $mapcode .= 'height:' . $this->_params['mapheight'] . '; '; }
                        $mapcode .= '"';
                    }
                    $mapcode .= ' id="' . $mapvar . '"></div>';

                    // Track info placeholders
                    $tiplaceholders = [
                        '%ELEDIAGURL%','%SPDDIAGURL%','%TRACKPOINTS%','%DISTANCE-KM%','%DISTANCE-MI%','%DISTANCE-NM%',
                        '%ELE-UP-M%','%ELE-DOWN-M%','%ELE-MIN-M%','%ELE-MAX-M%','%ELE-DELTA-M%',
                        '%ELE-UP-FT%','%ELE-DOWN-FT%','%ELE-MIN-FT%','%ELE-MAX-FT%','%ELE-DELTA-FT%',
                        '%STARTTIME%','%ENDTIME%','%DURATION%','%DURATIONMOVING%','%DURATIONPAUSED%',
                        '%AVGSPEED-KMH%','%AVGSPEED-MPH%','%AVGSPEED-KN%',
                        '%AVGSPEEDUP-KMH%','%AVGSPEEDUP-MPH%','%AVGSPEEDUP-KN%',
                        '%AVGSPEEDDOWN-KMH%','%AVGSPEEDDOWN-MPH%','%AVGSPEEDDOWN-KN%',
                        '%AVGSPEEDMOVING-KMH%','%AVGSPEEDMOVING-MPH%','%AVGSPEEDMOVING-KN%',
                        '%MAXSPEED-KMH%','%MAXSPEED-MPH%','%MAXSPEED-KN%',
                        '%MAXSPEEDUP-KMH%','%MAXSPEEDUP-MPH%','%MAXSPEEDUP-KN%',
                        '%MAXSPEEDDOWN-KMH%','%MAXSPEEDDOWN-MPH%','%MAXSPEEDDOWN-KN%',
                        '%TRACKPOINTDISTANCE-M%','%TRACKPOINTDISTANCE-FT%',
                    ];

                    // Elevation diagram
                    $edcode = '';
                    if ($this->_params['ed'] == 1 && $this->_params['haseledata'] == 1) {
                        $edwidth  = $this->_params['edwidth'];
                        $edheight = $this->_params['edheight'];
                        $edcode   = '<img class="gpxtrackdiagram" src="' . $edurl . '"';
                        if ($edwidth !== '0' || $edheight !== '0') {
                            $edcode .= ' style="';
                            if ($edwidth  !== '0') { $edcode .= 'width:'  . $edwidth  . ' !important; '; }
                            if ($edheight !== '0') { $edcode .= 'height:' . $edheight . ' !important;'; }
                            $edcode .= '"';
                        }
                        $edcode .= "/>\n";
                    }

                    // Speed diagram
                    $spdcode = '';
                    if ($this->_params['spd'] == 1 && $this->_params['hasspddata'] == 1) {
                        $spdwidth  = $this->_params['spdwidth'];
                        $spdheight = $this->_params['spdheight'];
                        $spdcode   = '<img class="gpxspeeddiagram" src="' . $spdurl . '"';
                        if ($spdwidth !== '0' || $spdheight !== '0') {
                            $spdcode .= ' style="';
                            if ($spdwidth  !== '0') { $spdcode .= 'width:'  . $spdwidth  . ' !important;'; }
                            if ($spdheight !== '0') { $spdcode .= 'height:' . $spdheight . ' !important;'; }
                            $spdcode .= '"';
                        }
                        $spdcode .= "/>\n";
                    }

                    // Download link
                    $dlcode = '';
                    if ($this->_params['dl'] == 1) {
                        if ($this->_params['dlzip'] == 1) {
                            $zip_fn = $this->ziptrackfile($this->_rootfolder, $gpx_file);
                            if ($zip_fn) { $gpx_path = $this->_live_site . $zip_fn; }
                        }

                        $dltext   = str_replace('%s', basename($gpx_path), $this->_params['dltext']);
                        $cssstyle = $this->_params['dlstyle'] !== ''
                            ? ' style="' . $this->_params['dlstyle'] . '"'
                            : '';

                        if ($this->_params['dltype'] == 0) {
                            $dlcode = '<div class="' . $this->_params['dlclass'] . '"' . $cssstyle . '>'
                                . '<a href="' . $gpx_path . '" type="application/gpx+xml" download="' . $gpx_file . '" target="_blank">' . $dltext . '</a></div>';
                        } else {
                            $dlcode = '<div class="' . $this->_params['dlclass'] . '"' . $cssstyle . '>'
                                . '<button onclick="window.location.href=\'' . $gpx_path . '\'">' . $dltext . '</button></div>';
                        }
                    }

                    $gtmcode = $tpltext;
                    $gtmcode = str_replace($tiplaceholders, $tivars, $gtmcode);
                    $gtmcode = str_replace(['%TRACKMAP%','%TRACKDIAGRAM%','%SPEEDDIAGRAM%','%TRACKDOWNLOAD%'], [$mapcode,$edcode,$spdcode,$dlcode], $gtmcode);
                    $gtmcode = '<!-- GPXTrackmap ' . $this->_gtmversion . ' #' . $gtmcount . ' START -->' . "\n"
                             . $gtmcode . "\n"
                             . '<!-- GPXTrackmap #' . $gtmcount . ' END -->' . "\n";
                }

                $regex = '@(<p>)?\{gpxtrackmap\}' . preg_quote($gpx_code, '@') . '\{/gpxtrackmap\}(</p>)?@s';
                $article->text = preg_replace($regex, $gtmcode, $article->text);

                unset($gpx_array);
                $this->_warnings = '';
            }
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Helper: warn about missing custom layer file
    // -------------------------------------------------------------------------

    private function warnCustom(string $fn): string
    {
        $this->_warnings .= '<h2 style="color:red;"><em>' . $fn . ' does not exist or is empty.</em></h2>' . "\n";
        return '';
    }

    // -------------------------------------------------------------------------
    // collectParams
    // -------------------------------------------------------------------------

    private function collectParams(): void
    {
        $names = [
            'mapwidth','mapheight','maplayer','mapnav','mappan','mapzoombtns','mapfullscreen','mapswitch','maplayers','mapgraticule','maphillshading',
            'mapscale','mapwheelzoom','mapoverview','mapmousepos','zoomout',
            'trackcolor','trackwidth','trackopacity','trackstyle',
            'wpshow','wpradius','wpcolor','wppopups','wppopupwidth','wppopupheight','wppopupele','wppopuptimefmt','wppopupdesc','wppopupdescbb','wppopuplinkfmt','wpsymbols',
            'startmarker','endmarker','markerset',
            'ti','tidecimalsep','tidatefmt','titimefmt','titimeshift','timovespeed',
            'dl','dltext','dltype','dlzip','dlclass','dlstyle',
            'ed','edwidth','edheight','edlinecolor','edlinewidth','edbgcolor','edfilterorder','edfillmode','edupcolor','eddowncolor','edunits',
            'edxgrid','edxgridunits','edxgridlimit','edxgridcolor','edxgridwidth','edygridlines','edygridwidth','edygridcolor',
            'spd','spdwidth','spdheight','spdlinecolor','spdlinewidth','spdbgcolor','spdfilterorder','spdfillmode','spdupcolor','spddowncolor','spdunits',
            'spdxgrid','spdxgridunits','spdxgridlimit','spdxgridcolor','spdxgridwidth','spdygridlines','spdygridwidth','spdygridcolor',
            'cache','tpl','usejquery','showwarnings','zoomlevel','tfapikey',
        ];

        $this->expandPresets($this->_params, $this->params->get('presets'), $names);

        foreach ($names as $name) {
            if (!array_key_exists($name, $this->_params)) {
                $this->_params[$name] = $this->params->get($name);
            }
        }
    }

    // -------------------------------------------------------------------------
    // expandPresets
    // -------------------------------------------------------------------------

    private function expandPresets(array &$syntaxparams, ?string $backendpresets, array $paramnames): void
    {
        $presets = explode("\n", (string)$backendpresets);

        foreach ($syntaxparams as $key => $value) {
            if ($key === 'preset' || $key === 'ps') {
                $psetcalls = explode('-', $value);

                foreach ($psetcalls as $psetcall) {
                    $psetcall = strtolower(trim($psetcall));
                    $tfound   = 0;

                    foreach ($presets as $presetline) {
                        if (trim($presetline) === '') { continue; }

                        $p = strpos($presetline, ':');
                        if ($p === false) {
                            $this->_warnings .= 'Syntax error in preset: ' . $presetline . '<br />' . "\n";
                            continue;
                        }

                        $psetname   = strtolower(trim(substr($presetline, 0, $p)));
                        $psetparams = substr($presetline, $p + 1);

                        if ($psetname === $psetcall) {
                            $tfound++;
                            foreach (explode(',', $psetparams) as $tparam_value) {
                                $key_value = explode('=', $tparam_value);
                                $spname    = strtolower(trim($key_value[0]));
                                if (!array_key_exists($spname, $syntaxparams)) {
                                    $syntaxparams[$spname] = trim($key_value[1] ?? '');
                                }
                            }
                        }
                    }

                    if ($tfound === 0) {
                        $this->_warnings .= 'unknown preset called: "' . $psetcall . '"<br />' . "\n";
                    }
                }
            } else {
                if (!in_array($key, $paramnames, true)) {
                    $this->_warnings .= 'unknown parameter: "' . $key . '"' . "\n";
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // getGpxFileInfo
    // -------------------------------------------------------------------------

    private function getGpxFileInfo(string $filepath, string $filename): array
    {
        $lat = $lon = $ele = $dist = $distdelta = $tim = $speed = $wpts = [];
        $n = $found_ele = $found_time = 0;

        $avgspeeddown_kmh = $avgspeeddown_mph = $avgspeeddown_kn = '';
        $avgspeedup_kmh   = $avgspeedup_mph   = $avgspeedup_kn   = '';

        date_default_timezone_set('UTC');

        if (!File::exists($filepath . $filename)) {
            return [];
        }

        $gpx = @simplexml_load_file($filepath . $filename);
        if ($gpx === false) { return []; }

        $starttime = $endtime = $ts = 0;

        foreach ($gpx->trk as $trk) {
            foreach ($trk->trkseg as $trkseg) {
                foreach ($trkseg->trkpt as $tpt) {
                    $attrs   = $tpt->attributes();
                    $lat[$n] = (float)$attrs['lat'];
                    $lon[$n] = (float)$attrs['lon'];

                    $ele[$n]  = ((float)$tpt->ele != 0) ? (float)$tpt->ele : 0;
                    if ($ele[$n] != 0) { $found_ele = 1; }

                    $ts = (string)$tpt->time;
                    if ($ts !== '') {
                        $found_time = 1;
                        $tim[$n]    = $this->getGPXTime($ts);
                    } else {
                        $tim[$n] = 0;
                    }

                    if ($n === 0) {
                        $minele_m  = $ele[$n];
                        $maxele_m  = $ele[$n];
                        $starttime = $this->getGPXTime($ts);
                    } else {
                        if ($ele[$n] < $minele_m) { $minele_m = $ele[$n]; }
                        if ($ele[$n] > $maxele_m) { $maxele_m = $ele[$n]; }
                    }
                    $n++;
                }
            }
        }

        if ($n > 0) {
            $endtime = $this->getGPXTime($ts);
        }

        $wptcount = 0;

        if ($this->_params['wppopups'] !== 0) {
            foreach ($gpx->wpt as $wpt) {
                $attrs   = $wpt->attributes();
                $wptlat  = (float)$attrs['lat'];
                $wptlon  = (float)$attrs['lon'];
                $wpttime = ((string)$wpt->time !== '') ? $this->getGPXTime($wpt->time) : '';
                $wptele  = ((string)$wpt->ele  !== '') ? (float)$wpt->ele : '';
                $wptsym  = (string)$wpt->sym;
                $wptname = (string)$wpt->name;
                $wptdesc = strtr((string)$wpt->desc, '"', '\"');
                if ($wptdesc === '') { $wptdesc = strtr((string)$wpt->description, '"', '\"'); }

                if ($this->_params['wppopupdescbb'] == 1) {
                    $bbcode = [
                        "/\[br\]/is"                            => '<br />',
                        "/\[b\](.*?)\[\/b\]/is"                => '<strong>$1</strong>',
                        "/\[u\](.*?)\[\/u\]/is"                => '<u>$1</u>',
                        "/\[i\](.*?)\[\/i\]/is"                => '<i>$1</i>',
                        "/\[code\](.*?)\[\/code\]/is"          => '<pre>$1</pre>',
                        "/\[quote\](.*?)\[\/quote\]/is"        => '<blockquote>$1</blockquote>',
                        "/\[url\=(.*?)\](.*?)\[\/url\]/is"     => "<a href='$1' target='_self'>$2</a>",
                        "/\[img\](.*?)\[\/img\]/is"            => "<img src='$1' alt='' />",
                    ];
                    $wptdesc = preg_replace(array_keys($bbcode), array_values($bbcode), $wptdesc);
                }

                $wptlinks = '';
                $linkno   = 1;
                foreach ($wpt->link as $wptlink) {
                    $attrs    = $wptlink->attributes();
                    $wpthref  = (string)$attrs['href'];
                    $wptlinks .= $wpthref . "\n";

                    if ($this->_params['wppopupdescbb'] == 1) {
                        $bbcode   = ["/\[link{$linkno}\](.*?)\[\/link{$linkno}\]/is" => "<a href='{$wpthref}' target='_blank'>$1</a>"];
                        $wptdesc  = preg_replace(array_keys($bbcode), array_values($bbcode), $wptdesc);
                    }
                    $linkno++;
                }

                $wpts[] = ['lat'=>$wptlat,'lon'=>$wptlon,'ele'=>$wptele,'time'=>$wpttime,'name'=>$wptname,'desc'=>$wptdesc,'sym'=>$wptsym,'links'=>$wptlinks];
                $wptcount++;
            }
        }

        $r0 = 6371.0;
        $distance_km = 0.0;
        $duration_moving = $duration_paused = 0;

        if ($n > 0) {
            for ($i = 0; $i < ($n - 1); $i++) {
                $distdelta[$i] = 0.0;
                $speed[$i]     = 0.0;

                if ($lat[$i] !== $lat[$i+1] && $lon[$i] !== $lon[$i+1]) {
                    $a     = deg2rad(90.0 - $lat[$i]);
                    $b     = deg2rad(90.0 - $lat[$i+1]);
                    $gamma = deg2rad(abs($lon[$i+1] - $lon[$i]));
                    $c     = $r0 * acos(min(1.0, cos($a)*cos($b) + sin($a)*sin($b)*cos($gamma)));

                    $distdelta[$i]  = $c;
                    $distance_km   += $c;

                    if ($found_time === 1) {
                        $dt = $tim[$i+1] - $tim[$i];
                        if (abs($dt) > 1 && $c != 0.0) {
                            $speed[$i] = $c / $dt * 3600;
                        } elseif ($i > 0 && abs($tim[$i-1] - $tim[$i+1]) > 1 && $c != 0.0) {
                            $speed[$i] = ($distdelta[$i-1] + $c) / ($tim[$i+1] - $tim[$i-1]) * 3600;
                        }

                        if ($speed[$i] > $this->_params['timovespeed']) {
                            $duration_moving += $tim[$i+1] - $tim[$i];
                        } else {
                            $duration_paused += $tim[$i+1] - $tim[$i];
                        }
                    }
                }
                $dist[$i] = $distance_km;
            }
            $dist[$n-1]     = $distance_km;
            $distdelta[$n-1] = 0.0;
            $speed[$n-1]    = 0.0;
            $dist[$n]       = $distance_km;
            $distdelta[$n]  = 0.0;
            $speed[$n]      = 0.0;

            if ($this->_params['spdfilterorder'] > 1) {
                $speed = $this->filterSignal($speed, $n, $this->_params['spdfilterorder']);
            }
        }

        $m_ft    = 1 / 0.3048;
        $kmh_mph = 1 / 1.609;
        $kmh_kn  = 1 / 1.852;

        $tpdistance_m  = ($n > 0) ? round($distance_km / $n * 1000, 2) : 0;
        $tpdistance_ft = round($tpdistance_m * $m_ft, 2);
        $distance_mi   = round($distance_km * $kmh_mph, 1);
        $distance_nm   = round($distance_km * $kmh_kn,  1);
        $distance_km   = round($distance_km, 1);

        $maxspeed_kmh = 0.0;
        for ($i = 0; $i < ($n - 1); $i++) {
            if (($speed[$i] ?? 0) > $maxspeed_kmh) { $maxspeed_kmh = $speed[$i]; }
        }
        $maxspeed_mph = round($maxspeed_kmh * $kmh_mph, 1);
        $maxspeed_kn  = round($maxspeed_kmh * $kmh_kn,  1);
        $maxspeed_kmh = round($maxspeed_kmh, 1);

        $maxspeedup_kmh = $maxspeeddown_kmh = 0.0;

        if ($found_ele === 1) {
            $up_m = $down_m = $timeup = $timedown = 0;
            $distup = $distdown = 0.0;

            if ($this->_params['edfilterorder'] > 1) {
                $ele = $this->filterSignal($ele, $n, $this->_params['edfilterorder']);
            }

            for ($i = 0; $i < ($n - 1); $i++) {
                if ($ele[$i] < $ele[$i+1]) {
                    $up_m += $ele[$i+1] - $ele[$i];
                    if ($found_time) {
                        if (($speed[$i] ?? 0) > $maxspeedup_kmh) { $maxspeedup_kmh = $speed[$i]; }
                        $timeup += $tim[$i+1] - $tim[$i];
                        $distup += $distdelta[$i];
                    }
                } elseif ($ele[$i] > $ele[$i+1]) {
                    $down_m += $ele[$i] - $ele[$i+1];
                    if ($found_time) {
                        if (($speed[$i] ?? 0) > $maxspeeddown_kmh) { $maxspeeddown_kmh = $speed[$i]; }
                        $timedown += $tim[$i+1] - $tim[$i];
                        $distdown += $distdelta[$i];
                    }
                }
            }

            if ($timedown > 0 && $distdown > 0.0) {
                $avgspeeddown_kmh = round($distdown / $timedown * 3600, 1);
                $avgspeeddown_mph = round($avgspeeddown_kmh * $kmh_mph, 1);
                $avgspeeddown_kn  = round($avgspeeddown_kmh * $kmh_kn,  1);
            }
            if ($timeup > 0 && $distup > 0.0) {
                $avgspeedup_kmh = round($distup / $timeup * 3600, 1);
                $avgspeedup_mph = round($avgspeedup_kmh * $kmh_mph, 1);
                $avgspeedup_kn  = round($avgspeedup_kmh * $kmh_kn,  1);
            }

            $maxspeedup_mph    = round($maxspeedup_kmh   * $kmh_mph, 1);
            $maxspeedup_kn     = round($maxspeedup_kmh   * $kmh_kn,  1);
            $maxspeedup_kmh    = round($maxspeedup_kmh,   1);
            $maxspeeddown_mph  = round($maxspeeddown_kmh * $kmh_mph, 1);
            $maxspeeddown_kn   = round($maxspeeddown_kmh * $kmh_kn,  1);
            $maxspeeddown_kmh  = round($maxspeeddown_kmh, 1);

            $up_m   = round($up_m);
            $down_m = round($down_m);
            $minele_m    = round($minele_m ?? 0);
            $maxele_m    = round($maxele_m ?? 0);
            $deltaele_m  = round($maxele_m - $minele_m);
            $up_ft       = round($up_m   * $m_ft);
            $down_ft     = round($down_m * $m_ft);
            $minele_ft   = round($minele_m * $m_ft);
            $maxele_ft   = round($maxele_m * $m_ft);
            $deltaele_ft = round(($maxele_m - $minele_m) * $m_ft);
        }

        // Set n/a for missing elevation
        if ($found_ele !== 1 || $this->_params['ti'] != 1) {
            $down_m = $up_m = $minele_m = $maxele_m = $deltaele_m = 'n/a';
            $down_ft = $up_ft = $minele_ft = $maxele_ft = $deltaele_ft = 'n/a';
            $avgspeedup_kmh  = $avgspeeddown_kmh  = 'n/a';
            $avgspeedup_mph  = $avgspeeddown_mph  = 'n/a';
            $avgspeedup_kn   = $avgspeeddown_kn   = 'n/a';
            $maxspeedup_kmh  = $maxspeeddown_kmh  = 'n/a';
            $maxspeedup_mph  = $maxspeeddown_mph  = 'n/a';
            $maxspeedup_kn   = $maxspeeddown_kn   = 'n/a';
        }

        // Time-based calculations — PHP 8.1+: strftime() removed, use gmdate() + IntlDateFormatter
        if ($found_time === 1) {
            $starttimestr      = $this->formatDateTime($this->_params['tidatefmt'], $starttime);
            $endtimestr        = $this->formatDateTime($this->_params['tidatefmt'], $endtime);
            $durationstr       = $this->formatDuration($this->_params['titimefmt'], $endtime - $starttime);
            $durationmovingstr = $this->formatDuration($this->_params['titimefmt'], $duration_moving);
            $durationpausedstr = $this->formatDuration($this->_params['titimefmt'], $duration_paused);

            $elapsed = $endtime - $starttime;
            if ($elapsed !== 0) {
                $avgspeed_kmh = round($distance_km / $elapsed * 3600, 1);
                $avgspeed_mph = round($distance_mi / $elapsed * 3600, 1);
                $avgspeed_kn  = round($distance_nm / $elapsed * 3600, 1);
            } else {
                $avgspeed_kmh = $avgspeed_mph = $avgspeed_kn = 'n/a';
            }

            if ($duration_moving > 0) {
                $avgspeedmoving_kmh = round($distance_km / $duration_moving * 3600, 1);
                $avgspeedmoving_mph = round($distance_mi / $duration_moving * 3600, 1);
                $avgspeedmoving_kn  = round($distance_nm / $duration_moving * 3600, 1);
            } else {
                $avgspeedmoving_kmh = $avgspeedmoving_mph = $avgspeedmoving_kn = 'n/a';
            }
        }

        if ($found_time !== 1 || $this->_params['ti'] != 1) {
            $starttimestr = $endtimestr = $durationstr = $durationmovingstr = $durationpausedstr = 'n/a';
            $avgspeed_kmh = $avgspeed_mph = $avgspeed_kn = 'n/a';
            $avgspeedmoving_kmh = $avgspeedmoving_mph = $avgspeedmoving_kn = 'n/a';
            $maxspeed_kmh = $maxspeed_mph = $maxspeed_kn = 'n/a';
        }

        // Diagrams
        if ($found_ele === 1 && $this->_params['ed'] == 1) {
            $this->_params['haseledata'] = 1;
            $elediagfn = $this->renderDiagram($dist,$ele,$n,$minele_m,$maxele_m,$distance_km,
                $this->_params['edunits'],$filepath,$filename,'',
                $this->_params['edbgcolor'],$this->_params['edlinecolor'],$this->_params['edfillmode'],
                $this->_params['edupcolor'],$this->_params['eddowncolor'],$this->_params['edlinewidth'],
                $this->_params['edxgrid'],$this->_params['edxgridunits'],$this->_params['edxgridlimit'],
                $this->_params['edxgridwidth'],$this->_params['edxgridcolor'],
                $this->_params['edygridlines'],$this->_params['edygridwidth'],$this->_params['edygridcolor']);
            if ($elediagfn === '') {
                $this->_warnings .= 'Unable to write svg file into folder ' . $filepath . '. Please check write permissions!' . "\n";
            }
        } else {
            $elediagfn = '';
        }

        if ($found_time === 1 && $this->_params['spd'] == 1) {
            $this->_params['hasspddata'] = 1;
            $spddiagfn = $this->renderDiagram($dist,$speed,$n,0.0,$maxspeed_kmh,$distance_km,
                $this->_params['spdunits'],$filepath,$filename,'_speed',
                $this->_params['spdbgcolor'],$this->_params['spdlinecolor'],$this->_params['spdfillmode'],
                $this->_params['spdupcolor'],$this->_params['spddowncolor'],$this->_params['spdlinewidth'],
                $this->_params['spdxgrid'],$this->_params['spdxgridunits'],$this->_params['spdxgridlimit'],
                $this->_params['spdxgridwidth'],$this->_params['spdxgridcolor'],
                $this->_params['spdygridlines'],$this->_params['spdygridwidth'],$this->_params['spdygridcolor']);
        } else {
            $spddiagfn = '';
        }

        // Decimal separator substitution
        $sep = $this->_params['tidecimalsep'];
        if ($sep !== '.') {
            $floatVars = [
                &$distance_km,&$distance_mi,&$distance_nm,
                &$up_m,&$down_m,&$minele_m,&$maxele_m,&$deltaele_m,
                &$up_ft,&$down_ft,&$minele_ft,&$maxele_ft,&$deltaele_ft,
                &$avgspeed_kmh,&$avgspeed_mph,&$avgspeed_kn,
                &$avgspeedup_kmh,&$avgspeedup_mph,&$avgspeedup_kn,
                &$avgspeeddown_kmh,&$avgspeeddown_mph,&$avgspeeddown_kn,
                &$avgspeedmoving_kmh,&$avgspeedmoving_mph,&$avgspeedmoving_kn,
                &$maxspeed_kmh,&$maxspeed_mph,&$maxspeed_kn,
                &$maxspeedup_kmh,&$maxspeedup_mph,&$maxspeedup_kn,
                &$maxspeeddown_kmh,&$maxspeeddown_mph,&$maxspeeddown_kn,
                &$tpdistance_m,&$tpdistance_ft,
            ];
            foreach ($floatVars as &$v) {
                if (is_string($v)) { $v = str_replace('.', $sep, $v); }
            }
            unset($v);
        }

        if ($this->_params['ti'] != 1) {
            $tpdistance_m = $tpdistance_ft = $distance_km = $distance_nm = $distance_mi = $n = 'n/a';
        }

        return [
            $elediagfn, $spddiagfn, $n,
            $distance_km, $distance_mi, $distance_nm,
            $up_m, $down_m, $minele_m, $maxele_m, $deltaele_m,
            $up_ft, $down_ft, $minele_ft, $maxele_ft, $deltaele_ft,
            $starttimestr, $endtimestr,
            $durationstr, $durationmovingstr, $durationpausedstr,
            $avgspeed_kmh, $avgspeed_mph, $avgspeed_kn,
            $avgspeedup_kmh, $avgspeedup_mph, $avgspeedup_kn,
            $avgspeeddown_kmh, $avgspeeddown_mph, $avgspeeddown_kn,
            $avgspeedmoving_kmh, $avgspeedmoving_mph, $avgspeedmoving_kn,
            $maxspeed_kmh, $maxspeed_mph, $maxspeed_kn,
            $maxspeedup_kmh, $maxspeedup_mph, $maxspeedup_kn,
            $maxspeeddown_kmh, $maxspeeddown_mph, $maxspeeddown_kn,
            $tpdistance_m, $tpdistance_ft,
            'wptcount' => $wptcount,
            'wpts'     => $wpts,
        ];
    }

    // -------------------------------------------------------------------------
    // PHP 8.1+ replacements for strftime()
    // -------------------------------------------------------------------------

    /**
     * Format a Unix timestamp using a strftime-compatible format string.
     * Converts common %X tokens to gmdate() equivalents.
     */
    private function formatDateTime(string $fmt, int $timestamp): string
    {
        $map = [
            '%Y' => 'Y', '%m' => 'm', '%d' => 'd',
            '%H' => 'H', '%M' => 'i', '%S' => 's',
            '%e' => 'j', '%A' => 'l', '%a' => 'D',
            '%B' => 'F', '%b' => 'M', '%p' => 'A',
        ];
        $datefmt = str_replace(array_keys($map), array_values($map), $fmt);
        return gmdate($datefmt, $timestamp);
    }

    /**
     * Format a duration in seconds using a strftime-compatible format string.
     * For durations > 24h the hour component simply overflows (gmdate wraps at 24h),
     * so we manually calculate H for durations.
     */
    private function formatDuration(string $fmt, int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        $map = [
            '%H' => str_pad((string)$h, 2, '0', STR_PAD_LEFT),
            '%M' => str_pad((string)$m, 2, '0', STR_PAD_LEFT),
            '%S' => str_pad((string)$s, 2, '0', STR_PAD_LEFT),
        ];
        return str_replace(array_keys($map), array_values($map), $fmt);
    }

    // -------------------------------------------------------------------------
    // expandWptSymbolFn / getWptSymbolFn
    // -------------------------------------------------------------------------

    private function expandWptSymbolFn(string $symbolfn): string
    {
        $app         = Factory::getApplication();
        $templateDir = $this->_live_site . '/templates/' . $app->getTemplate();

        $dirs = [
            rtrim($this->_plugin_dir, '/'),
            rtrim($this->_live_site . $this->_rootfolder, '/'),
            $templateDir,
        ];
        $phs  = ['%PLUGINDIR%', '%GPXDIR%', '%TEMPLATEDIR%'];

        return str_replace('\\', '/', str_replace($phs, $dirs, $symbolfn));
    }

    private function getWptSymbolFn(string $symbol, int &$symbolwidth, int &$symbolheight, int &$offsetleft, int &$offsettop): string
    {
        $symbolwidth = $symbolheight = 16;
        $mappings     = explode("\n", (string)$this->params->get('wpsymbolmappings'));
        $defaultfn    = '%PLUGINDIR%/markers/waypointmarker16.png';

        foreach ($mappings as $mapping) {
            $parts = explode('=', $mapping);
            foreach (explode('|', $parts[0]) as $sym) {
                if ($sym === $symbol || $sym === '*') {
                    $syminfo = explode(',', $parts[1] ?? '');
                    $fn      = $this->expandWptSymbolFn($syminfo[0]);
                    if (!empty($syminfo[1])) { $symbolwidth  = (int)$syminfo[1]; }
                    if (!empty($syminfo[2])) { $symbolheight = (int)$syminfo[2]; }
                    if (!empty($syminfo[3])) { $offsetleft   = (int)$syminfo[3]; }
                    if (!empty($syminfo[4])) { $offsettop    = (int)$syminfo[4]; }
                    if ($sym === $symbol) { return $fn; }
                    $defaultfn = $syminfo[0];
                }
            }
        }

        return $this->expandWptSymbolFn($defaultfn);
    }

    // -------------------------------------------------------------------------
    // makeWptCode
    // -------------------------------------------------------------------------

    private function makeWptCode(int $wptcount, array $wpts, string $mapvar, int $mapno): string
    {
        $s = "\nvar toMercator = OpenLayers.Projection.transforms['EPSG:4326']['EPSG:3857'];\n"
           . "var features = [];\n";

        $popupW = (is_numeric($this->_params['wppopupwidth'])  && $this->_params['wppopupwidth']  > 0) ? (int)$this->_params['wppopupwidth']  : 300;
        $popupH = (is_numeric($this->_params['wppopupheight']) && $this->_params['wppopupheight'] > 0) ? (int)$this->_params['wppopupheight'] : 300;
        $popupsize = "$popupW,$popupH";

        if ($this->_params['wpsymbols'] != 0) {
            if ($this->_params['wppopups'] != 0) {
                $s .= "var wptmarkerClick{$mapno} = function (evt) {\n"
                    . "    if (this.popup == null) {\n"
                    . "        this.popup = this.createPopup(this.closeBox);\n"
                    . "        this.popup.maxSize = new OpenLayers.Size({$popupsize});\n"
                    . "        map{$mapno}.addPopup(this.popup);\n"
                    . "        this.popup.show();\n"
                    . "    } else { this.popup.toggle(); }\n"
                    . "    currentPopup = this.popup;\n"
                    . "    OpenLayers.Event.stop(evt);\n"
                    . "};\n";
            }

            for ($i = 0; $i < $wptcount; $i++) {
                $symbolwidth = $symbolheight = 24;
                $offsetleft  = $offsettop   = 0;
                $wptsymbolfn = $this->getWptSymbolFn($wpts[$i]['sym'], $symbolwidth, $symbolheight, $offsetleft, $offsettop);

                $s .= "\n var wptsize{$i} = new OpenLayers.Size({$symbolwidth},{$symbolheight});\n";

                if ($offsetleft !== 0 && $offsettop !== 0) {
                    $s .= " var wptoffset{$i} = new OpenLayers.Pixel(-({$offsetleft}), -({$offsettop}));\n";
                } else {
                    $s .= " var wptoffset{$i} = new OpenLayers.Pixel(-(wptsize{$i}.w/2), -wptsize{$i}.h/2);\n";
                }

                $s .= " var wpticon{$i} = new OpenLayers.Icon(\"{$wptsymbolfn}\",\n"
                    . "       wptsize{$i},wptoffset{$i});\n"
                    . " wptgeo{$i} = toMercator(new OpenLayers.Geometry.Point({$wpts[$i]['lon']},{$wpts[$i]['lat']}));\n"
                    . " wptll{$i} = new OpenLayers.LonLat.fromString(wptgeo{$i}.toShortString());\n"
                    . " var wptfeature{$i} = new OpenLayers.Feature(layerMarkers{$mapno}, wptll{$i});\n"
                    . " wptfeature{$i}.data.icon = wpticon{$i};\n"
                    . " wptfeature{$i}.closeBox = true;\n"
                    . " wptfeature{$i}.popupClass = OpenLayers.Popup.FramedCloud;\n";

                $wptele = '';
                if ($this->_params['wppopupele'] !== 0 && $wpts[$i]['ele'] !== '') {
                    $e = $wpts[$i]['ele'];
                    if ($this->_params['wppopupele'] === 'ft') { $e *= 1 / 0.3048; $wptele = ' (' . round($e) . ' ft)'; }
                    if ($this->_params['wppopupele'] === 'm')  { $wptele = ' (' . round($e) . ' m)'; }
                }

                $wpttime = '';
                if ($this->_params['wppopuptimefmt'] !== '0' && (int)$wpts[$i]['time'] !== 0) {
                    $wpttime = '<br />' . $this->formatDateTime($this->_params['wppopuptimefmt'], (int)$wpts[$i]['time']);
                }

                $wptdesc = '';
                if ($this->_params['wppopupdesc'] !== '0' && (string)$wpts[$i]['desc'] !== '') {
                    $d = preg_replace('/\r\n|\r|\n/', '', nl2br((string)$wpts[$i]['desc']));
                    $wptdesc = '<br />' . $d;
                }

                $wptlinks = '';
                if ($this->_params['wppopuplinkfmt'] !== '0' && (string)$wpts[$i]['links'] !== '') {
                    $wptlinks = '<br />';
                    $ln = 1;
                    foreach (explode("\n", (string)$wpts[$i]['links']) as $lnk) {
                        if (trim($lnk) !== '') {
                            $d = str_replace('%N%', $ln, $this->_params['wppopuplinkfmt']);
                            if ($ln > 1) { $wptlinks .= '&nbsp;'; }
                            $wptlinks .= "<a href='{$lnk}' target='_blank'>{$d}</a>";
                            $ln++;
                        }
                    }
                }

                $wpthtml = '"<div style=\'font-size:.8em\'>'
                    . "<span class='gpxwptname'>{$wpts[$i]['name']}</span>"
                    . "<span class='gpxwptele'>{$wptele}</span>"
                    . "<span class='gpxwpttime'>{$wpttime}</span>"
                    . "<span class='gpxwpdesc'>{$wptdesc}</span>"
                    . "<span class='gpxwplinks'>{$wptlinks}</span></div>\"";

                $s .= " wptfeature{$i}.data.popupContentHTML = {$wpthtml};\n"
                    . " wptfeature{$i}.data.overflow = \"auto\";\n"
                    . " var wptmarker{$i} = wptfeature{$i}.createMarker();\n";

                if ($this->_params['wppopups'] != 0) {
                    $evt = ($this->_params['wppopups'] == 1) ? '"mouseover"' : '"mousedown"';
                    $s .= " wptmarker{$i}.events.register({$evt}, wptfeature{$i}, wptmarkerClick{$mapno});\n"
                        . " wptmarker{$i}.events.register(\"touchstart\", wptfeature{$i}, wptmarkerClick{$mapno});\n";
                }

                $s .= " layerMarkers{$mapno}.addMarker(wptmarker{$i});\n";
            }
        } else {
            for ($i = 0; $i < $wptcount; $i++) {
                $wptele = $wpttime = $wptdesc = $wptlinks = '';

                if ($this->_params['wppopupele'] !== 0 && $wpts[$i]['ele'] !== '') {
                    $e = $wpts[$i]['ele'];
                    if ($this->_params['wppopupele'] === 'ft') { $e *= 1 / 0.3048; $wptele = ' (' . round($e) . ' ft)'; }
                    if ($this->_params['wppopupele'] === 'm')  { $wptele = ' (' . round($e) . ' m)'; }
                }

                if ($this->_params['wppopuptimefmt'] !== '0' && (int)$wpts[$i]['time'] !== 0) {
                    $wpttime = '<br />' . $this->formatDateTime($this->_params['wppopuptimefmt'], (int)$wpts[$i]['time']);
                }

                if ($this->_params['wppopupdesc'] !== '0' && (string)$wpts[$i]['desc'] !== '') {
                    $d = preg_replace('/\r\n|\r|\n/', '', nl2br((string)$wpts[$i]['desc']));
                    $wptdesc = '<br />' . $d;
                }

                if ($this->_params['wppopuplinkfmt'] !== '0' && (string)$wpts[$i]['links'] !== '') {
                    $wptlinks = '<br />';
                    $ln = 1;
                    foreach (explode("\n", (string)$wpts[$i]['links']) as $lnk) {
                        if (trim($lnk) !== '') {
                            $d = str_replace('%N%', $ln, $this->_params['wppopuplinkfmt']);
                            if ($ln > 1) { $wptlinks .= '&nbsp;'; }
                            $wptlinks .= "<a href='{$lnk}' target='_blank'>{$d}</a>";
                            $ln++;
                        }
                    }
                }

                $s .= "\nfeatures[{$i}] = new OpenLayers.Feature.Vector(toMercator(new OpenLayers.Geometry.Point("
                    . $wpts[$i]['lon'] . ',' . $wpts[$i]['lat'] . ")),\n{ "
                    . "wptname: \"{$wpts[$i]['name']}\", "
                    . "wptele: \"" . addslashes($wptele) . "\", "
                    . "wpttime: \"" . addslashes($wpttime) . "\", "
                    . "wptdesc: \"" . addslashes($wptdesc) . "\", "
                    . "wptlinks: \"" . addslashes($wptlinks) . "\", "
                    . "wptnum: {$i} },\n"
                    . "{ fillColor: '{$this->_params['wpcolor']}', "
                    . "fillOpacity: {$this->_params['trackopacity']}, "
                    . "strokeColor: \"{$this->_params['trackcolor']}\", "
                    . "strokeOpacity: {$this->_params['trackopacity']}, "
                    . "strokeWidth: 1, pointRadius: {$this->_params['wpradius']}, cursor: \"pointer\"} );\n\n";
            }

            if ($this->_params['wppopups'] != 0) {
                $s .= "var vector = new OpenLayers.Layer.Vector(\"Points\",{\n"
                    . "  eventListeners:{\n"
                    . "    'featureselected':function(evt){\n"
                    . "    var feature = evt.feature;\n"
                    . "    var popup = new OpenLayers.Popup.FramedCloud(\"popup\",\n"
                    . "          OpenLayers.LonLat.fromString(feature.geometry.toShortString()),\n"
                    . "          null,\n"
                    . " \"<div style='font-size:.8em'>"
                    . "<span class='gpxwptname'>\" + feature.attributes.wptname + \"</span>"
                    . "<span class='gpxwptele'>\" + feature.attributes.wptele + \"</span>"
                    . "<span class='gpxwpttime'>\" + feature.attributes.wpttime + \"</span>"
                    . "<span class='gpxwpdesc'>\" + feature.attributes.wptdesc + \"</span>"
                    . "<span class='gpxwplinks'>\" + feature.attributes.wptlinks + \"</span>\" + \"</div>\",\n"
                    . "null,true);\n"
                    . "popup.maxSize = new OpenLayers.Size({$popupsize});\n"
                    . "feature.popup = popup;\n"
                    . "{$mapvar}.addPopup(popup); },\n"
                    . "    'featureunselected':function(evt){\n"
                    . "     var feature = evt.feature;\n"
                    . "     {$mapvar}.removePopup(feature.popup);\n"
                    . "     feature.popup.destroy();\n"
                    . "     feature.popup = null; } } });\n"
                    . "vector.addFeatures(features);\n"
                    . "var selector = new OpenLayers.Control.SelectFeature(vector,{\n";

                $s .= ($this->_params['wppopups'] == 1) ? " hover:true,\n" : " toggle:true,\n";

                $s .= " autoActivate:true });\n"
                    . "{$mapvar}.addLayer(vector);\n"
                    . "{$mapvar}.addControl(selector);\n";
            }
        }

        return $s;
    }

    // -------------------------------------------------------------------------
    // filterSignal
    // -------------------------------------------------------------------------

    private function filterSignal(array $s, int $n, int $order): array
    {
        $sn = [];
        if ($order < $n) {
            $sum   = $s[0];
            $sn[0] = $s[0];
            for ($i = 1; $i < $order; $i++) {
                $sn[$i] = $sum / $i;
                $sum    += $s[$i];
            }
            for ($i = $order; $i < $n; $i++) {
                $sn[$i] = $sum / $order;
                $sum    = $sum - $s[$i - $order] + $s[$i];
            }
        }
        return $sn;
    }

    // -------------------------------------------------------------------------
    // renderDiagram
    // -------------------------------------------------------------------------

    private function renderDiagram(
        array $dist, array $data, int $n,
        float|string $mindata, float|string $maxdata, float|string $distance, string $uom,
        string $filepath, string $filename, string $filenamesuffix,
        string $diabgcolor, string $dialinecolor, int $diafillmode,
        string $diaupcolor, string $diadowncolor, int $dialinewidth,
        string $xgrids, string $xgridunits, int $xgridlimit, int $xgridwidth, string $xgridcolor,
        int $ygridlines, int $ygridwidth, string $ygridcolor
    ): string {
        $ext     = pathinfo($filename, PATHINFO_EXTENSION);
        $pos     = strrpos($filename, $ext);
        $destfile = ($pos !== false) ? substr_replace($filename, 'svg', $pos, strlen($filename)) : $filename . '.svg';

        if ($filenamesuffix !== '') {
            $fn   = pathinfo($filename, PATHINFO_FILENAME);
            $pos2 = strrpos($filename, $fn);
            if ($pos2 !== false) {
                $destfile = substr_replace($filename, $fn . $filenamesuffix, $pos2, strlen($filename)) . '.svg';
            }
        }

        if (File::exists($filepath . $destfile) && $this->_params['cache'] == 1) {
            return $destfile;
        }

        $m_ft    = 1 / 0.3048;
        $kmh_mph = 1 / 1.609;
        $kmh_kn  = 1 / 1.852;
        $sep     = $this->_params['tidecimalsep'];

        $e  = '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="1000" height="500"' . "\n"
            . ' viewBox="0 0 10000 10000" preserveAspectRatio="none">' . "\n";
        $e .= '<rect x="0" y="0" width="100%" height="100%" stroke="gray" stroke-width="0" fill="' . $diabgcolor . '" fill-opacity="1" />' . "\n";

        if ($ygridlines > 0) {
            for ($i = 0; $i < $ygridlines; $i++) {
                $yco = ($i === 0) ? 500 : 500 + ($i * (9000 / ($ygridlines - 1)));
                $e  .= '<line x1="0" y1="' . $yco . '" x2="10000" y2="' . $yco . '" stroke-width="' . $ygridwidth . '" stroke="' . $ygridcolor . '" />' . "\n";
            }
        }

        $d1     = $dist[0];
        $a1     = $data[0];
        $yrange = ((float)$maxdata - (float)$mindata) * 1.1;
        $yofs   = ((float)$maxdata - (float)$mindata) * 0.05;
        $xrange = (float)$distance;

        if ($yrange == 0 || $xrange == 0) { return ''; }

        // x grid
        if ($xgrids !== '' && (string)$xgrids !== '0') {
            $xs     = explode('/', trim($xgrids));
            $xgrid  = 0;
            $xgrid_m = 0;

            foreach ($xs as $xg) {
                $xg = (float)$xg;
                if ($xg > 0.0) {
                    $xgrid_m = match ($xgridunits) {
                        'm'  => $xg,
                        'km' => $xg * 1000.0,
                        'ft' => $xg / $m_ft,
                        'mi' => $xg * 1000.0 / $kmh_mph,
                        'nm' => $xg * 1000.0 / $kmh_kn,
                        default => $xg * 1000.0,
                    };
                    $xgrid = $xg;
                    if ($distance * 1000 / $xgrid_m <= $xgridlimit) { break; }
                }
            }

            while ($xgrid_m > 0 && $distance * 1000 / $xgrid_m > $xgridlimit) {
                $xgrid_m *= 2;
                $xgrid   *= 2;
            }

            $xofs   = $xgrid;
            $xofs_m = $xgrid_m;

            while ($xgrid_m > 0 && $xofs_m < $distance * 1000) {
                $xcoord = $xofs_m / $xrange * 10;
                $e .= '<line x1="' . round($xcoord) . '" y1="0" x2="' . round($xcoord) . '" y2="10000" stroke-width="' . $xgridwidth . '" stroke="' . $xgridcolor . '" />' . "\n";
                $xdist = round($xofs, 1) . ' ' . $xgridunits;
                if ($sep !== '.') { $xdist = str_replace('.', $sep, $xdist); }
                $e .= '<g transform="scale(0.3,1)"><text x="' . round($xcoord * (1 / 0.3) + 100) . '" y="9950" font-family="Verdana" font-size="500" fill="black">' . $xdist . '</text></g>' . "\n";
                $xofs   += $xgrid;
                $xofs_m += $xgrid_m;
            }
        }

        $x1 = 0;
        $y1 = (($a1 - (float)$mindata + $yofs) / $yrange) * 100;

        $x1coord = round($x1 * 100);
        $y1coord = 10000 - round($y1 * 100);
        $lw      = $dialinewidth * 10;

        $polyline = '<polyline fill-opacity="0" stroke-width="' . $lw . '" stroke="' . $dialinecolor . '" points="' . $x1coord . ',' . $y1coord . ' ';
        $polygons = '';

        for ($i = 1; $i < ($n - 1); $i++) {
            $d2 = $dist[$i];
            $a2 = $data[$i];
            $x2 = $d2 / $xrange * 100;
            $y2 = (($a2 - (float)$mindata + $yofs) / $yrange) * 100;

            $x2coord = round($x2 * 100);
            $y2coord = 10000 - round($y2 * 100);
            $fill    = '';
            $m       = 0;

            if ($diafillmode == 1) {
                if ($x2 != $x1) {
                    $grad = ($y2 - $y1) / ($x2 - $x1);
                    $fill = ($grad > 0) ? $diaupcolor : $diadowncolor;
                    $m    = round(sqrt(sqrt(abs($grad))) * 0.4, 3);
                } else {
                    $fill = 'white';
                }
            } elseif ($diafillmode == 2) {
                $m    = ($a2 - (float)$mindata) / ((float)$maxdata - (float)$mindata);
                $fill = $diaupcolor;
            }

            if ($fill !== '') {
                $polygons .= '<polygon fill-opacity="' . $m . '" fill="' . $fill . '" stroke-width="0" points="'
                    . $x1coord . ',9500 ' . $x1coord . ',' . $y1coord . ' '
                    . $x2coord . ',' . $y2coord . ' ' . $x2coord . ',9500" />' . "\n";
            }

            $polyline .= $x2coord . ',' . $y2coord . ' ';
            $x1 = $x2; $y1 = $y2; $x1coord = $x2coord; $y1coord = $y2coord;
        }

        $e .= $polygons . $polyline . '" />';
        $e .= '<g transform="scale(0.3,1)">' . "\n";

        if ($ygridlines > 0) {
            for ($i = 0; $i < $ygridlines; $i++) {
                if ($i === $ygridlines - 1) {
                    $yco  = 9950;
                    $ecur = (float)$mindata;
                } else {
                    $yco  = 500 - 50 + ($i * (9000 / ($ygridlines - 1)));
                    $ecur = (float)$maxdata - (((float)$maxdata - (float)$mindata) / ($ygridlines - 1) * $i);
                }

                $ecur = match ($uom) {
                    'm'    => round($ecur, 1),
                    'ft'   => round($ecur * $m_ft, 1),
                    'km/h' => round($ecur, 1),
                    'mph'  => round($ecur * $kmh_mph, 1),
                    'kn'   => round($ecur * $kmh_kn,  1),
                    default => round($ecur, 1),
                };

                if ($sep !== '.') { $ecur = str_replace('.', $sep, (string)$ecur); }

                $e .= '<text x="100" y="' . $yco . '" font-family="Verdana" font-size="500" fill="black">' . $ecur . ' ' . $uom . '</text>' . "\n";
            }
        }

        $e .= "</g>\n</svg>\n";

        if (File::exists($filepath . $destfile)) { File::delete($filepath . $destfile); }

        if (!$this->isWritable($filepath . $destfile)) { return ''; }

        $fh = fopen($filepath . $destfile, 'c');
        fwrite($fh, $e);
        fclose($fh);

        return $destfile;
    }

    // -------------------------------------------------------------------------
    // getGPXTime
    // -------------------------------------------------------------------------

    private function getGPXTime(mixed $gpxtimestr): int
    {
        $s   = (string)$gpxtimestr;
        $y   = (int)substr($s,  0, 4);
        $mo  = (int)substr($s,  5, 2);
        $d   = (int)substr($s,  8, 2);
        $h   = (int)substr($s, 11, 2);
        $mi  = (int)substr($s, 14, 2);
        $sec = (int)substr($s, 17, 2);

        $ts = gmmktime($h, $mi, $sec, $mo, $d, $y);

        if ($this->_params['titimeshift'] != 0) {
            $ts += (int)$this->_params['titimeshift'] * 3600;
        }

        return $ts;
    }

    // -------------------------------------------------------------------------
    // ziptrackfile  (Joomla 5 / modern Archive API)
    // -------------------------------------------------------------------------

    private function ziptrackfile(string $filepath, string $filename): string|false
    {
        $ext      = pathinfo($filename, PATHINFO_EXTENSION);
        $pos      = strrpos($filename, $ext);
        $destfile = ($pos !== false) ? substr_replace($filename, 'zip', $pos, strlen($filename)) : $filename . '.zip';

        $fullgpxpath = $this->_absolute_path . DIRECTORY_SEPARATOR . $filepath . $filename;
        $fullzippath = $this->_absolute_path . DIRECTORY_SEPARATOR . $filepath . $destfile;

        if (File::exists($fullzippath) && $this->_params['cache'] == 1) {
            return $filepath . $destfile;
        }

        if (!$this->isWritable($fullzippath)) {
            $this->_warnings .= 'Unable to create zip file. Please check write permissions!' . "\n";
            return false;
        }

        if (!File::exists($fullgpxpath)) { return false; }

        $filesToZip = [[
            'data' => file_get_contents($fullgpxpath),
            'name' => basename($filename),
        ]];

        $archive = new Archive();
        $zip     = $archive->getAdapter('zip');
        $zip->create($fullzippath, $filesToZip);

        return File::exists($fullzippath) ? $filepath . $destfile : false;
    }

    // -------------------------------------------------------------------------
    // markerFilename
    // -------------------------------------------------------------------------

    private function markerFilename(mixed $markertype, mixed $markerset): string
    {
        $colorMap = [1=>'blue',2=>'red',3=>'green',4=>'yellow',5=>'white',6=>'gray',7=>'black'];
        $knownColors = ['blue','red','green','yellow','white','gray','black'];

        if (isset($colorMap[(int)$markertype])) {
            $clr = $colorMap[(int)$markertype];
        } elseif (in_array($markertype, $knownColors, true)) {
            $clr = $markertype;
        } else {
            return '';
        }

        return 'marker' . $markerset . '-' . $clr . '.png';
    }

    // -------------------------------------------------------------------------
    // isWritable  (replaces is__writable)
    // -------------------------------------------------------------------------

    private function isWritable(string $path): bool
    {
        if (str_ends_with($path, '/')) {
            return $this->isWritable($path . uniqid((string)mt_rand()) . '.tmp');
        }

        if (is_dir($path)) {
            return $this->isWritable($path . '/' . uniqid((string)mt_rand()) . '.tmp');
        }

        $existed = file_exists($path);
        $fh      = @fopen($path, 'a');

        if ($fh === false) { return false; }

        fclose($fh);
        if (!$existed) { unlink($path); }

        return true;
    }
}
