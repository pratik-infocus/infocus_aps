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
    'mage/translate',
    'uiComponent',
    'Magento_PaymentServicesPaypal/js/view/errors/response-error',
    'Magento_Ui/js/modal/alert',
    'Magento_PaymentServicesPaypal/js/vault',
    'Infocus_PartialPayments/invoice/create/scripts',
    'prototype'
], function ($, $t, Class, ResponseError, alert,adobevault) {
    'use strict';

    return adobevault.extend({
        defaults: {
            selectedMethod: '#invoice_payment_form [name="payment[method]"]:checked'
        },

        /**
         * Set list of observable attributes
         * @returns {exports.initObservable}
         */
        initObservable: function () {
            var self = this;
            self._super();
            self.$orderForm = $('#edit_form');
            $(document).on('partial.beforeSubmitInvoice', function (e, event, $from) {
               if (self.isSelectedMethod()) {
                    event.preventDefault();
                    self.submitOrder(e);
                }
            });
            return self;
        },

        isSelectedMethod: function () {
            var $selectedMethod = $(this.selectedMethod);
            return $selectedMethod.length && $selectedMethod.val() === this.code;
        },

        createOrder: function () {
            $('body').trigger('processStart');

            var orderData;
            orderData = new FormData();
            orderData.append('payment_source', "payment_services_paypal_vault");
            orderData.append('amount', jQuery('#order-pay_amount-input').val());
            orderData.append('quote_id', jQuery('#order_quote_id').val());
            $("#payment_form_payment_services_paypal_vault").find("div.admin__field").each(function(){
                if($(this).find("input[name='payment[token_switcher]']").is(":checked")){
                    var hashval =$(this).find("input[name='active-public-hash']").val();
                    $("input[name='payment[public_hash]']").val(hashval);
                    $("input.paypal-hash-id").val(hashval);
                }
            });

            return fetch(this.createOrderUrl, {
                method: 'POST',
                headers: {},
                credentials: 'same-origin',
                body: orderData
            }).then(function (res) {
                return res.json();
            }).then(function (data) {
                if (data.response['is_successful']) {
                    return data.response['paypal-order'];
                }
            });
        },
        /**
         * Kick off Commerce order flow
         */
        placeOrder: function () {
            if (this.isSelectedMethod()) {
                this.$orderForm.trigger('submit');
            } else {
                this._super();
            }
        },
    });
});
