<?php
defined( 'ABSPATH' ) || exit;

/**
 * WP-CLI self test.
 *
 * Usage:  wp acfve test --user=admin
 *
 * Creates a temporary variable product + variation + ACF field group (many field types),
 * simulates the WooCommerce variation save, reads the values back, then cleans up.
 */
class ACFVE_SelfTest {

	private $pass = 0;
	private $fail = 0;

	private function check( $label, $expected, $actual ) {
		if ( $expected == $actual ) { // loose on purpose (ACF returns strings/ints)
			$this->pass++;
			WP_CLI::log( "  [PASS] $label" );
		} else {
			$this->fail++;
			WP_CLI::log( "  [FAIL] $label  expected=" . wp_json_encode( $expected ) . " actual=" . wp_json_encode( $actual ) );
		}
	}

	public function __invoke( $args, $assoc_args ) {
		if ( ! current_user_can( 'edit_products' ) ) {
			WP_CLI::error( 'Run with an admin user: wp acfve test --user=admin' );
		}

		// 1. Field group with several field types, located on "Product Variation == All variations".
		$group = array(
			'key'      => 'group_acfve_selftest',
			'title'    => 'ACFVE Self Test',
			'location' => array( array( array( 'param' => 'product_variation', 'operator' => '==', 'value' => 'all' ) ) ),
			'fields'   => array(
				array( 'key' => 'field_acfve_text',     'label' => 'Text',     'name' => 'acfve_text',     'type' => 'text' ),
				array( 'key' => 'field_acfve_textarea', 'label' => 'Textarea', 'name' => 'acfve_textarea', 'type' => 'textarea' ),
				array( 'key' => 'field_acfve_number',   'label' => 'Number',   'name' => 'acfve_number',   'type' => 'number' ),
				array( 'key' => 'field_acfve_select',   'label' => 'Select',   'name' => 'acfve_select',   'type' => 'select', 'choices' => array( 'a' => 'A', 'b' => 'B' ) ),
				array( 'key' => 'field_acfve_bool',     'label' => 'Bool',     'name' => 'acfve_bool',     'type' => 'true_false' ),
				array( 'key' => 'field_acfve_color',    'label' => 'Color',    'name' => 'acfve_color',    'type' => 'color_picker' ),
				array(
					'key' => 'field_acfve_repeater', 'label' => 'Repeater', 'name' => 'acfve_repeater', 'type' => 'repeater',
					'layout' => 'table', 'button_label' => 'Add Row',
					'sub_fields' => array(
						array( 'key' => 'field_acfve_rep_label', 'label' => 'Label', 'name' => 'label', 'type' => 'text' ),
						array( 'key' => 'field_acfve_rep_qty',   'label' => 'Qty',   'name' => 'qty',   'type' => 'number' ),
					),
				),
			),
		);

		WP_CLI::log( 'Importing test field group...' );
		acf_import_field_group( $group );

		// 2. Temporary variable product + variation.
		$product = new WC_Product_Variable();
		$product->set_name( 'ACFVE temp product' );
		$product->save();

		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $product->get_id() );
		$variation->set_regular_price( '10' );
		$variation->save();
		$vid = $variation->get_id();
		WP_CLI::log( "Created product #{$product->get_id()} / variation #$vid" );

		// 3. Location rule picks up the group for variations.
		$groups = ACFVE_Plugin::get_groups( $vid );
		$keys   = wp_list_pluck( $groups, 'key' );
		$this->check( 'location rule matches variation', true, in_array( 'group_acfve_selftest', $keys, true ) );

		// 4. Render does not fatal and outputs prefixed input names.
		ob_start();
		ACFVE_Plugin::render_fields( 0, array(), get_post( $vid ) );
		$html = ob_get_clean();
		$this->check( 'render outputs prefixed inputs', true, false !== strpos( $html, 'acfve_variation[' . $vid . '][field_acfve_text]' ) );

		// 5. Simulate the POST WooCommerce sends, then run the save handler.
		$_POST[ ACFVE_Plugin::POST_KEY ] = array(
			$vid => array(
				'field_acfve_text'     => 'Hello variation',
				'field_acfve_textarea' => "Line 1\nLine 2",
				'field_acfve_number'   => '42',
				'field_acfve_select'   => 'b',
				'field_acfve_bool'     => '1',
				'field_acfve_color'    => '#ff0000',
				'field_acfve_repeater' => array(
					'row-0' => array( 'field_acfve_rep_label' => 'Small', 'field_acfve_rep_qty' => '3' ),
					'row-1' => array( 'field_acfve_rep_label' => 'Large', 'field_acfve_rep_qty' => '9' ),
				),
			),
		);
		ACFVE_Plugin::save_fields( $vid, 0 );

		// 6. Read back via raw meta and via get_field().
		$this->check( 'meta acfve_text saved',       'Hello variation', get_post_meta( $vid, 'acfve_text', true ) );
		$this->check( 'meta field-key reference',    'field_acfve_text', get_post_meta( $vid, '_acfve_text', true ) );
		$this->check( 'get_field text',              'Hello variation', get_field( 'acfve_text', $vid ) );
		$this->check( 'get_field textarea',          "Line 1\nLine 2",  get_field( 'acfve_textarea', $vid ) );
		$this->check( 'get_field number',            42,                get_field( 'acfve_number', $vid ) );
		$this->check( 'get_field select',            'b',               get_field( 'acfve_select', $vid ) );
		$this->check( 'get_field true_false',        1,                 (int) get_field( 'acfve_bool', $vid ) );
		$this->check( 'get_field color_picker',      '#ff0000',         get_field( 'acfve_color', $vid ) );
		$rows = get_field( 'acfve_repeater', $vid );
		$this->check( 'repeater row count',          2,                 is_array( $rows ) ? count( $rows ) : 0 );
		$this->check( 'repeater row 2 label',        'Large',           isset( $rows[1]['label'] ) ? $rows[1]['label'] : null );
		$this->check( 'repeater row 2 qty',          9,                 isset( $rows[1]['qty'] ) ? $rows[1]['qty'] : null );

		// 7. Cleanup.
		unset( $_POST[ ACFVE_Plugin::POST_KEY ] );
		wp_delete_post( $vid, true );
		wp_delete_post( $product->get_id(), true );
		$existing = acf_get_field_group( 'group_acfve_selftest' );
		if ( $existing ) {
			acf_delete_field_group( $existing['ID'] );
		}
		WP_CLI::log( 'Cleaned up test data.' );

		if ( $this->fail ) {
			WP_CLI::error( "{$this->fail} check(s) failed, {$this->pass} passed." );
		}
		WP_CLI::success( "All {$this->pass} checks passed." );
	}
}
