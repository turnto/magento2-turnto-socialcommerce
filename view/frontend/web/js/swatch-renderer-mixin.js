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
 * @copyright  Copyright (c) 2016 TurnTo Networks, Inc.
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

                if (window.turnToUseChildSku) {
                    if (typeof TurnTo != "undefined" && typeof response.sku != "undefined") {
                        TurnTo.reset({"sku": response.sku});
                    }

                    if (typeof TurnToChatter != "undefined" && typeof response.sku != "undefined") {
                        TurnToChatter.reset({"sku": response.sku});
                    }
                }
            }
        });

        return $.mage.SwatchRenderer;
    }
});
