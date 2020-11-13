/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
define([
    'underscore',
    'ko',
    'uiComponent',
    'jquery'
], function (_, ko, Component, jQuery) {
    'use strict';

    return Component.extend({
        defaults: {
            siteKey: null,
            reviewSku: null,
            reviewsData: {},
            reviewsEnabled: null,
            reviewsUrl: null

        },
        //
        /**
         * Initialize view.
         *
         * @returns {Component} Chainable.
         */
        initialize: function initialize() {
            this._super();

            this.observe(['reviewsData']);

            if (this.reviewSku !== null && this.reviewsEnabled === "true") {
                this.loadReviewCount(this.reviewSku);
            }

            // Map bridge the tabs widget to something we can manually call
            jQuery.widget.bridge('mage_tabs', jQuery.mage.tabs);
            this.tabsContainer = document.querySelector('.product.data.items');

            return this;
        },

        loadReviewCount: function loadTeaserCounts(sku) {
            var xhr = new XMLHttpRequest();

            xhr.open('GET', this.reviewsUrl + this.siteKey + '/' + sku + '/d/ugc/counts/en_US', true);
            xhr.addEventListener('load', function () {
                if (!xhr.responseText) {
                    return;
                }
                this.reviewsData(JSON.parse(xhr.responseText));
                this.populateReviewTabCount();
            }.bind(this));
            xhr.send();
        },

        populateReviewTabCount: function populateReviewTabCount(){
            let reviewTab = document.getElementById('tab-label-reviews-title');
            let reviewCount = this.reviewsData().reviews;
            //check to ensure review tab does not already have a count
            if( ! /\d/.test(reviewTab.innerHTML)){
                reviewTab.innerHTML = reviewTab.innerHTML + '<span class="counter">'+ reviewCount +'</span>';
            }
        }
    });
});
