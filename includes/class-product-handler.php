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
        add_filter('woocommerce_get_price_suffix', array( $this, 'add_price_suffix' ), 10, 2);
        add_filter('woocommerce_get_price_html', array( $this, 'modify_price_html' ), 10, 2);
        add_filter('woocommerce_get_availability', array( $this, 'modify_stock_status' ), 10, 2);
    }

    /**
     * Modify price HTML for area products to show price per m²
     */
    public function modify_price_html($price_html, $product)
    {
        $product_type = $this->get_product_type_meta($product->get_id());
        $meters_per_box = $this->get_meters_per_box_meta($product->get_id());

        if ($product_type === 'area' && $meters_per_box > 0) {
            $price_per_m2 = $product->get_price() / $meters_per_box;
            $box_price = $product->get_price();
            
            // Get quantity in box from attribute
            $quantity_in_box = $this->get_quantity_in_box($product);
            
            $price_html = '<span class="woocommerce-Price-amount amount"><bdi>' . number_format($price_per_m2, 2, ',', ' ') . '&nbsp;<span class="woocommerce-Price-currencySymbol">zł</span>/m²</bdi></span><br>';
            $price_html .= '<span class="walp-box-price"><bdi>' . number_format($box_price, 2, ',', ' ') . '&nbsp;<span class="woocommerce-Price-currencySymbol">zł</span>' . __('/opakowanie', 'woocommerce-area-length-plugin') . '</bdi></span>';
            if ($quantity_in_box) {
                $price_html .= '<span class="walp-box-price">' . sprintf(__('%d szt. w opakowaniu', 'woocommerce-area-length-plugin'), $quantity_in_box) . '</span>';
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
            'label' => __('Typ pomiaru', 'woocommerce-area-length-plugin'),
            'options' => array(
                'standard' => __('Standardowy', 'woocommerce-area-length-plugin'),
                'area' => __('Powierzchnia', 'woocommerce-area-length-plugin'),
                'length' => __('Długość', 'woocommerce-area-length-plugin')
            ),
            'desc_tip' => true,
            'description' => __('Wybierz typ pomiaru dla tego produktu.', 'woocommerce-area-length-plugin')
        ));

        // Meters per box
        woocommerce_wp_text_input(array(
            'id' => '_walp_meters_per_box',
            'label' => __('Metry na opakowanie', 'woocommerce-area-length-plugin'),
            'desc_tip' => true,
            'description' => __('Wprowadź metry na opakowanie dla obliczeń ilości.', 'woocommerce-area-length-plugin'),
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

    public function display_input_fields()
    {
        global $product;

        $product_id = $product->get_id();
        $product_type = $this->get_product_type_meta($product_id);
        $meters_per_box = $this->get_meters_per_box_meta($product_id);

        if (($product_type === 'area' || $product_type === 'length') && $meters_per_box > 0) {
            echo '<div class="walp-input-fields">';

            // Dimensions input section with wrapper (only for area)
            if ($product_type === 'area') {
                echo '<div class="walp-section walp-section-collapsible">';
                echo '<h4 class="walp-section-title walp-section-header">' . __('Wymiary pomieszczenia', 'woocommerce-area-length-plugin') . '<span class="walp-toggle-arrow"></span></h4>';
                echo '<div class="walp-section-content">';

                echo '<div class="walp-row">';

                $this->render_input_field('walp_length', __('Długość (metry)', 'woocommerce-area-length-plugin'));
                $this->render_input_field('walp_width', __('Szerokość (metry)', 'woocommerce-area-length-plugin'));

                // Safety margin dropdown only for area
                echo '<div class="walp-field-group">';
                echo '<label for="walp_margin">' . __('Zapas', 'woocommerce-area-length-plugin') . '</label>';
                echo '<select id="walp_margin" name="walp_margin">';
                $margins = array(
                    0 => __('0% (Bez marginesu)', 'woocommerce-area-length-plugin'),
                    5 => __('5% (Minimalny)', 'woocommerce-area-length-plugin'),
                    10 => __('10% (Standardowy)', 'woocommerce-area-length-plugin'),
                );
                foreach ($margins as $value => $label) {
                    $selected = ($value === 10) ? ' selected' : '';
                    echo '<option value="' . esc_attr($value) . '"' . $selected . '>' . esc_html($label) . '</option>';
                }
                echo '</select>';
                echo '</div>';

                echo '</div>'; // .walp-row
                echo '</div>'; // .walp-section-content
                echo '</div>'; // .walp-section
            }

            // Calculated fields section with wrapper
            echo '<div class="walp-section">';
            echo '<h4 class="walp-section-title">' . __('Potrzebna ilość', 'woocommerce-area-length-plugin') . '</h4>';
            echo '<div class="walp-row walp-calculated-row">';

            if ($product_type === 'area') {
                $this->render_input_field('walp_calculated_value', __('Całkowita powierzchnia (m²)', 'woocommerce-area-length-plugin'));
                $this->render_input_field('walp_calculated_qty', __('Potrzebne opakowania', 'woocommerce-area-length-plugin'), true);
            } else {
                $this->render_input_field('walp_calculated_value', __('Długość (metry)', 'woocommerce-area-length-plugin'));
                $this->render_input_field('walp_calculated_qty', __('Potrzebne sztuki', 'woocommerce-area-length-plugin'), true);
            }

            echo '</div>'; // .walp-row
            echo '</div>'; // .walp-section

            // Summary section
            echo '<div class="walp-summary">';
            echo '<h4 class="walp-section-title">' . __('Podsumowanie', 'woocommerce-area-length-plugin') . '</h4>';
            echo '<div class="walp-stats">';

            $this->render_stat(__('Razem:', 'woocommerce-area-length-plugin'), 'walp_total_value');
            $boxes_label = $product_type === 'area' ? __('Opakowania:', 'woocommerce-area-length-plugin') : __('Sztuki:', 'woocommerce-area-length-plugin');
            $this->render_stat($boxes_label, 'walp_boxes_needed');

            $this->render_stat(__('Cena końcowa:', 'woocommerce-area-length-plugin'), 'walp_final_price');

            echo '</div>';
            echo '</div>'; // .walp-summary
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
     * Add price suffix based on product type
     */
    public function add_price_suffix($suffix, $product)
    {
        $product_type = $this->get_product_type_meta($product->get_id());
        $meters_per_box = $this->get_meters_per_box_meta($product->get_id());

        if (($product_type === 'area' || $product_type === 'length') && $meters_per_box > 0) {
            if ($product_type === 'length') {
                $suffix .= ' / ' . __('sztuka', 'woocommerce-area-length-plugin');
            }
        }

        return $suffix;
    }

    /**
     * Modify stock status display
     */
    public function modify_stock_status($availability, $product)
    {
        if ($product->is_in_stock()) {
            $stock_quantity = $product->get_stock_quantity();
            if ($stock_quantity > 1000) {
                $availability['availability'] = __('1000+ w magazynie', 'woocommerce-area-length-plugin');
            }
        }
        return $availability;
    }
}
