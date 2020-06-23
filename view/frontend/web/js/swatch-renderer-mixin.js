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

            /**
             * Callback for product media
             *
             * @param {Object} $this
             * @param {String} response
             * @private
             */
            _ProductMediaCallback: function ($this, response) {
                this._super($this, response);

                if (!this.options.jsonConfig.useChild) {
                    this.turntoProductReset(response.sku)
                }
            },

            /**
             * @param images
             * @param context
             * @param isInProductView
             */
            updateBaseImage: function (images, context, isInProductView) {
                if (!this.options.useAjax && 0 in images && images[0].sku !== undefined) {
                    //load child reviews
                    this.turntoProductReset(images[0].sku);
                }else{
                    //load parent product
                    this.turntoProductReset(this.options.jsonConfig.parentSku);
                }
                
                this._super(images, context, isInProductView);
            },

            /**
             * @param sku
             */
            turntoProductReset: function(sku) {
                if (TurnToCmd === void(0) || sku === void(0) || !this.options.jsonConfig.useChild) {
                    return;
                }

                TurnToCmd('set', {"sku": sku});
            }
        });

        return $.mage.SwatchRenderer;
    }
});
