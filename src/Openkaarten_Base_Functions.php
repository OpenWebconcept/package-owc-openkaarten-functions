<?php
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
	 * Save the location geometry object.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return void
	 */
	public static function save_geometry_object( $post_id ) {
		error_log(print_r($_POST, true));

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
					$address = sanitize_text_field( wp_unslash( $_POST['field_geo_address'] ) );
					$zipcode = sanitize_text_field( wp_unslash( $_POST['field_geo_zipcode'] ) );
					$city    = sanitize_text_field( wp_unslash( $_POST['field_geo_city'] ) );
					$country = sanitize_text_field( wp_unslash( $_POST['field_geo_country'] ) );

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

					$geometry  = [
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
					$marker_data = json_decode( stripslashes( $_POST['location_geometry_coordinates'] ), true );

					if ( ! $marker_data ) {
						return;
					}

					// Remove duplicates from the array where lat and lng are the same.
					$marker_data = array_map( 'unserialize', array_unique( array_map( 'serialize', $marker_data ) ) );

					// Make the geometry object based on the amount of markers.
					if ( 1 === count( $marker_data ) ) {
						$marker_data = $marker_data[0];
						$geometry  = [
							'type'        => 'Point',
							'coordinates' => [ (float) $marker_data['lng'], (float) $marker_data['lat'] ],
						];
					} else {
						$geometry_coordinates = [];
						foreach ( $marker_data as $marker ) {
							$geometry_coordinates[] = [ (float) $marker['lng'], (float) $marker['lat'] ];
						}

						$geometry  = [
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
			'properties' => [],
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

}
