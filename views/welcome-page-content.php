<?php
$textname = 'vg_shortcode_display_data';
?>
<p><?php _e( 'Thank you for installing our plugin. This plugin is completely free.', $textname ); ?></p>

<?php
$steps              = array();
$steps['read_docs'] = '<p>' . sprintf( __( 'You can read our documentation to see examples of the shortcodes and available parameters:  <a href="%s" class="button" target="_blank">Read documentation</a>', $textname ), 'https://wordpress.org/plugins/shortcode-to-display-post-and-user-data/' ) . '</p>';

$steps['settings'] = '<p>' . sprintf( __( 'Please whitelist every field key in our settings page:  <a href="%s" class="button" target="_blank">Settings page</a>', $textname ), esc_url( admin_url( 'options-general.php?page=wpsdd_settings' ) ) ) . '</p>';

$steps = apply_filters( 'vg_sheet_editor/wpds/welcome_steps', $steps );

if ( ! empty( $steps ) ) {
	echo '<ol class="steps">';
	foreach ( $steps as $key => $step_content ) {
		?>
		<li><?php echo $step_content; ?></li>		
		<?php
	}

	echo '</ol>';
}
