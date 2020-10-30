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
            //if the reviews are 4.8 or above return 5 else return the reviews
            return (this.reviewsData().avgRating >= 4.75) ? 5 : Math.round(this.reviewsData().avgRating);
        },
        hasHalfStar: function hasHalfStar() {
            let halfStarValue = (this.reviewsData().avgRating - this.getNumFullStars()).toFixed(2);
            return halfStarValue > 0.25 && halfStarValue <= .75;
        },
        getNumEmptyStars: function getNumEmptyStars() {
            return (5 - (this.getNumFullStars() + (this.hasHalfStar() ? 1 : 0)));
        },


    });
});
