<?php

/**
 * Product Handler class for managing custom fields and product types
 */
class WALP_Product_Handler {
	public function __construct() {
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_custom_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_custom_fields' ) );
		add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'display_input_fields' ) );
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'display_hidden_fields' ), 5 );
		add_action( 'woocommerce_before_add_to_cart_quantity', array( $this, 'hide_standard_quantity' ) );
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'modify_add_to_cart_button' ) );
		add_filter( 'woocommerce_get_price_html', array( $this, 'modify_price_html' ), 10, 2 );
		add_filter( 'woocommerce_get_availability', array( $this, 'modify_stock_status' ), 10, 2 );
		add_filter( 'iworks_omnibus_message_template', array( $this, 'add_unit_to_omnibus_message_template' ), 10, 3 );
	}

	/**
	 * Get WooCommerce currency settings
	 */
	private function get_woocommerce_currency_settings() {
		return array(
			'symbol' => get_woocommerce_currency_symbol(),
			'position' => get_option( 'woocommerce_currency_pos', 'left' ),
			'decimal_separator' => wc_get_price_decimal_separator(),
			'thousand_separator' => wc_get_price_thousand_separator(),
			'decimals' => wc_get_price_decimals()
		);
	}

	/**
	 * Format price according to WooCommerce settings
	 */
	private function format_price( $price, $currency_settings ) {
		return number_format(
			$price,
			$currency_settings['decimals'],
			$currency_settings['decimal_separator'],
			$currency_settings['thousand_separator']
		);
	}

	/**
	 * Format currency display based on position
	 */
	private function format_currency_display( $formatted_price, $currency_symbol, $currency_position ) {
		switch ( $currency_position ) {
			case 'left':
				return $currency_symbol . $formatted_price;
			case 'left_space':
				return $currency_symbol . ' ' . $formatted_price;
			case 'right':
				return $formatted_price . $currency_symbol;
			case 'right_space':
			default:
				return $formatted_price . ' ' . $currency_symbol;
		}
	}

	/**
	 * Generate price display HTML with sale formatting
	 */
	private function generate_price_display_html( $price, $regular_price, $unit, $currency_settings ) {
		$formatted_price = $this->format_price( $price, $currency_settings );
		$currency_display = $this->format_currency_display(
			$formatted_price,
			$currency_settings['symbol'],
			$currency_settings['position']
		);

		$html = '';
		if ( $regular_price && $regular_price > $price ) {
			$formatted_regular = $this->format_price( $regular_price, $currency_settings );
			$regular_display = $this->format_currency_display(
				$formatted_regular,
				$currency_settings['symbol'],
				$currency_settings['position']
			);
			$html .= '<del><span class="woocommerce-Price-amount amount walp_regular_old_price"><bdi>' . $regular_display . $unit . '</bdi></span></del> ';
			$html .= '<span class="woocommerce-Price-amount amount walp_sale_price"><bdi>' . $currency_display . $unit . '</bdi></span>';
		} else {
			$html .= '<span class="woocommerce-Price-amount amount walp_regular_price"><bdi>' . $currency_display . $unit . '</bdi></span>';
		}

		return $html;
	}

	/**
	 * Generate price HTML for area products
	 */
	private function generate_area_price_html( $price_per_m2, $box_price, $quantity_in_box, $currency_settings, $product_id, $regular_price_per_m2 = null, $regular_box_price = null ) {
		$unit = '/' . $this->get_unit_string( $product_id );
		$package_unit = '/' . __( 'pkg', 'woocommerce-area-length-plugin' );

		$html = '<div class="walp-price-container">';

		$html .= '<div class="walp-price-per-m2">';
		$html .= $this->generate_price_display_html( $price_per_m2, $regular_price_per_m2, $unit, $currency_settings );
		$html .= '</div>';

		$html .= '<div class="walp-price-per-package">';
		$html .= $this->generate_price_display_html( $box_price, $regular_box_price, $package_unit, $currency_settings );
		$html .= '</div>';

		if ( $quantity_in_box ) {
			$html .= '<div class="walp-quantity-info">';
			$html .= sprintf( __( '%d pcs. in package', 'woocommerce-area-length-plugin' ), $quantity_in_box );
			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate price HTML for length products
	 */
	private function generate_length_price_html( $price, $currency_settings, $regular_price, $product_id ) {
		$unit = $this->get_unit_string( $product_id );
		return '<div class="walp-price-container"><div class="walp-price-per-piece">' . $this->generate_price_display_html( $price, $regular_price, $unit, $currency_settings ) . '</div></div>';
	}

	private function generate_standard_price_html( $price, $regular_price, $price_suffix, $currency_settings ) {
		return '<div class="walp-price-container">' .
			$this->generate_price_display_html( $price, $regular_price, $price_suffix, $currency_settings ) .
			'</div>';
	}

	/**
	 * Modify price HTML for area and length products
	 */
	public function modify_price_html( $price_html, $product ) {
		if ( is_admin() ) {
			return $price_html;
		}

		$product_id = $product->get_id();

		$product_type = $this->get_product_type_meta( $product_id );
		$meters_per_box = $this->get_meters_per_box_meta( $product_id );

		$price = $product->get_price();
		$regular_price = $product->get_regular_price();
		$price_suffix = $this->get_product_unit( $product_id, $product_type );
		$currency_settings = $this->get_woocommerce_currency_settings();

		if ( ! $meters_per_box ) {
			return $this->generate_standard_price_html( $price, $regular_price, $price_suffix, $currency_settings );
		}

		if ( $product_type === 'area' ) {
			$price_per_m2 = $product->get_price() / $meters_per_box;
			$quantity_in_box = $this->get_quantity_in_box( $product );

			$regular_price_per_m2 = $regular_price ? $regular_price / $meters_per_box : null;

			return $this->generate_area_price_html( $price_per_m2, $price, $quantity_in_box, $currency_settings, $product_id, $regular_price_per_m2, $regular_price );
		}

		if ( $product_type === 'length' ) {
			return $this->generate_length_price_html( $product->get_price(), $currency_settings, $regular_price, $product_id );
		}

		return $this->generate_standard_price_html( $price, $regular_price, $price_suffix, $currency_settings );
	}

	/**
	 * Remove default WooCommerce suffix for products with custom suffixes
	 * and add it for products without custom suffixes (regular products)
	 */
	public function remove_suffix_for_custom_products( $html, $product, $price, $qty ) {
		if ( is_admin() ) {
			return $html;
		}

		$product_id = $product->get_id();
		$custom_unit = $this->get_custom_unit_meta( $product_id );

		// If custom unit is set, remove the default WooCommerce suffix
		if ( ! empty( $custom_unit ) ) {
			return '';
		}

		// Otherwise, return the default suffix
		return $html;
	}

	/**
	 * Get product type meta
	 */
	private function get_product_type_meta( $product_id ) {
		return get_post_meta( $product_id, '_walp_product_type', true );
	}

	/**
	 * Get meters per box meta
	 */
	private function get_meters_per_box_meta( $product_id ) {
		return abs( floatval( get_post_meta( $product_id, '_walp_meters_per_box', true ) ) );
	}

	/**
	 * Get custom unit meta
	 */
	private function get_custom_unit_meta( $product_id ) {
		return trim( get_post_meta( $product_id, '_walp_custom_unit', true ) );
	}

	/**
	 * Get quantity in box from attribute
	 */
	private function get_quantity_in_box( $product ) {
		$terms = wc_get_product_terms( $product->get_id(), 'pa_ilosc-sztuk-w-opakowaniu', array( 'fields' => 'names' ) );
		return ! empty( $terms ) ? intval( $terms[0] ) : 0;
	}

	/**
	 * Get unit for product display
	 */
	private function get_product_unit( $product_id, $product_type ) {
		$unit = $this->get_unit_string( $product_id );
		return $product_type === 'length' ? $unit : '/' . $unit;
	}

	/**
	 * Unified method to get unit string
	 */
	private function get_unit_string( $product_id ) {
		$product_type = $this->get_product_type_meta( $product_id );
		$custom_unit = $this->get_custom_unit_meta( $product_id );

		if ( ! empty( $custom_unit ) ) {
			return $custom_unit;
		}

		switch ( $product_type ) {
			case 'area':
				return __( 'm²', 'woocommerce-area-length-plugin' );
			case 'length':
				return __( 'pcs', 'woocommerce-area-length-plugin' );
			default:
				return '';
		}
	}

	/**
	 * Add custom fields to product edit page
	 */
	public function add_custom_fields() {
		echo '<div class="options_group">';

		// Product type dropdown
		woocommerce_wp_select( array(
			'id' => '_walp_product_type',
			'label' => __( 'Measurement Type', 'woocommerce-area-length-plugin' ),
			'options' => array(
				'standard' => __( 'Standard', 'woocommerce-area-length-plugin' ),
				'area' => __( 'Area', 'woocommerce-area-length-plugin' ),
				'length' => __( 'Length', 'woocommerce-area-length-plugin' )
			),
			'desc_tip' => true,
			'description' => __( 'Select the measurement type for this product.', 'woocommerce-area-length-plugin' )
		) );

		// Meters per box
		woocommerce_wp_text_input( array(
			'id' => '_walp_meters_per_box',
			'label' => __( 'Meters per package', 'woocommerce-area-length-plugin' ),
			'desc_tip' => true,
			'description' => __( 'Enter meters per package for quantity calculations.', 'woocommerce-area-length-plugin' ),
			'type' => 'number',
			'custom_attributes' => array(
				'step' => '0.01',
				'min' => '0'
			)
		) );

		// Custom unit text
		woocommerce_wp_text_input( array(
			'id' => '_walp_custom_unit',
			'label' => __( 'Custom Unit', 'woocommerce-area-length-plugin' ),
			'desc_tip' => true,
			'description' => __( 'Enter custom unit text (e.g., "kg", "liters", "boxes"). If left empty, default units will be used.', 'woocommerce-area-length-plugin' ),
			'placeholder' => __( 'e.g., kg, liters, boxes', 'woocommerce-area-length-plugin' )
		) );

		echo '</div>';
	}

	/**
	 * Save custom fields
	 */
	public function save_custom_fields( $post_id ) {
		$product_type = isset( $_POST['_walp_product_type'] ) ? sanitize_text_field( $_POST['_walp_product_type'] ) : 'standard';
		$meters_per_box = isset( $_POST['_walp_meters_per_box'] ) ? abs( floatval( $_POST['_walp_meters_per_box'] ) ) : 0;
		$custom_unit = isset( $_POST['_walp_custom_unit'] ) ? sanitize_text_field( $_POST['_walp_custom_unit'] ) : '';

		update_post_meta( $post_id, '_walp_product_type', $product_type );
		update_post_meta( $post_id, '_walp_meters_per_box', $meters_per_box );
		update_post_meta( $post_id, '_walp_custom_unit', $custom_unit );
	}

	/**
	 * Helper function to render input field with increment/decrement buttons
	 */
	private function render_input_field( $id, $label, $required = false ) {
		echo '<div class="walp-field-group">';
		echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		echo '<div class="walp-input-group">';
		echo '<button type="button" class="walp-decrement" data-target="' . esc_attr( $id ) . '">-</button>';
		echo '<input type="number" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" placeholder="0"' . ( $required ? ' required' : '' ) . '>';
		echo '<button type="button" class="walp-increment" data-target="' . esc_attr( $id ) . '">+</button>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Helper function to render stat display
	 */
	private function render_stat( $label, $value_id, $hide = false ) {
		$style = $hide ? ' style="display: none;"' : '';
		echo '<div class="walp-stat-group"' . $style . '>';
		echo '<div class="walp-stat">';
		echo '<span class="walp-stat-label">' . esc_html( $label ) . '</span>';
		echo '<span class="walp-stat-value" id="' . esc_attr( $value_id ) . '">-</span>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render dimensions input section for area products
	 */
	private function render_area_dimensions_section() {
		echo '<div class="walp-section walp-section-collapsible">';
		echo '<h4 class="walp-section-title walp-section-header">' . __( 'Room Dimensions', 'woocommerce-area-length-plugin' ) . '<span class="walp-toggle-arrow"></span></h4>';
		echo '<div class="walp-section-content">';

		echo '<div class="walp-row">';

		$this->render_input_field( 'walp_length', __( 'Length (meters)', 'woocommerce-area-length-plugin' ) );
		$this->render_input_field( 'walp_width', __( 'Width (meters)', 'woocommerce-area-length-plugin' ) );

		$this->render_safety_margin_dropdown();

		echo '</div>'; // .walp-row
		echo '</div>'; // .walp-section-content
		echo '</div>'; // .walp-section
	}

	/**
	 * Render safety margin dropdown
	 */
	private function render_safety_margin_dropdown() {
		echo '<div class="walp-field-group">';
		echo '<label for="walp_margin">' . __( 'Safety Margin', 'woocommerce-area-length-plugin' ) . '</label>';
		echo '<select id="walp_margin" name="walp_margin">';
		$margins = array(
			0 => __( '0% (No margin)', 'woocommerce-area-length-plugin' ),
			5 => __( '5% (Minimal)', 'woocommerce-area-length-plugin' ),
			10 => __( '10% (Standard)', 'woocommerce-area-length-plugin' ),
		);
		foreach ( $margins as $value => $label ) {
			$selected = ( $value === 10 ) ? ' selected' : '';
			echo '<option value="' . esc_attr( $value ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '</div>';
	}

	/**
	 * Render calculated fields section
	 */
	private function render_calculated_fields_section( $product_type ) {
		echo '<div class="walp-section">';
		echo '<h4 class="walp-section-title">' . __( 'Required Quantity', 'woocommerce-area-length-plugin' ) . '</h4>';
		echo '<div class="walp-row walp-calculated-row">';

		if ( $product_type === 'area' ) {
			$this->render_input_field( 'walp_calculated_value', __( 'Total Area (m²)', 'woocommerce-area-length-plugin' ) );
			$this->render_input_field( 'walp_calculated_qty', __( 'Packages Needed', 'woocommerce-area-length-plugin' ), true );
		} else {
			$this->render_input_field( 'walp_calculated_value', __( 'Length (meters)', 'woocommerce-area-length-plugin' ) );
			$this->render_input_field( 'walp_calculated_qty', __( 'Pieces Needed', 'woocommerce-area-length-plugin' ), true );
		}

		echo '</div>'; // .walp-row
		echo '</div>'; // .walp-section
	}

	/**
	 * Render summary section
	 */
	private function render_summary_section( $product_type ) {
		echo '<div class="walp-summary">';
		echo '<h4 class="walp-section-title">' . __( 'Summary', 'woocommerce-area-length-plugin' ) . '</h4>';
		echo '<div class="walp-stats">';

		$this->render_stat( __( 'Total:', 'woocommerce-area-length-plugin' ), 'walp_total_value' );
		$boxes_label = $product_type === 'area' ? __( 'Packages:', 'woocommerce-area-length-plugin' ) : __( 'Pieces:', 'woocommerce-area-length-plugin' );
		$this->render_stat( $boxes_label, 'walp_boxes_needed' );

		$this->render_stat( __( 'Final Price:', 'woocommerce-area-length-plugin' ), 'walp_final_price' );

		echo '</div>';
		echo '</div>'; // .walp-summary
	}

	public function display_input_fields() {
		global $product;

		$product_id = $product->get_id();
		$product_type = $this->get_product_type_meta( $product_id );
		$meters_per_box = $this->get_meters_per_box_meta( $product_id );

		if ( ( $product_type === 'area' || $product_type === 'length' ) && $meters_per_box > 0 ) {
			echo '<div class="walp-input-fields">';

			// Dimensions input section (only for area)
			if ( $product_type === 'area' ) {
				$this->render_area_dimensions_section();
			}

			// Calculated fields section
			$this->render_calculated_fields_section( $product_type );

			// Summary section
			$this->render_summary_section( $product_type );

			echo '</div>'; // .walp-input-fields
		}
	}

	/**
	 * Display hidden fields for form submission
	 */
	public function display_hidden_fields() {
		global $product;

		$product_id = $product->get_id();
		$product_type = $this->get_product_type_meta( $product_id );
		$meters_per_box = $this->get_meters_per_box_meta( $product_id );

		if ( ( $product_type === 'area' || $product_type === 'length' ) && $meters_per_box > 0 ) {
			// Hidden fields
			echo '<input type="hidden" name="walp_qty" id="walp_qty">';
			echo '<input type="hidden" name="walp_product_type" id="walp_product_type" value="' . esc_attr( $product_type ) . '">';
			echo '<input type="hidden" name="walp_meters_per_box" id="walp_meters_per_box" value="' . esc_attr( $meters_per_box ) . '">';
			echo '<input type="hidden" name="walp_product_price" id="walp_product_price" value="' . esc_attr( $product->get_price() ) . '">';
		}
	}

	/**
	 * Hide standard quantity input for calculated products
	 */
	public function hide_standard_quantity() {
		global $product;

		$product_id = $product->get_id();
		$product_type = $this->get_product_type_meta( $product_id );
		$meters_per_box = $this->get_meters_per_box_meta( $product_id );

		if ( ( $product_type === 'area' || $product_type === 'length' ) && $meters_per_box > 0 ) {
			echo '<style>
                .summary-woodmart-layout-product .quantity,
				.summary.entry-summary .quantity {
					display: none !important;
				}
			</style>';
		}
	}

	/**
	 * Modify add to cart button for calculated products
	 */
	public function modify_add_to_cart_button() {
		global $product;

		$product_id = $product->get_id();
		$product_type = $this->get_product_type_meta( $product_id );
		$meters_per_box = $this->get_meters_per_box_meta( $product_id );

		if ( ( $product_type === 'area' || $product_type === 'length' ) && $meters_per_box > 0 ) {
			echo '<style>
				.single_add_to_cart_button {
					width: 100% !important;
					padding: 16px 24px !important;
					font-size: 16px !important;
					font-weight: 600 !important;
					margin-top: 16px !important;
				}
			</style>';
		}
	}

	/**
	 * Get product type
	 */
	public static function get_product_type( $product_id ) {
		return get_post_meta( $product_id, '_walp_product_type', true );
	}

	/**
	 * Get meters per box
	 */
	public static function get_meters_per_box( $product_id ) {
		return floatval( get_post_meta( $product_id, '_walp_meters_per_box', true ) );
	}

	/**
	 * Modify stock status display
	 */
	public function modify_stock_status( $availability, $product ) {
		if ( ! is_admin() && $product->is_in_stock() ) {
			$product_id = $product->get_id();
			$product_type = $this->get_product_type_meta( $product_id );
			$unit = '';
			if ( $product_type === 'area' ) {
				$unit = __( 'pkg', 'woocommerce-area-length-plugin' );
			} else {
				$unit = $this->get_unit_string( $product_id );
			}
			
			if ( ! empty( $unit ) ) {
				$stock_quantity = $product->get_stock_quantity();
				if ( $stock_quantity > 1000 ) {
					$availability['availability'] = sprintf( __( '1000+ %s in stock', 'woocommerce-area-length-plugin' ), $unit );
				} else {
					$availability['availability'] = sprintf( __( '%d %s in stock', 'woocommerce-area-length-plugin' ), $stock_quantity, $unit );
				}
			}
		}
		return $availability;
	}

	/**
	 * Add unit to Omnibus message template for area/length products
	 */
	public function add_unit_to_omnibus_message_template( $message, $price, $price_lowest ) {
		global $product;
		
		if ( ! $product ) {
			return $message;
		}
		
		$product_id = $product->get_id();
		$unit = '/' . $this->get_unit_string( $product_id );
		
		if ( ! empty( $unit ) ) {
			// Append the unit after the price placeholder (%2$s) in the message template
			$message = str_replace( '%2$s', '%2$s ' . $unit, $message );
		}
		
		return $message;
	}
}
