<?php //phpcs:ignore WordPress.Files.FileName -- This class name is necessary for the autoloader.

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the public-facing side of the site and
 * the admin area.
 *
 * @link       https://www.openwebconcept.nl
 *
 * @package    Openkaarten_Base_Functions
 */

namespace Openkaarten_Base_Functions;

use CMB2_Field;
use geoPHP\Exception\IOException;
use geoPHP\geoPHP;

define( 'OWC_OPENKAARTEN_FUNCTIONS_VERSION', '0.0.3' );

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and public-facing site hooks.
 *
 * @package    Openkaarten_Base_Functions
 * @author     Acato <eyal@acato.nl>
 */
class Openkaarten_Base_Functions {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @return void
	 */
	public static function init() {
		self::init_hooks();
	}

	/**
	 * Register the hooks for the admin area.
	 *
	 * @return void
	 */
	public static function init_hooks() {
		add_action( 'cmb2_render_geomap', array( 'Openkaarten_Base_Functions\Openkaarten_Base_Functions', 'cmb2_render_geomap_field_type' ), 10, 5 );
	}

	/**
	 * Save the location geometry object.
	 *
	 * @param int   $post_id The post ID.
	 * @param array $properties The properties of the location.
	 *
	 * @return void
	 */
	public static function save_geometry_object( $post_id, $properties = [] ) {
		if ( wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Check nonce.
		if ( ! isset( $_POST['nonce_CMB2phplocation_geometry_metabox'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce_CMB2phplocation_geometry_metabox'] ) ), 'nonce_CMB2phplocation_geometry_metabox' ) ) {
			return;
		}

		// Retrieve the latitude and longitude by address.
		if ( isset( $_POST['location_geometry_geodata_type'] ) ) {

			switch ( sanitize_text_field( wp_unslash( $_POST['location_geometry_geodata_type'] ) ) ) {
				case 'address':
					$address = isset( $_POST['field_geo_address'] ) ? sanitize_text_field( wp_unslash( $_POST['field_geo_address'] ) ) : '';
					$zipcode = isset( $_POST['field_geo_zipcode'] ) ? sanitize_text_field( wp_unslash( $_POST['field_geo_zipcode'] ) ) : '';
					$city    = isset( $_POST['field_geo_city'] ) ? sanitize_text_field( wp_unslash( $_POST['field_geo_city'] ) ) : '';
					$country = isset( $_POST['field_geo_country'] ) ? sanitize_text_field( wp_unslash( $_POST['field_geo_country'] ) ) : '';

					// Update post meta data.
					update_post_meta( $post_id, 'field_geo_address', wp_slash( $address ) );
					update_post_meta( $post_id, 'field_geo_zipcode', wp_slash( $zipcode ) );
					update_post_meta( $post_id, 'field_geo_city', wp_slash( $city ) );
					update_post_meta( $post_id, 'field_geo_country', wp_slash( $country ) );

					$address .= ' ' . $zipcode . ' ' . $city . ' ' . $country;

					$lat_long = self::convert_address_to_latlong( sanitize_text_field( wp_unslash( $address ) ) );
					if ( ! empty( $lat_long['latitude'] ) && ! empty( $lat_long['longitude'] ) ) {
						$latitude  = sanitize_text_field( wp_unslash( $lat_long['latitude'] ) );
						$longitude = sanitize_text_field( wp_unslash( $lat_long['longitude'] ) );
					}

					$geometry_coordinates = [ (float) $longitude, (float) $latitude ];

					$geometry = [
						'type'        => 'Point',
						'coordinates' => $geometry_coordinates,
					];
					break;
				case 'marker':
					// Check if there is a location_geometry_coordinates input.
					if ( ! isset( $_POST['location_geometry_coordinates'] ) ) {
						return;
					}

					// Check if the input has one or multiple markers in it.
					$marker_data = json_decode( stripslashes( sanitize_text_field( wp_unslash( $_POST['location_geometry_coordinates'] ) ) ), true );

					if ( ! $marker_data ) {
						return;
					}

					// Remove duplicates from the array where lat and lng are the same.
					$marker_data = array_map( 'unserialize', array_unique( array_map( 'serialize', $marker_data ) ) );

					// Make the geometry object based on the amount of markers.
					if ( 1 === count( $marker_data ) ) {
						$marker_data = $marker_data[0];
						$geometry    = [
							'type'        => 'Point',
							'coordinates' => [ (float) $marker_data['lng'], (float) $marker_data['lat'] ],
						];
					} else {
						$geometry_coordinates = [];
						foreach ( $marker_data as $marker ) {
							$geometry_coordinates[] = [ (float) $marker['lng'], (float) $marker['lat'] ];
						}

						$geometry = [
							'type'        => 'MultiPoint',
							'coordinates' => $geometry_coordinates,
						];
					}

					// Delete the address fields.
					delete_post_meta( $post_id, 'field_geo_address' );
					delete_post_meta( $post_id, 'field_geo_zipcode' );
					delete_post_meta( $post_id, 'field_geo_city' );
					delete_post_meta( $post_id, 'field_geo_country' );

					break;
			}
		}

		$component = [
			'type'       => 'Feature',
			'properties' => $properties,
			'geometry'   => $geometry,
		];
		$component = wp_json_encode( $component );

		// Check if post meta exists and update or add the post meta.
		if ( metadata_exists( 'post', $post_id, 'geometry' ) ) {
			update_post_meta( $post_id, 'geometry', wp_slash( $component ) );
		} else {
			add_post_meta( $post_id, 'geometry', wp_slash( $component ), true );
		}
	}

	/**
	 * Get latitude and longitude from an address with OpenStreetMap.
	 *
	 * @param string $address The address.
	 *
	 * @return array|null
	 */
	public static function convert_address_to_latlong( $address ) {

		if ( ! $address ) {
			return null;
		}

		$address     = str_replace( ' ', '+', $address );
		$osm_url     = 'https://nominatim.openstreetmap.org/search?q=' . $address . '&format=json&addressdetails=1';
		$osm_address = wp_remote_get( $osm_url );

		if ( ! $osm_address ) {
			return null;
		}

		$osm_address = json_decode( $osm_address['body'] );

		if ( ! $osm_address[0]->lat || ! $osm_address[0]->lon ) {
			return null;
		}

		$latitude  = $osm_address[0]->lat;
		$longitude = $osm_address[0]->lon;

		return [
			'latitude'  => $latitude,
			'longitude' => $longitude,
		];
	}

	/**
	 * Register the CMB2 metaboxes for the geometry fields for the Location post type.
	 *
	 * @param int   $post_id The post ID.
	 * @param array $post_types The post types to add the metabox to.
	 *
	 * @return void
	 */
	public static function cmb2_location_geometry_fields( $post_id, $post_types ) {
		$prefix = 'location_geometry_';

		$cmb = new_cmb2_box(
			array(
				'id'           => $prefix . 'metabox',
				'title'        => __( 'Geodata', 'openkaarten-functions' ),
				'object_types' => $post_types,
				'context'      => 'normal',
				'priority'     => 'low',
				'show_names'   => true,
				'cmb_styles'   => true,
				'show_in_rest' => true,
			)
		);

		// Add field to select whether to insert geodata based on a map marker or on an address.
		$cmb->add_field(
			array(
				'id'           => $prefix . 'geodata_type',
				'name'         => __( 'Geodata type', 'openkaarten-functions' ),
				'type'         => 'radio',
				'options'      => array(
					'marker'  => __( 'Marker(s)', 'openkaarten-functions' ),
					'address' => __( 'Address', 'openkaarten-functions' ),
				),
				'default'      => 'marker',
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'id'           => $prefix . 'coordinates',
				'name'         => __( 'Coordinates', 'openkaarten-functions' ),
				'desc'         => __( 'Click on the map to add a new marker. Drag the marker after adding to change the location of the marker. Right click on a marker to remove the marker from the map.', 'openkaarten-functions' ),
				'type'         => 'geomap',
				'show_in_rest' => true,
				'save_field'   => false,
				'attributes'   => array(
					'data-conditional-id'    => $prefix . 'geodata_type',
					'data-conditional-value' => 'marker',
				),
			)
		);

		// Add address and latitude and longitude fields.
		$address_fields = array(
			'address'   => __( 'Address + number', 'openkaarten-functions' ),
			'zipcode'   => __( 'Zipcode', 'openkaarten-functions' ),
			'city'      => __( 'City', 'openkaarten-functions' ),
			'country'   => __( 'Country', 'openkaarten-functions' ),
			'latitude'  => __( 'Latitude', 'openkaarten-functions' ),
			'longitude' => __( 'Longitude', 'openkaarten-functions' ),
		);

		foreach ( $address_fields as $field_key => $field ) {
			// Check if this field has a value and set it as readonly and disabled if it has.
			$field_value = get_post_meta( $post_id, 'field_geo_' . $field_key, true );
			$attributes  = array(
				'data-conditional-id'    => $prefix . 'geodata_type',
				'data-conditional-value' => 'address',
			);

			if ( 'latitude' === $field_key || 'longitude' === $field_key ) {
				$attributes = array_merge(
					$attributes,
					array(
						'readonly' => 'readonly',
					)
				);
			}

			$cmb->add_field(
				array(
					'name'         => $field,
					'id'           => 'field_geo_' . $field_key,
					'type'         => 'text',
					/* translators: %s: The field name. */
					'description'  => sprintf( __( 'The %s of the location.', 'openkaarten-functions' ), strtolower( $field ) ),
					'show_in_rest' => true,
					'attributes'   => $attributes,
					'save_field'   => false,
				)
			);
		}
	}

	/**
	 * Render the geomap field type.
	 *
	 * @param CMB2_Field $field The CMB2 field object.
	 * @param mixed      $escaped_value The value of the field.
	 * @param int        $object_id The object ID.
	 *
	 * @return void
	 */
	public static function cmb2_render_geomap_field_type( $field, $escaped_value, $object_id ) {
		// Get latitude and longitude of centre of the Netherlands as starting point.
		$center_lat  = 52.1326;
		$center_long = 5.2913;

		// Retrieve the current values of the latitude and longitude of the markers from the geometry object.
		$set_marker = false;

		$markers  = array();
		$geometry = get_post_meta( $object_id, 'geometry', true );

		if ( ! empty( $geometry ) ) {
			// Parse the geometry object.
			try {
				$geometry_data = geoPHP::load( $geometry );

				// Check if geometry type is Point or MultiPoint.
				if ( 'Point' !== $geometry_data->geometryType() && 'MultiPoint' !== $geometry_data->geometryType() ) {
					echo esc_html__( 'This geometry type can\'t be viewed on this map for the location post type. It can be plotted on the map via the Datalayer.', 'openkaarten-functions' );
					return;
				}

				if ( ! $geometry_data->getBBox() ) {
					return;
				}

				$bbox = $geometry_data->getBBox();

				$center_lat  = $bbox['miny'] + ( ( $bbox['maxy'] - $bbox['miny'] ) / 2 );
				$center_long = $bbox['minx'] + ( ( $bbox['maxx'] - $bbox['minx'] ) / 2 );

				// Set the marker to true.
				$set_marker = true;

				// Add the marker to the markers array.
				$markers = $geometry_data->getComponents();

				// Create array of markers.
				$markers = array_map(
					function ( $marker ) {
						return [ $marker->x(), $marker->y() ];
					},
					$markers
				);

			} catch ( IOException $e ) {
				return;
			}
		}

		// Enqueue the OpenStreetMap script.
		wp_localize_script(
			'owc_ok-openstreetmap-geodata',
			'leaflet_vars',
			array(
				'centerLat'   => esc_attr( $center_lat ),
				'centerLong'  => esc_attr( $center_long ),
				'defaultZoom' => 10,
				'fitBounds'   => false,
				'allowClick'  => true,
				'setMarker'   => $set_marker,
				'markers'     => $markers,
			)
		);

		// Add the map and the hidden input field. This hidden input field is needed for the CMB2 Conditional Logic to work, but doesn't store any data itself.
		echo '<div id="map-geodata" class="map-geodata"></div>
		<p class="cmb2-metabox-description">' . esc_attr( $field->args['desc'] ) . '</p>
		<input type="hidden" id="' . esc_attr( $field->args['id'] ) . '" name="' . esc_attr( $field->args['_name'] ) . '" data-conditional-id="location_geometry_geodata_type" data-conditional-value="marker">';
	}
}
