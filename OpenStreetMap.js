/**
 * OpenStreetMap tile layer definitions for OpenLayers 2.x
 * Updated URLs for current tile servers (2024+)
 */

/**
 * Class: OpenLayers.Layer.OSM.Mapnik
 * Standard OpenStreetMap Mapnik rendering.
 *
 * Inherits from:
 *  - <OpenLayers.Layer.OSM>
 */
OpenLayers.Layer.OSM.Mapnik = OpenLayers.Class(OpenLayers.Layer.OSM, {
    /**
     * Constructor: OpenLayers.Layer.OSM.Mapnik
     *
     * Parameters:
     * name    - {String}
     * options - {Object} Hashtable of extra options to tag onto the layer
     */
    initialize: function(name, options) {
        var url = [
            "//a.tile.openstreetmap.org/${z}/${x}/${y}.png",
            "//b.tile.openstreetmap.org/${z}/${x}/${y}.png",
            "//c.tile.openstreetmap.org/${z}/${x}/${y}.png"
        ];
        options = OpenLayers.Util.extend({
            numZoomLevels: 19,
            buffer: 0,
            transitionEffect: "resize"
        }, options);
        OpenLayers.Layer.OSM.prototype.initialize.apply(this, [name, url, options]);
    },

    CLASS_NAME: "OpenLayers.Layer.OSM.Mapnik"
});

/**
 * Class: OpenLayers.Layer.OSM.CycleMap
 * Thunderforest OpenCycleMap layer.
 * Requires a free API key from https://www.thunderforest.com/
 * Pass it via options: { apiKey: 'YOUR_KEY' }
 *
 * Inherits from:
 *  - <OpenLayers.Layer.OSM>
 */
OpenLayers.Layer.OSM.CycleMap = OpenLayers.Class(OpenLayers.Layer.OSM, {
    /**
     * Constructor: OpenLayers.Layer.OSM.CycleMap
     *
     * Parameters:
     * name    - {String}
     * options - {Object} Hashtable of extra options; supports { apiKey: '...' }
     */
    initialize: function(name, options) {
        var apiKey = (options && options.apiKey) ? "?apikey=" + options.apiKey : "";
        var url = [
            "//a.tile.thunderforest.com/cycle/${z}/${x}/${y}.png" + apiKey,
            "//b.tile.thunderforest.com/cycle/${z}/${x}/${y}.png" + apiKey,
            "//c.tile.thunderforest.com/cycle/${z}/${x}/${y}.png" + apiKey
        ];
        options = OpenLayers.Util.extend({
            numZoomLevels: 19,
            buffer: 0,
            transitionEffect: "resize",
            attribution: 'Maps &copy; <a href="https://www.thunderforest.com/">Thunderforest</a>, ' +
                         'Data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }, options);
        OpenLayers.Layer.OSM.prototype.initialize.apply(this, [name, url, options]);
    },

    CLASS_NAME: "OpenLayers.Layer.OSM.CycleMap"
});

/**
 * Class: OpenLayers.Layer.OSM.TransportMap
 * Thunderforest Transport layer.
 * Requires a free API key from https://www.thunderforest.com/
 * Pass it via options: { apiKey: 'YOUR_KEY' }
 *
 * Inherits from:
 *  - <OpenLayers.Layer.OSM>
 */
OpenLayers.Layer.OSM.TransportMap = OpenLayers.Class(OpenLayers.Layer.OSM, {
    /**
     * Constructor: OpenLayers.Layer.OSM.TransportMap
     *
     * Parameters:
     * name    - {String}
     * options - {Object} Hashtable of extra options; supports { apiKey: '...' }
     */
    initialize: function(name, options) {
        var apiKey = (options && options.apiKey) ? "?apikey=" + options.apiKey : "";
        var url = [
            "//a.tile.thunderforest.com/transport/${z}/${x}/${y}.png" + apiKey,
            "//b.tile.thunderforest.com/transport/${z}/${x}/${y}.png" + apiKey,
            "//c.tile.thunderforest.com/transport/${z}/${x}/${y}.png" + apiKey
        ];
        options = OpenLayers.Util.extend({
            numZoomLevels: 19,
            buffer: 0,
            transitionEffect: "resize",
            attribution: 'Maps &copy; <a href="https://www.thunderforest.com/">Thunderforest</a>, ' +
                         'Data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }, options);
        OpenLayers.Layer.OSM.prototype.initialize.apply(this, [name, url, options]);
    },

    CLASS_NAME: "OpenLayers.Layer.OSM.TransportMap"
});

/**
 * Class: OpenLayers.Layer.OSM.Landscape
 * Thunderforest Landscape layer.
 * Requires a free API key from https://www.thunderforest.com/
 * Pass it via options: { apiKey: 'YOUR_KEY' }
 *
 * Inherits from:
 *  - <OpenLayers.Layer.OSM>
 */
OpenLayers.Layer.OSM.Landscape = OpenLayers.Class(OpenLayers.Layer.OSM, {
    initialize: function(name, options) {
        var apiKey = (options && options.apiKey) ? "?apikey=" + options.apiKey : "";
        var url = [
            "//a.tile.thunderforest.com/landscape/${z}/${x}/${y}.png" + apiKey,
            "//b.tile.thunderforest.com/landscape/${z}/${x}/${y}.png" + apiKey,
            "//c.tile.thunderforest.com/landscape/${z}/${x}/${y}.png" + apiKey
        ];
        options = OpenLayers.Util.extend({
            numZoomLevels: 19,
            buffer: 0,
            transitionEffect: "resize",
            attribution: 'Maps &copy; <a href="https://www.thunderforest.com/">Thunderforest</a>, ' +
                         'Data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }, options);
        OpenLayers.Layer.OSM.prototype.initialize.apply(this, [name, url, options]);
    },

    CLASS_NAME: "OpenLayers.Layer.OSM.Landscape"
});

/**
 * Class: OpenLayers.Layer.OSM.Outdoors
 * Thunderforest Outdoors layer.
 * Requires a free API key from https://www.thunderforest.com/
 * Pass it via options: { apiKey: 'YOUR_KEY' }
 *
 * Inherits from:
 *  - <OpenLayers.Layer.OSM>
 */
OpenLayers.Layer.OSM.Outdoors = OpenLayers.Class(OpenLayers.Layer.OSM, {
    initialize: function(name, options) {
        var apiKey = (options && options.apiKey) ? "?apikey=" + options.apiKey : "";
        var url = [
            "//a.tile.thunderforest.com/outdoors/${z}/${x}/${y}.png" + apiKey,
            "//b.tile.thunderforest.com/outdoors/${z}/${x}/${y}.png" + apiKey,
            "//c.tile.thunderforest.com/outdoors/${z}/${x}/${y}.png" + apiKey
        ];
        options = OpenLayers.Util.extend({
            numZoomLevels: 19,
            buffer: 0,
            transitionEffect: "resize",
            attribution: 'Maps &copy; <a href="https://www.thunderforest.com/">Thunderforest</a>, ' +
                         'Data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }, options);
        OpenLayers.Layer.OSM.prototype.initialize.apply(this, [name, url, options]);
    },

    CLASS_NAME: "OpenLayers.Layer.OSM.Outdoors"
});

/**
 * Class: OpenLayers.Layer.OSM.OpenTopoMap
 * OpenTopoMap topographic layer.
 *
 * Inherits from:
 *  - <OpenLayers.Layer.OSM>
 */
OpenLayers.Layer.OSM.OpenTopoMap = OpenLayers.Class(OpenLayers.Layer.OSM, {
    initialize: function(name, options) {
        var url = [
            "//a.tile.opentopomap.org/${z}/${x}/${y}.png",
            "//b.tile.opentopomap.org/${z}/${x}/${y}.png",
            "//c.tile.opentopomap.org/${z}/${x}/${y}.png"
        ];
        options = OpenLayers.Util.extend({
            numZoomLevels: 18,
            buffer: 0,
            transitionEffect: "resize",
            tileOptions: { crossOriginKeyword: null },
            attribution: 'Map data &copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a> contributors, ' +
                         '<a href="https://viewfinderpanoramas.org">SRTM</a> | ' +
                         'Map style &copy; <a href="https://opentopomap.org">OpenTopoMap</a> ' +
                         '<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>'
        }, options);
        OpenLayers.Layer.OSM.prototype.initialize.apply(this, [name, url, options]);
    },

    CLASS_NAME: "OpenLayers.Layer.OSM.OpenTopoMap"
});
