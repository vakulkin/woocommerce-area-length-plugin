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
        add_action('woocommerce_before_add_to_cart_form', array($this, 'display_input_fields'));
        add_action('woocommerce_before_add_to_cart_button', array( $this, 'display_hidden_fields' ), 5);
        add_action('woocommerce_before_add_to_cart_quantity', array( $this, 'hide_standard_quantity' ));
        add_action('woocommerce_after_add_to_cart_button', array( $this, 'modify_add_to_cart_button' ));
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
        $meters_per_box = isset($_POST['_walp_meters_per_box']) ? floatval($_POST['_walp_meters_per_box']) : 0;

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
        echo '<input type="number" id="' . esc_attr($id) . '" name="' . esc_attr($id) . '" step="1" min="0" placeholder="0"' . ($required ? ' required' : '') . '>';
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
        echo '<div class="walp-stat" id="' . esc_attr($value_id) . '_wrapper">';
        echo '<span class="walp-stat-label">' . esc_html($label) . '</span>';
        echo '<span class="walp-stat-value" id="' . esc_attr($value_id) . '">-</span>';
        echo '</div>';
        echo '</div>';
    }

    public function display_input_fields()
    {
        global $product;

        $product_type = get_post_meta($product->get_id(), '_walp_product_type', true);

        if ($product_type === 'area' || $product_type === 'length') {
            echo '<div class="walp-input-fields">';

            // Dimensions input section with wrapper
            echo '<div class="walp-section">';
            echo '<div class="walp-row walp-dimensions-row">';

            // Render dimension inputs based on product type
            if ($product_type === 'area') {
                $this->render_input_field('walp_length', __('Długość (metry)', 'woocommerce-area-length-plugin'));
                $this->render_input_field('walp_width', __('Szerokość (metry)', 'woocommerce-area-length-plugin'));
            } else {
                $this->render_input_field('walp_length', __('Długość (metry)', 'woocommerce-area-length-plugin'));
                $this->render_input_field('walp_calculated_qty', __('Potrzebne opakowania', 'woocommerce-area-length-plugin'), true);
            }

            // Safety margin dropdown only for area
            if ($product_type === 'area') {
                echo '<div class="walp-field-group">';
                echo '<label for="walp_margin">' . __('Zapas', 'woocommerce-area-length-plugin') . '</label>';
                echo '<select id="walp_margin" name="walp_margin">';
                $margins = array(
                    0  => __('0% (Bez marginesu)', 'woocommerce-area-length-plugin'),
                    5  => __('5% (Minimalny)', 'woocommerce-area-length-plugin'),
                    10 => __('10% (Standardowy)', 'woocommerce-area-length-plugin'),
                    15 => __('15% (Konserwatywny)', 'woocommerce-area-length-plugin'),
                    20 => __('20% (Bardzo bezpieczny)', 'woocommerce-area-length-plugin'),
                );
                foreach ($margins as $value => $label) {
                    $selected = ($value === 10) ? ' selected' : '';
                    echo '<option value="' . esc_attr($value) . '"' . $selected . '>' . esc_html($label) . '</option>';
                }
                echo '</select>';
                echo '</div>';
            }

            echo '</div>'; // .walp-row
            echo '</div>'; // .walp-section

            // Calculated fields section with wrapper
            if ($product_type === 'area') {
                echo '<div class="walp-calculated-fields">';
                echo '<div class="walp-section">';
                echo '<div class="walp-row">';

                $this->render_input_field('walp_calculated_area', __('Całkowita powierzchnia (m²)', 'woocommerce-area-length-plugin'));
                $this->render_input_field('walp_calculated_qty', __('Potrzebne opakowania', 'woocommerce-area-length-plugin'), true);

                echo '</div>'; // .walp-row
                echo '</div>'; // .walp-section
                echo '</div>'; // .walp-calculated-fields
            }

            // Summary section
            echo '<div class="walp-summary">';
            echo '<h4>' . __('Podsumowanie pomiarów', 'woocommerce-area-length-plugin') . '</h4>';
            echo '<div class="walp-stats">';

            $this->render_stat(__('Razem:', 'woocommerce-area-length-plugin'), 'walp_total_value');
            $this->render_stat(__('Opakowania:', 'woocommerce-area-length-plugin'), 'walp_boxes_needed');

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

        $product_type = get_post_meta($product->get_id(), '_walp_product_type', true);
        $meters_per_box = get_post_meta($product->get_id(), '_walp_meters_per_box', true);

        if ($product_type === 'area' || $product_type === 'length') {
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

        $product_type = get_post_meta($product->get_id(), '_walp_product_type', true);

        if ($product_type === 'area' || $product_type === 'length') {
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

        $product_type = get_post_meta($product->get_id(), '_walp_product_type', true);

        if ($product_type === 'area' || $product_type === 'length') {
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
}
