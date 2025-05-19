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
    'ko',
    'jquery',
    'uiComponent',
    'Infocus_AdobePaymentService/js/model/payment-service',
    'Magento_Ui/js/model/messages',
    'uiLayout',
    'partialInvoiceData',
    'placePartialInvoice',
    'partialLoader',
    'partialValidate',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/action/select-payment-method',

], function (
    ko,
    $,
    Component,
    paymentService,
    Messages,
    layout,
    partialInvoiceData,
    placePartialInvoice,
    partialLoader,
    partialValidate,
    additionalValidators
) {
    'use strict';

    return Component.extend({
        isPlaceOrderActionAllowed: ko.observable(false),
        defaults:{
            partialForm: '[data-role="partially-form"]'
        },
        /**
         * Initialize view.
         *
         * @return {exports}
         */
        initialize: function () {
            this._super().initChildren();
            this.bind();

            return this;
        },

        /**
         * Bind events
         */
        bind: function () {
            var self = this;
            self.isPlaceOrderActionAllowed(true);
            $(document).on('PARTIAL_METHODS_DISABLED', function () {
                self.isPlaceOrderActionAllowed(false);
            });

            $(document).on('PARTIAL_METHODS_ENABLED', function () {
                self.isPlaceOrderActionAllowed(true);
            });
        },

        /**
         * Initialize child elements
         *
         * @returns {Component} Chainable.
         */
        initChildren: function () {
            this.messageContainer = new Messages();
            this.createMessagesComponent();

            return this;
        },

        /**
         * Create child message renderer component
         *
         * @returns {Component} Chainable.
         */
        createMessagesComponent: function () {
            var messagesComponent = {
                parent: this.name,
                name: this.name + '.messages',
                displayArea: 'messages',
                component: 'Magento_Ui/js/view/messages',
                config: {
                    messageContainer: this.messageContainer
                }
            };

            layout([messagesComponent]);

            return this;
        },

        /**
         * @return {Boolean}
         */
        selectPaymentMethod: function () {
            partialInvoiceData.paymentMethod(this.getData());
            partialInvoiceData.setSelectedPaymentMethod(this.item.method);
            return true;
        },

        isChecked: ko.computed(function () {
            return partialInvoiceData.getSelectedPaymentMethod() || null;
        }),

        isRadioButtonVisible: ko.computed(function () {
            return paymentService.getAvailablePaymentMethods().length !== 1;
        }),

        /**
         * Get payment method type.
         */
        getTitle: function () {
            return this.item.title;
        },

        /**
         * Place order.
         */
        placeOrder: function () {
            if (!$(this.partialForm).partialValidate('validateInvoices')) {
                return false;
            }
            var paymentData = this.getData(),

                payData = {
                    method: paymentData.method,
                    payments_order_id: paymentData.additional_data.payments_order_id,
		            paypal_order_id: paymentData.additional_data.paypal_order_id,
                    public_hash: paymentData.additional_data.public_hash
                };
	        partialLoader.loaderStart();
            placePartialInvoice.placePartialInvoice(payData);
        },

        /**
         * Get payment method data
         */
        getData: function () {
            return {
                'method': this.item.method,
                'po_number': null,
                'additional_data': null
            };
        },

        /**
         * @return {*}
         */
        getPlaceOrderDeferredObject: function () {
            return $.when(
                placeOrderAction(this.getData(), this.messageContainer)
            );
        },
    });
});
