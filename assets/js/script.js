// WALP Calculator Module
var WALPCalculator = (function () {
    'use strict';

    // Configuration
    var config = {
        selectors: {
            length: '#walp_length',
            width: '#walp_width',
            margin: '#walp_margin',
            calculatedArea: '#walp_calculated_area',
            calculatedQty: '#walp_calculated_qty',
            metersPerBox: '#walp_meters_per_box',
            productType: '#walp_product_type',
            productPrice: '#walp_product_price',
            qty: '#walp_qty',
            qtyInput: 'input.qty',
            totalValue: '#walp_total_value',
            boxesNeeded: '#walp_boxes_needed',
            finalPrice: '#walp_final_price'
        },
        defaults: {
            minQty: 1,
            defaultCurrency: 'zł'
        }
    };

    // Utility functions
    var utils = {
        roundTo2: function (value) {
            return Math.round(value * 100) / 100;
        },

        getInputValue: function ($el, defaultValue) {
            if (typeof $el === 'string') {
                $el = jQuery($el);
            }
            var val = $el.val().replace(',', '.');
            return parseFloat(val) || defaultValue || 0;
        },

        isValidInteger: function (value) {
            return value > 0 && value === Math.floor(value);
        },

        formatCurrency: function (amount, symbol) {
            return amount.toFixed(2) + ' ' + symbol;
        },

        formatMeasurement: function (value, unit) {
            return value.toFixed(2) + ' ' + unit;
        }
    };

    // Calculation functions
    var calculator = {
        getMetersPerBox: function () {
            return utils.getInputValue(config.selectors.metersPerBox, 0);
        },

        getMarginMultiplier: function () {
            return 1 + (utils.getInputValue(config.selectors.margin, 0) / 100);
        },

        calculateAreaFromDimensions: function () {
            var length = utils.getInputValue(config.selectors.length);
            var width = utils.getInputValue(config.selectors.width);

            if (length <= 0 || width <= 0) {
                return 0;
            }

            return utils.roundTo2(length * width * this.getMarginMultiplier());
        },

        calculateBoxesFromMeters: function (meters) {
            if (meters <= 0) return 0;

            var metersPerBox = this.getMetersPerBox();
            return metersPerBox > 0 ? Math.ceil(meters / metersPerBox) : 0;
        },

        calculateAreaFromBoxes: function (boxes) {
            if (!utils.isValidInteger(boxes)) return 0;
            return utils.roundTo2(boxes * this.getMetersPerBox());
        },

        clampQty: function (qty) {
            var $qtyInput = jQuery(config.selectors.qtyInput);
            var minQty = parseInt($qtyInput.attr('min')) || config.defaults.minQty;
            var maxQty = $qtyInput.attr('max') ? parseInt($qtyInput.attr('max')) : null;

            if (maxQty !== null && qty > maxQty) return maxQty;
            if (qty < minQty) return minQty;
            return qty;
        }
    };

    // UI Update functions
    var ui = {
        updateSummary: function (boxes, clampedQty, productType) {
            var totalValue = '-';
            var boxesNeeded = boxes > 0 ? boxes : '-';
            var finalPrice = '-';

            // Calculate total value based on product type
            var totalCalculated = utils.roundTo2(boxes * calculator.getMetersPerBox());

            if (boxes > 0) {
                if (productType === 'area') {
                    totalValue = utils.formatMeasurement(totalCalculated, 'm²');
                } else if (productType === 'length') {
                    totalValue = utils.formatMeasurement(totalCalculated, 'mb');
                }
            }

            // Calculate final price
            if (clampedQty > 0) {
                var productPrice = utils.getInputValue(config.selectors.productPrice, 0);
                if (productPrice > 0) {
                    finalPrice = utils.formatCurrency(productPrice * clampedQty, config.defaults.defaultCurrency);
                }
            }

            // Check stock constraints
            if (clampedQty !== boxes && boxes > 0) {
                var stockInfo = '';
                if (clampedQty > boxes) {
                    stockInfo = 'co najmniej: ' + clampedQty;
                } else {
                    stockInfo = '<span style="color:orange">' + clampedQty + '</span> z ' + boxes + ' na stanie';
                }
                boxesNeeded += '<br><span style="font-size: 0.9em; line-height: 1.3; color: #666;">' + stockInfo + '</span>';
            }

            // Update DOM
            jQuery(config.selectors.totalValue).text(totalValue);
            jQuery(config.selectors.boxesNeeded).html(boxesNeeded);
            jQuery(config.selectors.finalPrice).text(finalPrice);
        },

        updateQuantityFields: function (boxes, area, productType) {
            var clampedQty = calculator.clampQty(boxes);
            jQuery(config.selectors.calculatedQty).val(boxes > 0 ? boxes : '');
            jQuery(config.selectors.qty).val(clampedQty > 0 ? clampedQty : '');
            jQuery(config.selectors.qtyInput).val(clampedQty > 0 ? clampedQty : config.defaults.minQty);
            this.updateSummary(boxes, clampedQty, productType);
        },

        handleEmptyCalculation: function (productType) {
            var $qtyInput = jQuery(config.selectors.qtyInput);
            var minQty = parseInt($qtyInput.attr('min')) || config.defaults.minQty;
            this.updateSummary(minQty, minQty, productType);
        }
    };

    // Main calculation orchestration
    var engine = {
        updateCalculations: function (triggeredBy) {
            var productType = jQuery(config.selectors.productType).val();

            if (calculator.getMetersPerBox() <= 0) {
                return;
            }

            if (productType === 'area') {
                this._handleAreaCalculations(triggeredBy, productType);
            } else if (productType === 'length') {
                this._handleLengthCalculations(triggeredBy, productType);
            }
        },

        _handleAreaCalculations: function (triggeredBy, productType) {
            var $qtyInput = jQuery(config.selectors.qtyInput);
            var minQty = parseInt($qtyInput.attr('min')) || config.defaults.minQty;

            if (triggeredBy === 'dimensions') {
                var length = utils.getInputValue(config.selectors.length);
                var width = utils.getInputValue(config.selectors.width);

                if (length <= 0 || width <= 0) {
                    ui.handleEmptyCalculation(productType);
                    return;
                }

                var area = calculator.calculateAreaFromDimensions();
                jQuery(config.selectors.calculatedArea).val(area > 0 ? area : '');
                var boxes = calculator.calculateBoxesFromMeters(area) || minQty;

                if (boxes === 0 || !area) {
                    boxes = minQty;
                    area = calculator.calculateAreaFromBoxes(boxes);
                    jQuery(config.selectors.calculatedArea).val(area > 0 ? area.toFixed(2) : '');
                }

                ui.updateQuantityFields(boxes, area, productType);
            }
            else if (triggeredBy === 'area') {
                var area = utils.getInputValue(config.selectors.calculatedArea);

                if (area <= 0) {
                    ui.handleEmptyCalculation(productType);
                    return;
                }

                var boxes = calculator.calculateBoxesFromMeters(area);
                ui.updateQuantityFields(boxes, area, productType);
            }
            else if (triggeredBy === 'boxes') {
                var boxes = parseInt(jQuery(config.selectors.calculatedQty).val()) || 0;
                if (boxes <= 0) {
                    boxes = minQty;
                }
                jQuery(config.selectors.calculatedQty).val(boxes);
                var area = calculator.calculateAreaFromBoxes(boxes);
                jQuery(config.selectors.calculatedArea).val(area > 0 ? area : '');
                ui.updateQuantityFields(boxes, area, productType);
            }
        },

        _handleLengthCalculations: function (triggeredBy, productType) {
            var $qtyInput = jQuery(config.selectors.qtyInput);
            var minQty = parseInt($qtyInput.attr('min')) || config.defaults.minQty;

            if (triggeredBy === 'dimensions') {
                var length = utils.getInputValue(config.selectors.length);

                if (length <= 0) {
                    ui.handleEmptyCalculation(productType);
                    return;
                }

                var totalLength = length;
                var boxes = calculator.calculateBoxesFromMeters(totalLength) || minQty;
                ui.updateQuantityFields(boxes, 0, productType);
            }
            else if (triggeredBy === 'boxes') {
                var boxes = parseInt(jQuery(config.selectors.calculatedQty).val()) || 0;
                if (boxes <= 0) {
                    boxes = minQty;
                }
                jQuery(config.selectors.calculatedQty).val(boxes);
                var totalLength = boxes * calculator.getMetersPerBox();
                jQuery(config.selectors.length).val(totalLength > 0 ? utils.roundTo2(totalLength) : '');
                ui.updateQuantityFields(boxes, 0, productType);
            }
        },

        adjustInputValue: function (input, increment) {
            var current = parseFloat(input.val()) || 0;
            var min = parseFloat(input.attr('min')) || 0;
            var newValue = utils.roundTo2(current + (increment ? 1 : -1));

            if (!increment && newValue < min) {
                return;
            }

            input.val(newValue).trigger('input');
        }
    };

    // Public API
    return {
        utils: utils,
        calculator: calculator,
        ui: ui,
        engine: engine,
        config: config
    };
})();

// Initialize on document ready
jQuery(document).ready(function ($) {
    var WALP = WALPCalculator;

    // Event bindings
    $('#walp_length, #walp_width, #walp_margin').on('input change', function () {
        WALP.engine.updateCalculations('dimensions');
    });

    $('#walp_calculated_area').on('input change', function () {
        WALP.engine.updateCalculations('area');
    });

    $('#walp_calculated_qty').on('input change', function () {
        WALP.engine.updateCalculations('boxes');
    });

    // Custom increment/decrement buttons
    $('.walp-increment').on('click', function () {
        var input = $('#' + $(this).data('target'));
        WALP.engine.adjustInputValue(input, true);
    });

    $('.walp-decrement').on('click', function () {
        var input = $('#' + $(this).data('target'));
        WALP.engine.adjustInputValue(input, false);
    });

    // Initial calculation and setup
    var qtyInput = $('input.qty');
    var minQty = parseInt(qtyInput.attr('min')) || 1;
    $('#walp_calculated_qty').val(minQty);

    // Trigger calculation based on boxes to show initial stats
    WALP.engine.updateCalculations('boxes');
    $('input.qty').attr('step', '1');
});
