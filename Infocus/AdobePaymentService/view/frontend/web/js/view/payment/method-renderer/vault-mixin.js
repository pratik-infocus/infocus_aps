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

define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    return function (VaultComponent) {
        return VaultComponent.extend({
            defaults: {
                template: 'Infocus_AdobePaymentService/payment/vault-checkout',
            },

            /**
             * Get expiration date
             * @returns {String}
             */
            getExpirationDate: function () {
                var showExpiry = parseFloat(window.checkoutConfig.is_expiry_date_visible);
                if(showExpiry == 1)
                {
                    return this.details.expirationDate;
                }
                else{
                    return '';
                }
            },
        });
    };
});
