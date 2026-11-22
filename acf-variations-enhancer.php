<?php
/**
 * Plugin Name:       ACF Variations Enhancer
 * Description:       Child/add-on plugin for Advanced Custom Fields. Adds a "Product Variation" location rule so ANY ACF field type can be used on WooCommerce variations, and saves the data as variation meta.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Shimanta Das(microcodes.in)
 * License:           GPL-2.0-or-later
 * Text Domain:       acf-variations-enhancer
 */

defined( 'ABSPATH' ) || exit;

define( 'ACFVE_VERSION', '1.0.0' );
define( 'ACFVE_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACFVE_URL', plugin_dir_url( __FILE__ ) );

final class ACFVE_Plugin {

	const POST_KEY = 'acfve_variation'; // $_POST[acfve_variation][variation_id][field_key]

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'boot' ), 20 );
	}

	public static function boot() {
		if ( ! class_exists( 'ACF' ) || ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'missing_deps_notice' ) );
			return;
		}

		// Location rule: "Product Variation".
		add_action( 'acf/init', array( __CLASS__, 'register_location' ) );

		// Render fields inside each variation panel.
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'render_fields' ), 10, 3 );

		// Save (fires for ajax "Save changes" and on product save).
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_fields' ), 10, 2 );

		// Assets.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		// WP-CLI self test.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once ACFVE_DIR . 'includes/class-selftest.php';
			WP_CLI::add_command( 'acfve test', 'ACFVE_SelfTest' );
		}
	}

	public static function missing_deps_notice() {
		echo '<div class="notice notice-error"><p><strong>ACF Variations Enhancer</strong> requires Advanced Custom Fields and WooCommerce to be active.</p></div>';
	}

	public static function register_location() {
		if ( ! function_exists( 'acf_register_location_type' ) || ! class_exists( 'ACF_Location' ) ) {
			return; // Needs ACF 5.9+.
		}
		require_once ACFVE_DIR . 'includes/class-location.php';
		acf_register_location_type( 'ACFVE_Location_Variation' );
	}

	/** Field groups that apply to variations. */
	public static function get_groups( $variation_id = 0 ) {
		return acf_get_field_groups( array(
			'product_variation' => 'all',
			'post_id'           => $variation_id, // harmless; lets other rules see context
		) );
	}

	public static function render_fields( $loop, $variation_data, $variation ) {
		$variation_id = is_object( $variation ) ? (int) $variation->ID : 0;
		if ( ! $variation_id ) {
			return;
		}

		$groups = self::get_groups( $variation_id );
		if ( empty( $groups ) ) {
			return;
		}

		foreach ( $groups as $group ) {
			$fields = acf_get_fields( $group );
			if ( empty( $fields ) ) {
				continue;
			}

			echo '<div class="acfve-group acf-fields acf-form-fields -top" data-variation="' . esc_attr( $variation_id ) . '">';
			echo '<h4 class="acfve-title">' . esc_html( $group['title'] ) . '</h4>';

			foreach ( $fields as $field ) {
				// Unique input names per variation, e.g. acfve_variation[123][field_abc]
				$field['prefix'] = self::POST_KEY . '[' . $variation_id . ']';
				$field['value']  = acf_get_value( $variation_id, $field );
				acf_render_field_wrap( $field );
			}

			echo '</div>';
		}
	}

	public static function save_fields( $variation_id, $loop ) {
		$variation_id = (int) $variation_id;

		if ( ! current_user_can( 'edit_post', $variation_id ) ) {
			return;
		}

		// WooCommerce already verifies its own nonce before firing this hook.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST[ self::POST_KEY ][ $variation_id ] ) || ! is_array( $_POST[ self::POST_KEY ][ $variation_id ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
		$values = wp_unslash( $_POST[ self::POST_KEY ][ $variation_id ] );

		// ACF validates/sanitizes per field type and stores meta + field-key reference.
		acf_update_values( $values, $variation_id );
	}

	public static function enqueue( $hook ) {
		global $post;
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! $post || 'product' !== $post->post_type ) {
			return;
		}

		// Load ACF's JS/CSS and field assets (uploader, editor, date pickers, select2, etc.).
		if ( function_exists( 'acf_enqueue_scripts' ) ) {
			acf_enqueue_scripts();
		}
		if ( function_exists( 'acf_enqueue_uploader' ) ) {
			acf_enqueue_uploader();
		}
		wp_enqueue_editor();

		wp_enqueue_script(
			'acfve-admin',
			ACFVE_URL . 'assets/admin.js',
			array( 'jquery', 'acf-input' ),
			ACFVE_VERSION,
			true
		);
		wp_enqueue_style(
			'acfve-admin',
			ACFVE_URL . 'assets/admin.css',
			array( 'acf-input' ),
			ACFVE_VERSION
		);
	}
}

ACFVE_Plugin::init();
