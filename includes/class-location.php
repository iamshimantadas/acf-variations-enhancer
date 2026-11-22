<?php
defined( 'ABSPATH' ) || exit;

/**
 * ACF location rule: Product Variation == All variations.
 */
class ACFVE_Location_Variation extends ACF_Location {

	public function initialize() {
		$this->name        = 'product_variation';
		$this->label       = __( 'Product Variation', 'acf-variations-enhancer' );
		$this->category    = 'post';
		$this->object_type = 'post';
	}

	public function match( $rule, $screen, $field_group ) {
		if ( ! isset( $screen['product_variation'] ) ) {
			return false;
		}
		return $this->compare_to_rule( $screen['product_variation'], $rule );
	}

	public function get_values( $rule ) {
		return array(
			'all' => __( 'All variations', 'acf-variations-enhancer' ),
		);
	}
}
