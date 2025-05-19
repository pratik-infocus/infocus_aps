/**
 * Infocus
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Infocus-solution.com license that is
 * available through the world-wide-web at this URL:
 * https://infocus-solution.com/license.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @author Infocus Solutions
 * @copyright Copyright (c) 2024 Infocus (https://infocus-solution.com)
 * @package Partial Payment for Adobe Payment Service for Magento 2
 */

define(
    [
        'Infocus_AdobePaymentService/js/view/payment/default',
        'partialInvoiceData'
    ],
    function (Component, partialInvoiceData) {
        'use strict';
        return Component.extend({
            /**
             * @returns
             */
            selectPaymentMethod: function () {
                partialInvoiceData.setSelectedPaymentMethod(this.getId());
                return true;
            },

            /**
             * @returns {String}
             */
            getToken: function () {
                return '';
            },

            /**
             * @returns {String}
             */
            getId: function () {
                return this.index;
            },

            /**
             * @returns {String}
             */
            getCode: function () {
                return this.code;
            },

            /**
             * Get last 4 digits of card
             * @returns {String}
             */
            getMaskedCard: function () {
                return '';
            },

            /**
             * Get expiration date
             * @returns {String}
             */
            getExpirationDate: function () {
                return '';
            },

            /**
             * Get card type
             * @returns {String}
             */
            getCardType: function () {
                return '';
            },

            /**
             * @param {String} type
             * @returns {Boolean}
             */
            getIcons: function (type) {
                return window.checkoutConfig.payment.ccform.icons.hasOwnProperty(type)
                    ? window.checkoutConfig.payment.ccform.icons[type]
                    : false;
            },

            /**
             * @returns {*}
             */
            getData: function () {
                var data = {
                    method: this.getCode()
                };

                data['additional_data'] = {};
                data['additional_data']['public_hash'] = this.getToken();

                return data;
            }
        });
    }
);
