<script type="text/javascript">

  function osm_getTileURL(bounds) {
    var res = this.map.getResolution();
    var x = Math.round((bounds.left - this.maxExtent.left) / (res * this.tileSize.w));
    var y = Math.round((this.maxExtent.top - bounds.top) / (res * this.tileSize.h));
    var z = this.map.getZoom();
    var limit = Math.pow(2, z);

    if (y < 0 || y >= limit) {
      return OpenLayers.Util.getImagesLocation() + "404.png";
    } else {
      x = ((x % limit) + limit) % limit;
      return this.url + z + "/" + x + "/" + y + "." + this.type;
    }
  }

  function onWindowResize_%MAPVAR%() {
    setTimeout(function() { %MAPVAR%.updateSize(); }, 200);
  }

  window.addEventListener("resize", onWindowResize_%MAPVAR%, false);

  var %MAPVAR%;

  function initMap_%MAPVAR%() {

    // Safety check: if OpenLayers or the map div are not ready yet, retry in 100ms
    if (typeof OpenLayers === 'undefined' || !document.getElementById('%MAPVAR%')) {
      setTimeout(initMap_%MAPVAR%, 100);
      return;
    }

    window.%MAPVAR% = new OpenLayers.Map("%MAPVAR%", {
      controls: [%MAPCONTROLS%
                 new OpenLayers.Control.Attribution()],
      maxExtent: new OpenLayers.Bounds(-20037508.34, -20037508.34, 20037508.34, 20037508.34),
      maxResolution: 156543.0399,
      numZoomLevels: 19,
      units: 'm',
      projection: new OpenLayers.Projection("EPSG:900913"),
      displayProjection: new OpenLayers.Projection("EPSG:4326")
    });

    %NOMOUSEWHEELZOOM%
    %MAPLAYERS%

    var lgpx = new OpenLayers.Layer.Vector("%GPXLAYERNAME%", {
      strategies: [new OpenLayers.Strategy.Fixed()],
      protocol: new OpenLayers.Protocol.HTTP({
        url: "%GPXPATH%",
        format: new OpenLayers.Format.GPX({ %EXTRACTCODE% })
      }),
      style: {
        strokeColor: "%TRACKCOLOR%",
        strokeWidth: %TRACKWIDTH%,
        strokeOpacity: %TRACKOPACITY%,
        strokeDashstyle: %TRACKDASHSTYLE%,
        pointRadius: %WPRADIUS%,
        fillColor: "%WPCOLOR%"
      },
      projection: new OpenLayers.Projection("EPSG:4326")
    });

    %MAPVAR%.addLayer(lgpx);
    %HILLSHADINGLAYER%
    %MARKERLAYER%

    lgpx.events.register("loadend", lgpx, function() {
      %ZOOMCODE%
      %MARKERCODE%
      %WPTCODE%
    });

    OpenLayers.Util.onImageLoadError = function() {
      this.src = "/plugins/content/gpxtrackmap/markers/404.png";
    };
  }

  // Launch immediately if DOM is still loading, otherwise use setTimeout(0)
  // to ensure the map div is rendered before we try to use it.
  // We do NOT use 'load' because by the time Joomla injects this inline script
  // the load event has already fired and would never trigger again.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMap_%MAPVAR%);
  } else {
    setTimeout(initMap_%MAPVAR%, 0);
  }

  function switch_map_fullscreen_%MAPVAR%(onoroff) {
    var growbtn   = document.getElementById('gtm_fullscreen_on_%MAPVAR%');
    var shrinkbtn = document.getElementById('gtm_fullscreen_off_%MAPVAR%');
    var mapdiv    = document.getElementById('%MAPVAR%');
    var bgdiv     = document.getElementById('gtm_fullscreen_bg_%MAPVAR%');

    if (bgdiv) {
      bgdiv.style.display = (onoroff === "on") ? "inline" : "none";
    }

    if (mapdiv) {
      if (onoroff === "on") {
        mapdiv.style.position = "fixed";
        mapdiv.style.top      = "0";
        mapdiv.style.left     = "0";
        mapdiv.style.width    = "100%";
        mapdiv.style.height   = "100%";
        mapdiv.style.zIndex   = "1100";
      } else {
        mapdiv.style.position = "relative";
        mapdiv.style.top      = "0";
        mapdiv.style.left     = "0";
        mapdiv.style.width    = "%MAPWIDTH%";
        mapdiv.style.height   = "%MAPHEIGHT%";
        mapdiv.style.zIndex   = "auto";
      }
    }

    window.%MAPVAR%.updateSize();

    if (onoroff === "on") {
      window.%MAPVAR%.zoomIn();
    } else {
      window.%MAPVAR%.zoomOut();
    }

    if (growbtn)   { growbtn.style.display   = (onoroff === "on") ? "none"   : "inline"; }
    if (shrinkbtn) { shrinkbtn.style.display = (onoroff === "on") ? "inline" : "none";   }
  }

</script>
