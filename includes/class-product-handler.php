<?php

/**
 * Product Handler class for managing custom fields and product types
 */
class WALP_Product_Handler
{
    public function __construct()
    {
        add_action('woocommerce_product_options_general_product_data', array( $this, 'add_custom_fields' ));
        add_action('woocommerce_process_product_meta', array( $this, 'save_custom_fields' ));
        add_action('woocommerce_before_add_to_cart_form', array( $this, 'display_input_fields' ));
        add_action('woocommerce_before_add_to_cart_button', array( $this, 'display_hidden_fields' ), 5);
        add_action('woocommerce_before_add_to_cart_quantity', array( $this, 'hide_standard_quantity' ));
        add_action('woocommerce_after_add_to_cart_button', array( $this, 'modify_add_to_cart_button' ));
        add_filter('woocommerce_get_price_html', array( $this, 'modify_price_html' ), 10, 2);
        add_filter('woocommerce_get_availability', array( $this, 'modify_stock_status' ), 10, 2);
    }

    /**
     * Get WooCommerce currency settings
     */
    private function get_woocommerce_currency_settings()
    {
        return array(
            'symbol' => get_woocommerce_currency_symbol(),
            'position' => get_option('woocommerce_currency_pos', 'left'),
            'decimal_separator' => wc_get_price_decimal_separator(),
            'thousand_separator' => wc_get_price_thousand_separator(),
            'decimals' => wc_get_price_decimals()
        );
    }

    /**
     * Format price according to WooCommerce settings
     */
    private function format_price($price, $currency_settings)
    {
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
    private function format_currency_display($formatted_price, $currency_symbol, $currency_position)
    {
        switch ($currency_position) {
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
     * Generate price HTML for area products
     */
    private function generate_area_price_html($price_per_m2, $box_price, $quantity_in_box, $currency_settings)
    {
        $formatted_price_per_m2 = $this->format_price($price_per_m2, $currency_settings);
        $formatted_box_price = $this->format_price($box_price, $currency_settings);

        $currency_display = $this->format_currency_display(
            $formatted_price_per_m2,
            $currency_settings['symbol'],
            $currency_settings['position']
        );

        $box_currency_display = $this->format_currency_display(
            $formatted_box_price,
            $currency_settings['symbol'],
            $currency_settings['position']
        );

        $price_html = '<span class="woocommerce-Price-amount amount"><bdi>' . $currency_display . '/m²</bdi></span><br>';
        $price_html .= '<span class="walp-box-price"><bdi>' . $box_currency_display . __('/package', 'woocommerce-area-length-plugin') . '</bdi></span>';

        if ($quantity_in_box) {
            $price_html .= '<span class="walp-box-price">' . sprintf(__('%d pcs. in package', 'woocommerce-area-length-plugin'), $quantity_in_box) . '</span>';
        }

        return $price_html;
    }

    /**
     * Generate price HTML for length products
     */
    private function generate_length_price_html($price, $currency_settings)
    {
        $formatted_price = $this->format_price($price, $currency_settings);

        $currency_display = $this->format_currency_display(
            $formatted_price,
            $currency_settings['symbol'],
            $currency_settings['position']
        );

        $price_html = '<span class="woocommerce-Price-amount amount"><bdi>' . $currency_display . '/' . __('piece', 'woocommerce-area-length-plugin') . '</bdi></span>';

        return $price_html;
    }

    /**
     * Modify price HTML for area and length products
     */
    public function modify_price_html($price_html, $product)
    {
        if (!is_admin()) {
            $product_type = $this->get_product_type_meta($product->get_id());
            $meters_per_box = $this->get_meters_per_box_meta($product->get_id());

            if ($product_type === 'area' && $meters_per_box > 0) {
                $price_per_m2 = $product->get_price() / $meters_per_box;
                $box_price = $product->get_price();
                $quantity_in_box = $this->get_quantity_in_box($product);
                $currency_settings = $this->get_woocommerce_currency_settings();
                $price_html = $this->generate_area_price_html($price_per_m2, $box_price, $quantity_in_box, $currency_settings);
            } elseif ($product_type === 'length' && $meters_per_box > 0) {
                $price = $product->get_price();
                $currency_settings = $this->get_woocommerce_currency_settings();
                $price_html = $this->generate_length_price_html($price, $currency_settings);
            }
        }

        return $price_html;
    }

    /**
     * Get product type meta
     */
    private function get_product_type_meta($product_id)
    {
        return get_post_meta($product_id, '_walp_product_type', true);
    }

    /**
     * Get meters per box meta
     */
    private function get_meters_per_box_meta($product_id)
    {
        return abs(floatval(get_post_meta($product_id, '_walp_meters_per_box', true)));
    }

    /**
     * Get quantity in box from attribute
     */
    private function get_quantity_in_box($product)
    {
        $terms = wc_get_product_terms($product->get_id(), 'pa_ilosc-sztuk-w-opakowaniu', array('fields' => 'names'));
        return !empty($terms) ? intval($terms[0]) : 0;
    }

    /**
     * Add custom fields to product edit page
     */
    public function add_custom_fields()
    {
        echo '<div class="options_group">';

        // Product type dropdown
        woocommerce_wp_select(array(
            'id' => '_walp_product_type',
            'label' => __('Measurement Type', 'woocommerce-area-length-plugin'),
            'options' => array(
                'standard' => __('Standard', 'woocommerce-area-length-plugin'),
                'area' => __('Area', 'woocommerce-area-length-plugin'),
                'length' => __('Length', 'woocommerce-area-length-plugin')
            ),
            'desc_tip' => true,
            'description' => __('Select the measurement type for this product.', 'woocommerce-area-length-plugin')
        ));

        // Meters per box
        woocommerce_wp_text_input(array(
            'id' => '_walp_meters_per_box',
            'label' => __('Meters per package', 'woocommerce-area-length-plugin'),
            'desc_tip' => true,
            'description' => __('Enter meters per package for quantity calculations.', 'woocommerce-area-length-plugin'),
            'type' => 'number',
            'custom_attributes' => array(
                'step' => '0.01',
                'min' => '0'
            )
        ));

        echo '</div>';
    }

    /**
     * Save custom fields
     */
    public function save_custom_fields($post_id)
    {
        $product_type = isset($_POST['_walp_product_type']) ? sanitize_text_field($_POST['_walp_product_type']) : 'standard';
        $meters_per_box = isset($_POST['_walp_meters_per_box']) ? abs(floatval($_POST['_walp_meters_per_box'])) : 0;

        update_post_meta($post_id, '_walp_product_type', $product_type);
        update_post_meta($post_id, '_walp_meters_per_box', $meters_per_box);
    }

    /**
     * Helper function to render input field with increment/decrement buttons
     */
    private function render_input_field($id, $label, $required = false)
    {
        echo '<div class="walp-field-group">';
        echo '<label for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
        echo '<div class="walp-input-group">';
        echo '<button type="button" class="walp-decrement" data-target="' . esc_attr($id) . '">-</button>';
        echo '<input type="number" id="' . esc_attr($id) . '" name="' . esc_attr($id) . '" placeholder="0"' . ($required ? ' required' : '') . '>';
        echo '<button type="button" class="walp-increment" data-target="' . esc_attr($id) . '">+</button>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Helper function to render stat display
     */
    private function render_stat($label, $value_id, $hide = false)
    {
        $style = $hide ? ' style="display: none;"' : '';
        echo '<div class="walp-stat-group"' . $style . '>';
        echo '<div class="walp-stat">';
        echo '<span class="walp-stat-label">' . esc_html($label) . '</span>';
        echo '<span class="walp-stat-value" id="' . esc_attr($value_id) . '">-</span>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render dimensions input section for area products
     */
    private function render_area_dimensions_section()
    {
        echo '<div class="walp-section walp-section-collapsible">';
        echo '<h4 class="walp-section-title walp-section-header">' . __('Room Dimensions', 'woocommerce-area-length-plugin') . '<span class="walp-toggle-arrow"></span></h4>';
        echo '<div class="walp-section-content">';

        echo '<div class="walp-row">';

        $this->render_input_field('walp_length', __('Length (meters)', 'woocommerce-area-length-plugin'));
        $this->render_input_field('walp_width', __('Width (meters)', 'woocommerce-area-length-plugin'));

        $this->render_safety_margin_dropdown();

        echo '</div>'; // .walp-row
        echo '</div>'; // .walp-section-content
        echo '</div>'; // .walp-section
    }

    /**
     * Render safety margin dropdown
     */
    private function render_safety_margin_dropdown()
    {
        echo '<div class="walp-field-group">';
        echo '<label for="walp_margin">' . __('Safety Margin', 'woocommerce-area-length-plugin') . '</label>';
        echo '<select id="walp_margin" name="walp_margin">';
        $margins = array(
            0 => __('0% (No margin)', 'woocommerce-area-length-plugin'),
            5 => __('5% (Minimal)', 'woocommerce-area-length-plugin'),
            10 => __('10% (Standard)', 'woocommerce-area-length-plugin'),
        );
        foreach ($margins as $value => $label) {
            $selected = ($value === 10) ? ' selected' : '';
            echo '<option value="' . esc_attr($value) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';
    }

    /**
     * Render calculated fields section
     */
    private function render_calculated_fields_section($product_type)
    {
        echo '<div class="walp-section">';
        echo '<h4 class="walp-section-title">' . __('Required Quantity', 'woocommerce-area-length-plugin') . '</h4>';
        echo '<div class="walp-row walp-calculated-row">';

        if ($product_type === 'area') {
            $this->render_input_field('walp_calculated_value', __('Total Area (m²)', 'woocommerce-area-length-plugin'));
            $this->render_input_field('walp_calculated_qty', __('Packages Needed', 'woocommerce-area-length-plugin'), true);
        } else {
            $this->render_input_field('walp_calculated_value', __('Length (meters)', 'woocommerce-area-length-plugin'));
            $this->render_input_field('walp_calculated_qty', __('Pieces Needed', 'woocommerce-area-length-plugin'), true);
        }

        echo '</div>'; // .walp-row
        echo '</div>'; // .walp-section
    }

    /**
     * Render summary section
     */
    private function render_summary_section($product_type)
    {
        echo '<div class="walp-summary">';
        echo '<h4 class="walp-section-title">' . __('Summary', 'woocommerce-area-length-plugin') . '</h4>';
        echo '<div class="walp-stats">';

        $this->render_stat(__('Total:', 'woocommerce-area-length-plugin'), 'walp_total_value');
        $boxes_label = $product_type === 'area' ? __('Packages:', 'woocommerce-area-length-plugin') : __('Pieces:', 'woocommerce-area-length-plugin');
        $this->render_stat($boxes_label, 'walp_boxes_needed');

        $this->render_stat(__('Final Price:', 'woocommerce-area-length-plugin'), 'walp_final_price');

        echo '</div>';
        echo '</div>'; // .walp-summary
    }

    public function display_input_fields()
    {
        global $product;

        $product_id = $product->get_id();
        $product_type = $this->get_product_type_meta($product_id);
        $meters_per_box = $this->get_meters_per_box_meta($product_id);

        if (($product_type === 'area' || $product_type === 'length') && $meters_per_box > 0) {
            echo '<div class="walp-input-fields">';

            // Dimensions input section (only for area)
            if ($product_type === 'area') {
                $this->render_area_dimensions_section();
            }

            // Calculated fields section
            $this->render_calculated_fields_section($product_type);

            // Summary section
            $this->render_summary_section($product_type);

            echo '</div>'; // .walp-input-fields
        }
    }

    /**
     * Display hidden fields for form submission
     */
    public function display_hidden_fields()
    {
        global $product;

        $product_id = $product->get_id();
        $product_type = $this->get_product_type_meta($product_id);
        $meters_per_box = $this->get_meters_per_box_meta($product_id);

        if (($product_type === 'area' || $product_type === 'length') && $meters_per_box > 0) {
            // Hidden fields
            echo '<input type="hidden" name="walp_qty" id="walp_qty">';
            echo '<input type="hidden" name="walp_product_type" id="walp_product_type" value="' . esc_attr($product_type) . '">';
            echo '<input type="hidden" name="walp_meters_per_box" id="walp_meters_per_box" value="' . esc_attr($meters_per_box) . '">';
            echo '<input type="hidden" name="walp_product_price" id="walp_product_price" value="' . esc_attr($product->get_price()) . '">';
        }
    }

    /**
     * Hide standard quantity input for calculated products
     */
    public function hide_standard_quantity()
    {
        global $product;

        $product_id = $product->get_id();
        $product_type = $this->get_product_type_meta($product_id);
        $meters_per_box = $this->get_meters_per_box_meta($product_id);

        if (($product_type === 'area' || $product_type === 'length') && $meters_per_box > 0) {
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
    public function modify_add_to_cart_button()
    {
        global $product;

        $product_id = $product->get_id();
        $product_type = $this->get_product_type_meta($product_id);
        $meters_per_box = $this->get_meters_per_box_meta($product_id);

        if (($product_type === 'area' || $product_type === 'length') && $meters_per_box > 0) {
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
    public static function get_product_type($product_id)
    {
        return get_post_meta($product_id, '_walp_product_type', true);
    }

    /**
     * Get meters per box
     */
    public static function get_meters_per_box($product_id)
    {
        return floatval(get_post_meta($product_id, '_walp_meters_per_box', true));
    }

    /**
     * Modify stock status display
     */
    public function modify_stock_status($availability, $product)
    {
        if (!is_admin() && $product->is_in_stock()) {
            $stock_quantity = $product->get_stock_quantity();
            if ($stock_quantity > 1000) {
                $availability['availability'] = __('1000+ in stock', 'woocommerce-area-length-plugin');
            }
        }
        return $availability;
    }
}
