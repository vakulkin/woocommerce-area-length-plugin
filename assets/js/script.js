// WALP Calculator Module
var WALPCalculator = (function () {
    'use strict';

    // Configuration
    var config = {
        selectors: {
            length: '#walp_length',
            width: '#walp_width',
            margin: '#walp_margin',
            calculatedValue: '#walp_calculated_value',
            calculatedQty: '#walp_calculated_qty',
            mozaikQty: '#walp_mozaik_qty',
            mozaikArea: '#walp_mozaik_area',
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
            minQty: 1
        },
        // Currency and i18n will be set from walpData
        currency: typeof walpData !== 'undefined' ? walpData.currency : {
            symbol: '$',
            position: 'left',
            decimalSeparator: '.',
            thousandSeparator: ',',
            decimals: 2
        },
        i18n: typeof walpData !== 'undefined' ? walpData.i18n : {
            atLeast: 'at least:',
            weHave: 'we have',
            of: 'of',
            inStock: 'in stock',
            squareMeters: 'm²',
            meters: 'm',
            pieces: 'pcs'
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
            if (!$el.length) {
                return defaultValue || 0;
            }
            var val = $el.val().replace(',', '.');
            return parseFloat(val) || defaultValue || 0;
        },

        isValidInteger: function (value) {
            return value > 0 && value === Math.floor(value);
        },

        formatCurrency: function (amount, symbol) {
            var formattedAmount = amount.toFixed(config.currency.decimals);
            
            // Replace decimal separator
            formattedAmount = formattedAmount.replace('.', config.currency.decimalSeparator);
            
            // Add thousand separator
            var parts = formattedAmount.split(config.currency.decimalSeparator);
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, config.currency.thousandSeparator);
            formattedAmount = parts.join(config.currency.decimalSeparator);
            
            // Format based on currency position
            var currencySymbol = symbol || config.currency.symbol;
            switch (config.currency.position) {
                case 'left':
                    return currencySymbol + formattedAmount;
                case 'left_space':
                    return currencySymbol + ' ' + formattedAmount;
                case 'right':
                    return formattedAmount + currencySymbol;
                case 'right_space':
                default:
                    return formattedAmount + ' ' + currencySymbol;
            }
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
            if (metersPerBox <= 0) return 0;

            var ratio = meters / metersPerBox;
            // Round to 2 decimal places since only 2 decimals are valuable
            ratio = utils.roundTo2(ratio);
            return Math.ceil(ratio);
        },

        calculateAreaFromBoxes: function (boxes) {
            if (!utils.isValidInteger(boxes)) return 0;
            return utils.roundTo2(boxes * this.getMetersPerBox());
        },

        calculateMozaikAreaFromQty: function (qty) {
            if (qty <= 0) return 0;
            var metersPerBox = this.getMetersPerBox();
            if (metersPerBox <= 0) return 0;
            // Direct calculation without margin
            return utils.roundTo2(qty * metersPerBox);
        },

        calculateMozaikQtyFromArea: function (area) {
            if (area <= 0) return 0;
            var metersPerBox = this.getMetersPerBox();
            if (metersPerBox <= 0) return 0;
            // Direct calculation without margin in qty calculation
            return Math.ceil(area / metersPerBox);
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
        calculateTotalValue: function (boxes, productType) {
            var totalCalculated = utils.roundTo2(boxes * calculator.getMetersPerBox());

            if (boxes > 0) {
                if (productType === 'area') {
                    return utils.formatMeasurement(totalCalculated, config.i18n.squareMeters);
                } else if (productType === 'length') {
                    return utils.formatMeasurement(totalCalculated, config.i18n.meters);
                } else if (productType === 'mozaik') {
                    // Apply margin to total area display
                    var totalWithMargin = utils.roundTo2(totalCalculated * calculator.getMarginMultiplier());
                    return utils.formatMeasurement(totalWithMargin, config.i18n.squareMeters);
                }
            }

            return '-';
        },

        calculateFinalPrice: function (clampedQty) {
            if (clampedQty > 0) {
                var productPrice = utils.getInputValue(config.selectors.productPrice, 0);
                if (productPrice > 0) {
                    return utils.formatCurrency(productPrice * clampedQty);
                }
            }
            return '-';
        },

        generateStockInfo: function (boxes, clampedQty) {
            if (boxes <= 0) return boxes > 0 ? boxes : '-';

            var boxesNeeded = boxes;

            if (clampedQty > boxes) {
                var stockInfo = config.i18n.atLeast + ' ' + clampedQty;
                boxesNeeded += '<br><span class="walp-stock-info">' + stockInfo + '</span>';
            } else if (clampedQty < boxes) {
                var stockInfo = config.i18n.weHave + ' <span class="walp-stock-warning">' + clampedQty + '</span> ' + config.i18n.of + ' ' + boxes + ' ' + config.i18n.inStock;
                boxesNeeded = '<span class="walp-stock-info">' + stockInfo + '</span>';
            }
            // If clampedQty === boxes, boxesNeeded remains as boxes

            return boxesNeeded;
        },

        updateSummary: function (boxes, clampedQty, productType) {
            var totalValue = this.calculateTotalValue(boxes, productType);
            var boxesNeeded = this.generateStockInfo(boxes, clampedQty);
            var finalPrice = this.calculateFinalPrice(clampedQty);

            // Update DOM
            jQuery(config.selectors.totalValue).text(totalValue);
            jQuery(config.selectors.boxesNeeded).html(boxesNeeded);
            jQuery(config.selectors.finalPrice).text(finalPrice);
        },

        updateQuantityFields: function (boxes, area, productType) {
            // For mozaik, calculate boxes based on area WITH margin
            var finalBoxes = boxes;
            if (productType === 'mozaik' && boxes > 0) {
                var totalAreaWithMargin = boxes * calculator.getMetersPerBox() * calculator.getMarginMultiplier();
                finalBoxes = Math.ceil(totalAreaWithMargin / calculator.getMetersPerBox());
            }
            
            var clampedQty = calculator.clampQty(finalBoxes);
            jQuery(config.selectors.calculatedQty).val(finalBoxes > 0 ? finalBoxes : '');
            jQuery(config.selectors.qty).val(clampedQty > 0 ? clampedQty : '');
            jQuery(config.selectors.qtyInput).val(clampedQty > 0 ? clampedQty : config.defaults.minQty);
            this.updateSummary(finalBoxes, clampedQty, productType);
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
            } else if (productType === 'mozaik') {
                this._handleMozaikCalculations(triggeredBy, productType);
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
                jQuery(config.selectors.calculatedValue).val(area > 0 ? area : '');
                var boxes = calculator.calculateBoxesFromMeters(area) || minQty;

                if (boxes === 0 || !area) {
                    boxes = minQty;
                    area = calculator.calculateAreaFromBoxes(boxes);
                    jQuery(config.selectors.calculatedValue).val(area > 0 ? area.toFixed(2) : '');
                }

                ui.updateQuantityFields(boxes, area, productType);
            }
            else if (triggeredBy === 'area') {
                var area = utils.getInputValue(config.selectors.calculatedValue);

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
                jQuery(config.selectors.calculatedValue).val(area > 0 ? area : '');
                ui.updateQuantityFields(boxes, area, productType);
            }
        },

        _handleLengthCalculations: function (triggeredBy, productType) {
            var $qtyInput = jQuery(config.selectors.qtyInput);
            var minQty = parseInt($qtyInput.attr('min')) || config.defaults.minQty;

            if (triggeredBy === 'area') {
                var length = utils.getInputValue(config.selectors.calculatedValue);

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
                jQuery(config.selectors.calculatedValue).val(totalLength > 0 ? utils.roundTo2(totalLength) : '');
                ui.updateQuantityFields(boxes, 0, productType);
            }
        },

        _handleMozaikCalculations: function (triggeredBy, productType) {
            var $qtyInput = jQuery(config.selectors.qtyInput);
            var minQty = parseInt($qtyInput.attr('min')) || config.defaults.minQty;

            if (triggeredBy === 'mozaik_qty') {
                var qty = utils.getInputValue(config.selectors.mozaikQty);

                if (qty <= 0) {
                    qty = minQty;
                    jQuery(config.selectors.mozaikQty).val(minQty);
                }

                var area = calculator.calculateMozaikAreaFromQty(qty);
                jQuery(config.selectors.mozaikArea).val(area > 0 ? area.toFixed(2) : '');
                // Calculate total area covered (qty * meters_per_box)
                var totalArea = qty * calculator.getMetersPerBox();
                ui.updateQuantityFields(Math.ceil(qty), totalArea, productType);
            }
            else if (triggeredBy === 'mozaik_area') {
                var area = utils.getInputValue(config.selectors.mozaikArea);

                if (area <= 0) {
                    jQuery(config.selectors.mozaikQty).val(minQty);
                    var totalArea = minQty * calculator.getMetersPerBox();
                    ui.updateQuantityFields(minQty, totalArea, productType);
                    return;
                }

                var qty = calculator.calculateMozaikQtyFromArea(area) || minQty;
                jQuery(config.selectors.mozaikQty).val(qty);
                // Calculate total area covered (qty * meters_per_box)
                var totalArea = qty * calculator.getMetersPerBox();
                ui.updateQuantityFields(qty, totalArea, productType);
            }
            else if (triggeredBy === 'margin') {
                // Just refresh display with new margin - don't change input values
                var qty = utils.getInputValue(config.selectors.mozaikQty);
                if (qty <= 0) {
                    qty = minQty;
                    jQuery(config.selectors.mozaikQty).val(minQty);
                }
                var totalArea = qty * calculator.getMetersPerBox();
                ui.updateQuantityFields(Math.ceil(qty), totalArea, productType);
            }
        },

        adjustInputValue: function (input, increment) {
            var current = parseFloat(input.val()) || 0;
            var min = parseFloat(input.attr('min')) || 0;
            var step = 1;

            if (input.attr('id') === 'walp_calculated_value') {
                step = calculator.getMetersPerBox() || 1;
            }
            
            // For mozaik qty, increment by 1 piece
            if (input.attr('id') === 'walp_mozaik_qty') {
                step = 1;
            }
            
            // For mozaik area, increment by meters_per_box
            if (input.attr('id') === 'walp_mozaik_area') {
                step = calculator.getMetersPerBox() || 1;
            }

            var newValue = utils.roundTo2(current + (increment ? step : -step));

            if (!increment && newValue < min) {
                newValue = min;
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

    $('#walp_calculated_value').on('input change', function () {
        WALP.engine.updateCalculations('area');
    });

    $('#walp_calculated_qty').on('input change', function () {
        WALP.engine.updateCalculations('boxes');
    });

    // Mozaik event bindings
    $('#walp_mozaik_qty').on('input change', function () {
        WALP.engine.updateCalculations('mozaik_qty');
    });

    $('#walp_mozaik_area').on('input change', function () {
        WALP.engine.updateCalculations('mozaik_area');
    });

    // Margin change for mozaik
    $('#walp_margin').on('change', function () {
        var productType = $('#walp_product_type').val();
        if (productType === 'mozaik') {
            WALP.engine.updateCalculations('margin');
        }
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

    // Initialize mozaik calculator with default qty
    var productType = $('#walp_product_type').val();
    if (productType === 'mozaik') {
        $('#walp_mozaik_qty').val(minQty);
        WALP.engine.updateCalculations('mozaik_qty');
    } else {
        // Trigger calculation based on boxes to show initial stats
        WALP.engine.updateCalculations('boxes');
    }

    // Collapsible section toggle
    $('.walp-section-header').on('click', function() {
        var $section = $(this).closest('.walp-section-collapsible');
        $section.toggleClass('expanded');
    });
});
