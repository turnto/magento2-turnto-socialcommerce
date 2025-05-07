/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
define([
    'jquery',
    'underscore',
    'jquery-ui-modules/widget',
], function ($, _) {
    'use strict';
    return function (original) {
        $.widget('mage.SwatchRenderer', original, {
            /*
             * If useChild sku is enabled, pass the child sku to TurnTo after a swatch change.
             */
            _OnClick: function ($this, $widget) {
                this._super($this, $widget);
                if (this.options.jsonConfig.useChild) {
                    this.selectedProduct();
                }
            },

            /*
             * Get the product sku and pass it to TurnTo by using the selected swatch
             */
            selectedProduct: function () {
                let selected_options = {};
                $('div.swatch-attribute').each(function (k, v) {
                    // In Magento 2.4+ the div attributes are called "data-attribute-id" and "data-option-selected"
                    // In versions before 2.4, they're "attribute-id" and "option-selected". So check both.
                    let attribute_id = $(v).attr('data-attribute-id');
                    let option_selected = $(v).attr('data-option-selected');
                    if (!attribute_id || !option_selected) {
                        // Try this if they're using version < 2.4
                        attribute_id = $(v).attr('attribute-id');
                        option_selected = $(v).attr('option-selected');
                        // If we still don't have anything, now we can return
                        if (!attribute_id || !option_selected) {
                            return;
                        }
                    }
                    selected_options[attribute_id] = option_selected;
                });

                let product_id_index = $('[data-role=swatch-options]').data('mage-SwatchRenderer').options.jsonConfig.index;
                let self = this;
                $.each(product_id_index, function (product_id, attributes) {
                    let productIsSelected = function (attributes, selected_options) {
                        return _.isEqual(attributes, selected_options);
                    };
                    if (productIsSelected(attributes, selected_options)) {
                        // Update the TurnTo SKU
                        let sku_value = self.options.jsonConfig.childSkuMap[product_id];
                        TurnToCmd('set', {"sku": sku_value});
                        // Update Top Comment Widget
                        let comments = document.getElementsByClassName('tt-top-comment')[0];
                        if (typeof comments !== 'undefined') {
                            comments.setAttribute('data-ttsku',sku_value);
                            comments.setAttribute("data-ttprocessed", "");
                            comments.innerHTML = "";
                            TurnToCmd('topComments.process');
                        }

                        //break out of the loop
                        return false;
                    }
                });
            },
        });

        return $.mage.SwatchRenderer;
    }
});
