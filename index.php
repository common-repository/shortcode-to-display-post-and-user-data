<?php

/*
  Plugin Name: Shortcode to display post and user data
  Description: Display post and user data on the frontend using a shortcode.
  Version: 1.3.0
  Author: Jose Vega
  Author Email: josevega@vegacorp.me
  License:

  Copyright 2011 JoseVega (josevega@vegacorp.me)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

 */
require 'vendor/vg-plugin-sdk/index.php';
require_once __DIR__ . '/settings.php';
$vgds_plugin_sdk = new VG_Freemium_Plugin_SDK(
	array(
		'main_plugin_file'  => __FILE__,
		'show_welcome_page' => true,
		'welcome_page_file' => __DIR__ . '/views/welcome-page-content.php',
		'plugin_name'       => 'Shortcode to display post and user data',
		'plugin_prefix'     => 'wpdspu_',
		'plugin_version'    => '1.3.0',
	)
);

if ( ! class_exists( 'WP_Display_Post_User_Data' ) ) {

	class WP_Display_Post_User_Data {

		private static $instance = false;

		private function __construct() {

		}

		function init() {
			add_shortcode( 'vg_display_data', array( $this, 'display_object_data_shortcode' ) );
		}

		function _find_object_id( $object_id, $data_source ) {
			$object_id_parts = explode( ':', $object_id );

			if ( count( $object_id_parts ) == 2 ) {
				if ( $data_source === 'post_meta' || $data_source === 'post_data' || $data_source === 'post_terms' ) {
					$matching_items = new WP_Query(
						array(
							'meta_key'       => $object_id_parts[0],
							'meta_value'     => $object_id_parts[1],
							'fields'         => 'ids',
							'posts_per_page' => 1,
							'post_type'      => 'any',
						)
					);

					if ( $matching_items->have_posts() ) {
						$object_id = current( $matching_items->posts );
					}
				} elseif ( $data_source === 'user_meta' || $data_source === 'user_data' ) {

					$matching_items = get_users(
						array(
							'meta_key'   => $object_id_parts[0],
							'meta_value' => $object_id_parts[1],
							'fields'     => 'ids',
							'number'     => 1,
						)
					);

					if ( ! empty( $matching_items ) ) {
						$object_id = current( $matching_items );
					}
				}
			}
			return $object_id;
		}

		function _get_object( $object_id, $data_source, $key, $joiner ) {
			$object = null;
			if ( $data_source === 'post_data' ) {
				$object = get_post( (int) $object_id );
			} elseif ( $data_source === 'user_data' ) {
				$object = get_user_by( 'ID', (int) $object_id );
			} elseif ( $data_source === 'post_meta' ) {
				$object = get_post_meta( (int) $object_id, $key, true );
			} elseif ( $data_source === 'user_meta' ) {
				$object = get_user_meta( (int) $object_id, $key, true );
			} elseif ( $data_source === 'post_terms' ) {
				$terms = wp_get_object_terms(
					$object_id,
					$key,
					array(
						'fields' => 'names',
					)
				);

				if ( ! is_wp_error( $terms ) ) {
					$object = implode( $joiner, $terms );
				}
			}

			return $object;
		}

		function _apply_flag_to_value( $flag, $out, $key ) {
			if ( $flag === 'file_url' && is_numeric( $out ) ) {
				$source = wp_get_attachment_url( $out );
				if ( ! empty( $source ) ) {
					$out = $source;
				}
			} elseif ( $flag === 'image_tag' && is_numeric( $out ) ) {
				$out = wp_get_attachment_image( $out, 'full' );
			} elseif ( $flag === 'term_name' ) {
				$term_names = array();

				if ( is_string( $out ) && strpos( $out, ',' ) !== false ) {
					$out = explode( ',', $out );
				}
				if ( is_array( $out ) ) {
					foreach ( $out as $term_id ) {
						$term         = get_term_by( 'id', $term_id, $key );
						$term_names[] = $term->name;
					}
					$out = implode( ', ', array_filter( $term_names ) );
				} else {
					$term = get_term_by( 'id', $out, $key );
					$out  = $term->name;
				}
			}
			return $out;
		}

		/**
		 * Is acf plugin active
		 * @return boolean
		 */
		function is_acf_plugin_active() {
			return function_exists( 'acf_get_field_groups' ) || class_exists( 'ACF' );
		}

		function _get_acf_value( $key, $data_source, $object_id, $default ) {
			$acf_field = acf_maybe_get_field( $key, $data_source === 'post_meta' ? $object_id : 'user_' . $object_id );
			if ( ! $acf_field ) {
				return null;
			}
			$raw_value = get_field( $key, $data_source === 'post_meta' ? $object_id : 'user_' . $object_id, false );
			$value     = get_field( $key, $data_source === 'post_meta' ? $object_id : 'user_' . $object_id );
			if ( ! empty( $value ) ) {
				if ( $acf_field['type'] === 'user' ) {
					$user = get_user_by( $raw_value, 'ID' );
					$out  = is_object( $user ) && $user->display_name ? $user->display_name : implode( ' ', array_filter( array( get_user_meta( $object_id, 'first_name', true ), get_user_meta( $object_id, 'last_name', true ) ) ) );
				} elseif ( $acf_field['type'] === 'image' ) {
					$out = wp_get_attachment_image( $raw_value, 'full' );
				} elseif ( $acf_field['type'] === 'taxonomy' ) {
					$term_names = array();
					if ( ! is_array( $raw_value ) ) {
						$raw_value = array( $raw_value );
					}
					foreach ( $raw_value as $term_id ) {
						$term         = get_term_by( 'term_id', $term_id, $acf_field['taxonomy'] );
						$term_names[] = $term->name;
					}
					$out = implode( ', ', $term_names );
				} elseif ( $acf_field['type'] === 'select' ) {
					$out = $acf_field['choices'][ $raw_value ];
				} elseif ( $acf_field['type'] === 'page_link' ) {
					$out = $value;
				} elseif ( $acf_field['type'] === 'post_object' ) {
					if ( ! is_array( $value ) ) {
						$value = array( $value );
					}

					$out = implode( ', ', wp_list_pluck( $value, 'post_title' ) );
				} else {
					$out = $value;
				}
			}
			if ( empty( $out ) ) {
				$out = $default;
			}

			return $out;
		}

		function display_object_data_shortcode( $atts = array(), $content = '' ) {
			extract(
				wp_parse_args(
					$atts,
					array(
						'object_id'      => 'current', // current = current post id, query string key if object_id_type=query_string, key:value if object_id_type=find
						'object_id_type' => '', // query_string, find
						'data_source'    => 'post_meta', // post_data, post_meta, user_data, user_meta, post_terms
						'key'            => '', // field key
						'template'       => '{{var}}',
						'default'        => '', // default value
						'joiner'         => ' ', // if value is array, join using this
						'flag'           => '', // file_url || image tag
						'wpautop'        => '',
						'do_shortcodes'  => '',
					)
				)
			);

			$allowed_keys = array_map( 'trim', explode( ',', get_option( 'wpsdd_whitelisted_keys', '' ) ) );
			if ( empty( $allowed_keys ) || empty( $key ) || ! in_array( $key, $allowed_keys, true ) ) {
				return current_user_can( 'manage_options' ) ? '<p>Please whitelist the key used in this shortcode in our settings page for security reasons.</p>' : '';
			}

			if ( $object_id_type === 'query_string' && ! empty( $object_id ) ) {
				$object_id = ( isset( $_GET[ $object_id ] ) ) ? (int) $_GET[ $object_id ] : false;
			}

			if ( $object_id === 'current' ) {
				if ( strpos( $data_source, 'post' ) !== false ) {
					global $post;
					$object_id = $post->ID;
				} else {
					$object_id = get_current_user_id();
				}
			}

			if ( $object_id_type === 'find' && ! empty( $object_id ) ) {
				$object_id = $this->_find_object_id( $object_id, $data_source );
			}

			$out = '';

			// Only allow to display data of the current user
			$is_user_field = in_array( $data_source, array( 'user_meta', 'user_data' ), true );
			if ( $is_user_field && ! is_user_logged_in() || $is_user_field && get_current_user_id() !== (int) $object_id || strpos( $key, 'user_pass' ) !== false ) {
				return $out;
			}

			if ( ! $object_id || ! $key ) {
				return $out;
			}
			if ( $this->is_acf_plugin_active() && is_numeric( $object_id ) && in_array( $data_source, array( 'post_meta', 'user_meta' ), true ) ) {
				$acf_field = acf_maybe_get_field( $key, $data_source === 'post_meta' ? $object_id : 'user_' . $object_id );
				if ( $acf_field ) {
					$out = $this->_get_acf_value( $key, $data_source, $object_id, $default );
					return wp_kses_post( $out );
				}
			}

			if ( strpos( $key, ',' ) !== false ) {
				$keys = explode( ',', $key );
				$data = array();

				foreach ( $keys as $single_key ) {
					$data[] = do_shortcode( '[vg_display_data object_id="' . $object_id . '" data_source="' . $data_source . '" key="' . $single_key . '"]' );
				}

				$out = implode( $joiner, array_filter( $data ) );
			} else {

				$object = $this->_get_object( $object_id, $data_source, $key, $joiner );

				if ( ! empty( $object ) ) {
					if ( $data_source === 'user_data' && $key === 'roles' ) {
						if ( ! function_exists( 'get_editable_roles' ) ) {
							require_once ABSPATH . 'wp-admin/includes/user.php';
						}
						$editable_roles = get_editable_roles();
						$out            = array();
						foreach ( $object->roles as $role_key ) {
							if ( isset( $editable_roles[ $role_key ] ) ) {
								$out[] = $editable_roles[ $role_key ]['name'];
							}
						}
					} else {
						if ( $data_source === 'post_data' && isset( $object->$key ) ) {
							$out = $object->$key;
						} elseif ( $data_source === 'user_data' && isset( $object->data->$key ) ) {
							$out = $object->data->$key;
						} elseif ( ! is_object( $object ) ) {
							$out = $object;
						}
					}
				}
			}

			if ( empty( $out ) && ! empty( $default ) ) {
				$out = $default;
			}

			if ( $flag && ! empty( $out ) ) {
				$out = $this->_apply_flag_to_value( $flag, $out, $key );
			}
			if ( is_array( $out ) && ! empty( $out ) ) {
				$out = implode( $joiner, $out );
			}

			if ( $wpautop && ! empty( $out ) ) {
				$out = wpautop( $out );
			}
			if ( $do_shortcodes && ! empty( $out ) ) {
				$out = do_shortcode( $out );
			}
			if ( ! empty( $template ) && ! empty( $out ) ) {
				$out = str_replace( '{{var}}', $out, $template );
			}

			$out = wp_kses_post( $out );

			return $out;
		}

		/**
		 * Creates or returns an instance of this class.
		 */
		static function get_instance() {
			if ( null == self::$instance ) {
				self::$instance = new WP_Display_Post_User_Data();
				self::$instance->init();
			}
			return self::$instance;
		}

		function __set( $name, $value ) {
			$this->$name = $value;
		}

		function __get( $name ) {
			return $this->$name;
		}

	}

}

if ( ! function_exists( 'WP_Display_Post_User_Data_Obj' ) ) {

	function WP_Display_Post_User_Data_Obj() {
		return WP_Display_Post_User_Data::get_instance();
	}
}
WP_Display_Post_User_Data_Obj();
