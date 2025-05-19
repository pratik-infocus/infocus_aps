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
    'underscore',
    'mage/translate',
    'uiComponent',
    'Magento_Ui/js/lib/view/utils/dom-observer',
    'Infocus_AdobePaymentService/js/view/payment/methods/admin-hosted-fields',
    'Magento_PaymentServicesPaypal/js/view/errors/response-error',
    'Magento_Ui/js/modal/alert',
    'domReady!'
], function ($, _, $t, Component, domObserver, HostedFields, ResponseError, alert) {
    'use strict';

    return Component.extend({
        defaults: {
            orderFormSelector: '#edit_form',
            messageSelector: '.message',
            cardContainerSelector: '.card-container',
            billingAddressSelectorPrefix: '#order-billing_address_',
            mpOrderIdFieldSelector: '.payment-services-hosted-fields-form #mp-order-id',
            paypalOrderIdSelector: '.payment-services-hosted-fields-form #paypal-order-id',
            styles: {
                '.valid': {
                    'color': 'green'
                },
                '.invalid': {
                    'color': 'red'
                }
            },
            fields: {
                number: {
                    selector: '#card-number',
                    placeholder: '4111 1111 1111 1111'
                },
                cvv: {
                    selector: '#cvv',
                    placeholder: '123'
                },
                expirationMonth: {
                    selector: '#expiration-month',
                    placeholder: 'MM'
                },
                expirationYear: {
                    selector: '#expiration-year',
                    placeholder: 'YY'
                }
            },
            hostedFields: null,
            generalErrorMessage: $t('An error occurred. Refresh the page and try again.'),
            paymentMethodValidationError: $t('Your payment was not successful. Try again.'),
            notEligibleErrorMessage: $t('This payment option is currently unavailable.'),
            shouldCardBeVaulted: false,
            paymentSource: '',
            areHostedFieldsInitialized: false
        },

        /** @inheritdoc */
        initialize: function (config, element) {
            this.element = element;
            _.bindAll(this, 'getPaymentData', 'onOrderSuccess', 'onSuccess', 'onError', 'submitForm',
                'onChangePaymentMethod');
            this._super();
            this.initFormListeners();
            // eslint-disable-next-line no-undef
            if (this.code === order.paymentMethod) {
                this.orderForm.trigger('changePaymentMethod.' + this.code, this.code);
            }
            return this;
        },

        /**
         * Initialize form submit listeners.
         */
        initFormListeners: function () {
            this.orderForm = $(this.orderFormSelector);
            this.orderForm.off('changePaymentMethod.' + this.code)
                .on('changePaymentMethod.' + this.code, this.onChangePaymentMethod);
        },

        /**
         * Reinitialize submitOrder event.
         *
         * @param {Object} event
         * @param {String} method
         */
        onChangePaymentMethod: function (event, method) {
            this.orderForm.off('beforeSubmitOrder.' + this.code);
            if (method === this.code) {
                !this.areHostedFieldsInitialized && this.initHostedFields();
                this.orderForm.on('beforeSubmitOrder.' + this.code, this.submitForm);
            }

            var temp = this.hostedFields;
            var error = this.onError;
            var paymentData = this.getPaymentData();
            var onSuccess = this.onSuccess;
            $(document).on('partial.beforeSubmitInvoice', function (e, event, form, isOnlineMethod, paymentMethod) {
                if (paymentMethod === 'payment_services_paypal_hosted_fields') {
                    event.preventDefault();
                    if (!isOnlineMethod) {
                        throw new Error('Please check invoice capture');
                    } else {
                        if (form.valid()) {
                            $('body').trigger('processStart');
                            temp.instance.submit(paymentData)
                                .then(onSuccess)
                                .catch(error);
                        } else {
                            $('body').trigger('processStop');
                        }
                    }
                } else {

                }
            });
        },

        /**
         * Initialize Hosted Fields.
         */
        initHostedFields: function () {
            $('body').trigger('processStart');
            this.hostedFields = new HostedFields({
                fields: this.fields,
                scriptParams: this.scriptParams,
                onOrderSuccess: this.onOrderSuccess,
                createOrderUrl: this.createOrderUrl,
                shouldCardBeVaulted: this.shouldCardBeVaulted,
                paymentSource: this.paymentSource
            });
            this.render();
        },

        /**
         * Render the Hosted Fields and set event listeners
         */
        render: function () {
            this.hostedFields.sdkLoaded.then(function () {
                if (this.hostedFields.isEligible()) {
                    this.hostedFields.render()
                        .then(function (hostedFields) {
                            this.showFields(true);
                            this.afterHostedFieldsRender(hostedFields);
                            this.areHostedFieldsInitialized = true;
                            $('body').trigger('processStop');
                        }.bind(this));
                } else {
                    throw new Error('Hosted fields is not available');
                }
            }.bind(this)).catch(function () {
                this.showFields(false);
                this.displayEligibilityMessage(true);
                $('body').trigger('processStop');
            }.bind(this));
        },

        /**
         * Display eligibility message.
         *
         * @param {Boolean} show
         */
        displayEligibilityMessage: function (show) {
            var element = $(this.element).find(this.messageSelector);

            element.html(this.notEligibleErrorMessage);
            show ? element.show() : element.hide();
        },

        /**
         * Show/hide fields.
         *
         * @param {Boolean} show
         */
        showFields: function (show) {
            var element = $(this.element).find(this.cardContainerSelector);

            show ? element.show() : element.hide();
        },

        /**
         * Bind events after hostedFields rendered.
         *
         * @param {Object} hostedFields
         */
        afterHostedFieldsRender: function (hostedFields) {
            hostedFields.on('inputSubmitRequest', function () {
                this.orderForm.trigger('submitOrder');
            }.bind(this));
        },

        /**
         * Form submit handler
         *
         * @param {Object} e
         */
        submitForm: function (e) {
            if (this.orderForm.valid()) {
                this.hostedFields.instance.submit(this.getPaymentData())
                    .then(this.onSuccess)
                    .catch(this.onError);
            } else {
                $('body').trigger('processStop');
            }
            e.stopImmediatePropagation();

            return false;
        },

        /**
         * Get address field value.
         *
         * @param {String} selector
         * @return {*|String|jQuery}
         */
        getAddressValue: function (selector) {
            return $(this.billingAddressSelectorPrefix + selector).val();
        },

        /**
         * Get billing address field value.
         *
         * @param {String} selector
         * @return {*|String|jQuery}
         */
        getBillingAddressValue: function (selector) {
            return $(this.billingAddressSelectorPrefix + selector).val();
        },

        /**
         * Get payment related data.
         *
         * @return {Object}
         */
        getPaymentData: function () {
            return {
                cardholderName: this.getBillingAddressValue('name'),
                billingAddress: {
                    streetAddress: this.getBillingAddressValue('street0'),
                    extendedAddress: this.getBillingAddressValue('street1'),
                    region:  this.getBillingAddressValue('region'),
                    locality: this.getBillingAddressValue('city'),
                    postalCode: this.getBillingAddressValue('postcode'),
                    countryCodeAlpha2: this.getBillingAddressValue('country_id')
                }
            };
        },

        /**
         * Success callback for transaction.
         */
        onSuccess: function () {
            this.orderForm.trigger('realOrder');
        },

        /**
         * Log error message.
         *
         * @param {Object} error
         */
        onError: function (error) {
            var message = this.generalErrorMessage;
            if (error instanceof ResponseError) {
                message = error.message;
                this.reRender();
            } else if (error['debug_id']) {
                message = this.paymentMethodValidationError;
            }
            $('body').trigger('processStop');
            alert({
                content: message
            });
        },

        /**
         * Re-render hosted fields in case of order creation error.
         */
        reRender: function () {
            this.hostedFields.instance.teardown().then(function () {
                this.hostedFields.destroy();
                this.initHostedFields();
            }.bind(this));
        },

        /**
         * Set the payment services order ID and PayPal order ID.
         *
         * @param {Object} order
         */
        onOrderSuccess: function (order) {
            $(this.mpOrderIdFieldSelector).val(order['mp_order_id']);
            $(this.paypalOrderIdSelector).val(order.id);
            $('body').trigger('processStop');
            var form = jQuery('#edit_form');
            form.trigger('submit');
        }
    });
});
