<?php

if ( ! class_exists( 'WPSDD_Shortcode_Settings' ) ) {

	class WPSDD_Shortcode_Settings {

		private static $instance          = null;
		private $slug_settings_page       = 'wpsdd_settings';
		private $slug_settings_group_name = 'vgce_settings_fields';
		private $form_section_id          = 'form_shortcode_settings';

		public function init() {
			add_action( 'admin_init', array( $this, 'add_fields_to_settings_pages' ) );
			add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		}

		public function add_fields_to_settings_pages() {
			// Create section.
			add_settings_section(
				$this->form_section_id,
				'',
				'',
				$this->slug_settings_page
			);

			// Register fields.
			register_setting(
				$this->slug_settings_group_name,
				'wpsdd_whitelisted_keys',
				array( 'sanitize_callback' => 'sanitize_text_field' )
			);

			// Add field.
			add_settings_field(
				'wpsdd_whitelisted_keys',
				__( 'Allowed keys', 'wpsdd' ),
				array( $this, 'print_allowed_keys_field' ),
				$this->slug_settings_page,
				$this->form_section_id,
				array(
					'label_for' => 'wpsdd-allowed-keys',
				)
			);
		}

		public function print_allowed_keys_field() {
			$allowed_keys = get_option( 'wpsdd_whitelisted_keys', '' );
			?>
			<input type="text" name="wpsdd_whitelisted_keys" id="wpsdd-allowed-keys" value="<?php echo esc_attr( $allowed_keys ); ?>">
			<p class="description"><?php esc_html_e( 'Enter the allowed keys that can be used in the shortcode separated with commas. Any field key not found in this list will not return any value.', 'wpsdd' ); ?></p>
			<?php
		}

		public function add_settings_page() {
			add_submenu_page(
				'options-general.php',
				__( 'Shortcode to display post and user data', 'wpsdd' ),
				__( 'Shortcode to display post and user data', 'wpsdd' ),
				'manage_options',
				$this->slug_settings_page,
				array( $this, 'render_settings_page' )
			);
		}

		public function render_settings_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			?>
			<div class="wrap">
				<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
				<form method="post" action="options.php">
				<?php
					settings_fields( $this->slug_settings_group_name ); // settings group name.
					do_settings_sections( $this->slug_settings_page );
					submit_button();
				?>
				</form>
			</div>
				<?php
		}

		/**
		 * Creates or returns an instance of this class.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new WPSDD_Shortcode_Settings();
				self::$instance->init();
			}
			return self::$instance;
		}
	}

}

WPSDD_Shortcode_Settings::get_instance();
