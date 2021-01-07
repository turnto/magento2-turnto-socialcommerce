/**
 * TurnTo_SocialCommerce
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright  Copyright (c) 2018 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
define([
    'jquery',
    'jquery/ui'
], function ($) {
    'use strict';
    return function (original) {
        $.widget('mage.SwatchRenderer', original, {

            /*
             * If use child sku is enabled, pass the child sku to TurnTo after a swatch change.
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
                var selected_options = {};
                jQuery('div.swatch-attribute').each(function (k, v) {
                    var attribute_id = jQuery(v).attr('attribute-id');
                    var option_selected = jQuery(v).attr('option-selected');
                    if (!attribute_id || !option_selected) {
                        return;
                    }
                    selected_options[attribute_id] = option_selected;
                });

                var product_id_index = jQuery('[data-role=swatch-options]').data('mageSwatchRenderer').options.jsonConfig.index;
                var self = this;
                jQuery.each(product_id_index, function (product_id, attributes) {
                    var productIsSelected = function (attributes, selected_options) {
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
