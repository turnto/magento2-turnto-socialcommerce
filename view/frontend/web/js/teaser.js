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
            template: 'TurnTo_SocialCommerce/teaser',
            siteKey: null,
            teaserSku: null,
            reviewsData: {},
            reviewsEnabled: null,
            reviewsTeaserEnabled: null,
            qaEnabled: null,
            qaTeaserEnabled: null,
            commentsTeaserEnabled: null,
            teaserUrl: null
        },

        /**
         * Initialize view.
         *
         * @returns {Component} Chainable.
         */
        initialize: function initialize() {
            this._super();

            this.observe(['reviewsData']);

            if (this.teaserSku !== null && (this.qaEnabled === "true" || this.reviewsEnabled === "true")) {
                this.loadTeaserCounts(this.teaserSku);
            }

            // Map bridge the tabs widget to something we can manually call
            jQuery.widget.bridge('mage_tabs', jQuery.mage.tabs);
            this.tabsContainer = document.querySelector('.product.data.items');

            return this;
        },

        loadTeaserCounts: function loadTeaserCounts(sku) {
            var xhr = new XMLHttpRequest();

            xhr.open('GET', this.teaserUrl + this.siteKey + '/' + sku + '/d/ugc/counts/en_US', true);
            xhr.addEventListener('load', function () {
                if (!xhr.responseText) {
                    return;
                }
                this.reviewsData(JSON.parse(xhr.responseText));
            }.bind(this));
            xhr.send();
        },

        getNumFullStars: function getFullStars() {
            // this ends up being oddly complicated because we essentially want to do rounding, but also round to the
            //    nearest half
            return (this.reviewsData().avgRating - Math.floor(this.reviewsData().avgRating)) >= 0.75 ?
                Math.round(this.reviewsData().avgRating) :
                Math.floor(this.reviewsData().avgRating);
        },

        hasHalfStar: function hasHalfStar() {
            let halfStarValue = (this.reviewsData().avgRating - this.getNumFullStars()).toFixed(2);
            return halfStarValue > 0.25 && halfStarValue <= .75;
        },

        getNumEmptyStars: function getNumEmptyStars() {
            return (5 - (this.getNumFullStars() + (this.hasHalfStar() ? 1 : 0)));
        },

        getStarDescriptionString: function getStarDescriptionString() {
            let fullStars = this.getNumFullStars();
            let halfStar = this.hasHalfStar();
            let halfStarString = halfStar ? " and a half " : " ";

            return "Rating: " + fullStars + halfStarString + "Stars";
        },

        writeReview: function writeReview() {
            window.TurnToCmd('reviewsList.writeReview');
        },

        getTabIndex: function getTabIndex(tabAnchor) {
            /** @type Array.<Element> */
            var tabs = Array.prototype.slice.call(this.tabsContainer.querySelectorAll('.title a'));

            // Start at the end going backwards so that if none are found we default to opening the first tab
            var tabIndex = tabs.length - 1;

            // Initializer intentionally left out for block scoping. Doesn't apply to var, but better practice to block
            // scope always, even if there is no practical effect
            for (; tabIndex >= 0; tabIndex--) {
                if (tabs[tabIndex].getAttribute('href') === tabAnchor) {
                    break;
                }
            }

            return tabIndex;
        },

        openTab: function openTab(tabAnchor) {
            jQuery(this.tabsContainer).mage_tabs('activate', this.getTabIndex(tabAnchor));
            this.tabsContainer.scrollIntoView();
        },
    });
});
