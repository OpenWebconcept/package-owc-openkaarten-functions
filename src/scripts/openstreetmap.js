import L from "leaflet";

// Retrieve the locations and map configuration from the global window object.
const { centerLat, centerLong, defaultZoom, setMarker, markers } = window.leaflet_vars;

let markersArray = markers;

// Set the map configuration.
const config = {
  "centerX": centerLong,
  "centerY": centerLat,
  "minimumZoom": 4,
  "maximumZoom": 16,
  "defaultZoom": defaultZoom,
  "enableZoomControl": true,
  "enableBoxZoomControl": true
}

// Create the map with the specified configuration.
window.onload = function() {
  const map = new L.Map('map-geodata', {
    center: [config.centerY, config.centerX],
    zoom: config.defaultZoom,
    minZoom: config.minimumZoom,
    maxZoom: config.maximumZoom,
    boxZoom: config.enableBoxZoomControl
  });

  // Add the OpenStreetMap tile layer to the map.
  L.tileLayer('https://{s}.tile.osm.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://osm.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);

  // Add a marker to the map if the location is set.
  if (setMarker) {
    // Add a marker for every location in the markers array.
    markersArray.forEach( function( location ) {
      addMarker( map, location[1], location[0], false );
    } );
  }

  map.on( 'click', function (e) {
    var coord = e.latlng;
    var lat = coord.lat;
    var lng = coord.lng;

    // Add a draggable marker to the map.
    addMarker( map, lat, lng );
    map.panTo( new L.LatLng( lat, lng ) );
  } );
};

function addMarker( map, lat, lng ) {
  // Create a custom marker icon with the location color and icon.
  let customIconHtml = "<div style='background-color:" + location.color + ";' class='marker-pin'></div>";
  if (location.icon) {
    customIconHtml += "<span class='marker-icon'><img src='" + location.icon + "'  alt='marker icon' /></span>";
  }

  var customIcon = L.divIcon( {
    className: 'leaflet-custom-icon',
    html: customIconHtml,
    iconSize: [30, 42],
    iconAnchor: [15, 42]
  } );

  let iconOptions = {
    icon: customIcon,
    draggable: 'true'
  }

  var marker = L.marker( [lat, lng], iconOptions );
  marker.on( 'dragend', function (event) {
    var marker = event.target;
    var position = marker.getLatLng();
    marker.setLatLng( new L.LatLng( position.lat, position.lng ), {draggable: 'true'} );
    map.panTo( new L.LatLng( position.lat, position.lng ) );
  } );

  // Add an on right click to the marker to remove it.
  marker.on( 'contextmenu', function (event) {
    map.removeLayer( event.target );
    updateMarkers( map );
  }, this );

  map.addLayer( marker );

  // Update markers after adding marker.
  updateMarkers( map );
}

function updateMarkers( map ) {
  // Retrieve all markers from the map.
  let markers = map._layers;

  // Filter out the markers from the map.
  markers = Object.values( markers ).filter( function( marker ) {
    return marker instanceof L.Marker;
  } );

  // Create an array with the marker coordinates.
  let markerData = markers.map( function( marker ) {

    if ( marker[0] && marker[1] ) {
      return {
        lat: marker[1],
        lng: marker[0]
      }
    }

    return {
      lat: marker.getLatLng().lat,
      lng: marker.getLatLng().lng
    }
  } );

  // Pass the marker data to PHP.
  updateGeoFields( markerData );
}

function updateGeoFields( markerData ) {
  // Update hidden CMB2 fields with the marker data.
  jQuery( '#location_geometry_coordinates' ).val( JSON.stringify( markerData ) );
}
