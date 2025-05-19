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
    'mage/translate',
    'Infocus_AdobePaymentService/js/view/payment/method-renderer/vault/vault',
    'Infocus_PartialPayments/js/action/partial-loader',
    'Magento_PaymentServicesPaypal/js/view/errors/response-error'
], function (
    $t,
    VaultComponent,
    loader,
    ResponseError
) {
   'use strict';

   return VaultComponent.extend({
       defaults: {
           template: 'Infocus_AdobePaymentService/payment/vault',
           paymentSource: 'vault',
           paypalOrderId: null,
           paymentsOrderId: null,
           generalErrorMessage: $t('An error occurred. Refresh the page and try again.'),
           paymentMethodValidationError: $t('Your payment was not successful. Try again.')
       },

       /**
        * Get card brand
        * @returns {String}
        */
       getCardBrand: function () {
           return this.mapCardBrand(this.details.brand);
       },

       /**
        * Map the credit card brand received from PayPal to the Commerce standard
        * @param payPalCardBrand
        * @returns {*}
        */
       mapCardBrand: function (payPalCardBrand) {
           const cardBrandMapping = {
               AMEX: 'AE',
               DISCOVER: 'DI',
               DINERS: 'DN',
               ELO: 'ELO',
               HIPER: 'HC',
               JCB: 'JCB',
               MAESTRO: 'MI',
               MASTER_CARD: 'MC',
               MASTERCARD: 'MC',
               VISA: 'VI'
           };

           return cardBrandMapping[payPalCardBrand];
       },

       /**
        * Get last 4 digits of card
        * @returns {String}
        */
       getMaskedCard: function () {
           return this.details.maskedCC;
       },

        /**
         * Get expiration date
         * @returns {String}
         */
        getExpirationDate: function () {
            var showExpiry = parseFloat(window.cardExpiryConfig);
            if(showExpiry == 1)
            {
                return this.details.expirationDate;
            }
            else{
                return '';
            }
        },

        /**
        * Get PayPal order ID
        */
        getData: function () {
          let data = this._super();

          data['additional_data']['paypal_order_id'] = this.paypalOrderId;
          data['additional_data']['payments_order_id'] = this.paymentsOrderId;
          data['additional_data']['public_hash'] = this.publicHash;
          return data;
       },

       /**
        * Place order
        */
       onPlaceOrder: function () {
           loader.loaderStart();
           this.createOrder()
               .then(function (order) {
                   this.onOrderSuccess(order);
               }.bind(this))
               .then(function () {
                   this.placeOrder();
               }.bind(this))
               .catch(this.onError.bind(this))
               .finally(loader.loaderStop);
       },

       /**
        * Create PayPal order
        * @returns {Promise<any>}
        */
       createOrder: function () {
           var orderData = new FormData();
           orderData.append('payment_source', this.paymentSource);
           orderData.append('form_key', jQuery.mage.cookies.get('form_key'));
           orderData.append('amount', window.checkoutConfig.partialPaymentSum);
           orderData.append('order_increment', jQuery('input[data-role="invoice-trigger"]:checked').val());
           return fetch(this.createOrderUrl, {
               method: 'POST',
               headers: {},
               body: orderData,
               credentials: 'same-origin'
           }).then(function (res) {
               return res.json();
           }).then(function (data) {
               if (data.response['is_successful']) {
                   return data.response['paypal-order'];
               }
           });
       },

       /**
        * populate PayPal order ID and trigger Commerce order flow
        * @param order
        */
       onOrderSuccess: function (order) {
           this.paypalOrderId = order['id'];
           this.paymentsOrderId = order['mp_order_id'];
       },

       /**
        * handle payment error
        * @param error
        */
       onError: function (error) {
           var message = this.generalErrorMessage;

           if (error instanceof ResponseError) {
               message = error.message;
           } else if (error['debug_id']) {
               message = this.paymentMethodValidationError;
           }

           this.messageContainer.addErrorMessage({
               message: message
           });
           console.log(error['debug_id'] ? 'Error' + JSON.stringify(error) : error.toString());
       }
   });
});
