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

/* eslint-disable no-undef */
define([
    'jquery',
    'underscore',
    'mage/translate',
    'Infocus_AdobePaymentService/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'Infocus_PartialPayments/js/action/partial-loader',
    'Infocus_AdobePaymentService/js/view/payment/methods/hosted-fields',
    'Magento_PaymentServicesPaypal/js/view/errors/response-error',
    'Magento_Checkout/js/action/set-billing-address',
    'Magento_Ui/js/model/messageList',
    'Magento_Vault/js/view/payment/vault-enabler'
], function (
    $,
    _,
    $t,
    Component,
    quote,
    loader,
    HostedFields,
    ResponseError,
    setBillingAddressAction,
    globalMessageList,
    VaultEnabler
) {
    'use strict';

    return Component.extend({
        defaults: {
            isFormValid: false,
            invalidFields: [],
            isAvailable: false,
            isFormRendered: false,
            fields: {
                number: {
                    class: 'card-number-field',
                    label: $t('Credit Card Number'),
                    errorMessage: $t('Please enter a valid credit card number.'),
                    selector: '#${ $.formId } .${ $.fields.number.class }',
                    placeholder: ''
                },
                expirationDate: {
                    class: 'expiration-date-field card-field-short',
                    selector: '#${ $.formId } .expiration-date-field',
                    label: $t('Expiration Date'),
                    errorMessage: $t('Incorrect credit card expiration date.'),
                    placeholder: 'MM/YY'
                },
                cvv: {
                    class: 'cvv-field card-field-short',
                    selector: '#${ $.formId } .cvv-field',
                    label: $t('Card Security Code'),
                    errorMessage: $t('Please enter a valid credit card security code.'),
                    tooltip: {
                        title: $t('What is this?'),
                        contentUnsanitizedHtml: '<img src="${ $.cvvImgUrl }" ' +
                            'alt="${ $.cvvTitle }" title="${ $.cvvTitle }" />'
                    },
                    placeholder: ''
                }
            },
            cards: {
                AE: {
                    eligibilityCode: 'amex',
                    typeCode: 'american-express'
                },
                DI: {
                    eligibilityCode: 'discover',
                    typeCode: 'discover'
                },
                ELO: {
                    eligibilityCode: 'elo',
                    typeCode: 'elo'
                },
                HC: {
                    eligibilityCode: 'hiper',
                    typeCode: 'hiper'
                },
                JCB: {
                    eligibilityCode: 'jcb',
                    typeCode: 'jcb'
                },
                MC: {
                    eligibilityCode: 'mastercard',
                    typeCode: 'master-card'
                },
                VI: {
                    eligibilityCode: 'visa',
                    typeCode: 'visa'
                }
            },
            availableCards: [],
            threeDSMode: window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].threeDS,
            createOrderUrl: window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].createOrderUrl,
            ccIcons: window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].ccIcons,
            paymentSource: window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].paymentSource,
            cvvImgUrl:  window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].cvvImageUrl,
            scriptParams:  window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].sdkParams,
            isCommerceVaultEnabled: window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].isCommerceVaultEnabled, // eslint-disable-line max-len
            emptyErrorMessage: $t('This is a required field.'),
            cvvTitle: $t('The card security code is a three or four digit number printed on a credit card. Visa, Mastercard, and Discover cards have a three digit code on the card back. American Express cards have a four digit code on the card front.'), // eslint-disable-line max-len
            paymentMethodValidationError: $t('Your payment was not successful. Ensure you have entered your details correctly and try again, or try a different payment method. If you have continued problems, contact the issuing bank for your payment method.'), // eslint-disable-line max-len
            notEligibleErrorMessage: $t('This payment option is currently unavailable.'),
            generalErrorMessage: '${ $.paymentMethodValidationError }',
            placeOrderTitle: $t('Pay now'),
            formId: 'hosted-fields-form',
            template: 'Infocus_AdobePaymentService/payment/credit-card',
            ccType: '',
            billingAddress: quote.billingAddress,
            paymentsOrderId: null,
            paypalOrderId: null,
            hostedFields: null,
            shouldCardBeVaulted: false,
            paymentTypeIconUrl:  window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].paymentTypeIconUrl,
            paymentTypeIconTitle: $t('Pay with credit card')
        },

        /** @inheritdoc */
        initialize: function () {
            // config

            _.bindAll(this, 'onSuccess', 'onError', 'afterHostedFieldsRender', 'onOrderSuccess', 'beforeCreateOrder');
            this._super();
            this.initHostedFields();
            this.vaultEnabler = new VaultEnabler();
            this.vaultEnabler.isActivePaymentTokenEnabler(false);
            this.vaultEnabler.setPaymentCode(window.checkoutConfig.payment[this.getCode()].ccVaultCode);

            return this;
        },

        /**
         * Initialize Hosted Fields.
         */
        initHostedFields: function () {
            this.hostedFields = new HostedFields({
                formId: this.formId,
                fields: this.fields,
                scriptParams: this.scriptParams,
                onOrderSuccess: this.onOrderSuccess,
                createOrderUrl: this.createOrderUrl,
                shouldCardBeVaulted: this.shouldCardBeVaulted,
                paymentSource: this.paymentSource
            });
        },

        /** @inheritdoc */
        initObservable: function () {
            this._super()
                .observe('billingAddress paymentsOrderId paypalOrderId ' +
                    'ccType isFormValid invalidFields availableCards isAvailable isFormRendered shouldCardBeVaulted');

            return this;
        },

        /** @inheritdoc */
        getCode: function () {
            return 'payment_services_paypal_hosted_fields';
        },

        /** @inheritdoc */
        getData: function () {
            var data = this._super();

            data['additional_data'] = {
                payments_order_id: this.paymentsOrderId(),
                paypal_order_id: this.paypalOrderId()
            };

            this.vaultEnabler.visitAdditionalData(data);
            return data;
        },

        /**
         * Get payment related data.
         *
         * @return {Object}
         */
        getPaymentData: function () {
            var paymentData = {
                vault: this.isCommerceVaultEnabled && this.checkShouldCardBeVaulted(),
            };

            if (this.threeDSMode) {
                paymentData.contingencies = [this.threeDSMode];
            }
            return paymentData;
        },

        /** @inheritdoc */
        afterRender: function () {
            this.$form = $('#' + this.formId);

            this.hostedFields.sdkLoaded.then(function () {
                this.isAvailable(this.hostedFields.isEligible());

                if (this.isAvailable()) {
                    this.hostedFields.render()
                        .then(this.afterHostedFieldsRender.bind(this));
                }
            }.bind(this)).catch(function () {
                this.isAvailable(false);
            }.bind(this)).finally(function () {
                this.isFormRendered(true);
            }.bind(this));
        },

        /**
         * Bind events after hostedFields rendered.
         *
         * @param {Object} hostedFields
         */
        afterHostedFieldsRender: function (hostedFields) {
            this.processAvailableCards(hostedFields.getCardTypes());
            hostedFields.on('cardTypeChange', this.onCardTypeChange.bind(this, hostedFields));
            hostedFields.on('validityChange', this.onValidityChange.bind(this, hostedFields));
            hostedFields.on('blur', this.validateField.bind(this, hostedFields));
            hostedFields.on('inputSubmitRequest', function (e) {
                this.validateField(hostedFields, e);
                this.submitForm(hostedFields);
            }.bind(this));
            this.$form.off('submit');
            this.$form.on('submit', function (e) {
                e.preventDefault();
                loader.loaderStart();
                this.submitForm(hostedFields);
            }.bind(this));
        },

        /**
         * Filter eligible cards, convert to internal codes and set to available cards.
         *
         * @param {Object} cardTypes
         */
        processAvailableCards: function (cardTypes) {
            var cards = _.keys(cardTypes).sort(),
                eligibleCards  = _.chain(cards)
                    .filter(function (ccCode) {
                        return cardTypes[ccCode].eligible;
                    })
                    .map(function (ccCode) {
                        return _.findKey(this.cards, function (val) {
                            return val.eligibilityCode === ccCode;
                        }) || ccCode;
                    }, this)
                    .value();

            this.availableCards(eligibleCards);
        },

        /**
         * Validity change handler.
         *
         * @param {Object} hostedFields
         * @param {Object} event
         */
        onValidityChange: function (hostedFields, event) {
            var invalidFields = _.where(event.fields, {
                isValid: false
            });

            this.isFormValid(!invalidFields.length);
            this.isFormValid() && this.invalidFields([]);
        },

        /**
         * Check if field is valid.
         *
         * @param {String} fieldName
         * @return {Boolean}
         */
        isFieldValid: function (fieldName) {
            return !this.invalidFields.findWhere({
                name: fieldName
            });
        },

        /**
         * Get error message for field.
         *
         * @param {String} fieldName
         * @return {String}
         */
        getFieldErrorMessage: function (fieldName) {
            return !this.isFieldValid(fieldName) ? this.invalidFields.findWhere({
                name: fieldName
            }).message : '';
        },

        /**
         * Validate credit card field.
         *
         * @param {Object} hostedFields
         * @param {Object} event
         */
        validateField: function (hostedFields, event) {
            var fieldName = event.emittedBy,
                fieldValid = event.fields[fieldName].isValid,
                isEmpty = event.fields[fieldName].isEmpty,
                invalidFields = _.filter(this.invalidFields(), function (field) {
                    return field.name !== fieldName;
                });

            if (!fieldValid) {
                invalidFields.push({
                    name: fieldName,
                    message: isEmpty ? this.emptyErrorMessage : this.fields[fieldName].errorMessage
                });
            }

            this.invalidFields(invalidFields);
        },

        /**
         * Card type changes handler.
         *
         * @param {Object} hostedFields
         * @param {Object} event
         */
        onCardTypeChange: function (hostedFields, event) {
            var code = '';

            if (event.cards.length === 1) {
                code = _.findKey(this.cards, function (val) {
                    return val.typeCode === event.cards[0].type;
                });
            }

            this.ccType(code);
        },

        /**
         * Form submit handler
         *
         * @param {Object} hostedFields
         */
        submitForm: function (hostedFields) {
            if (this.isFormValid() && this.isPlaceOrderActionAllowed()) {
                loader.loaderStart();
                hostedFields.submit(
                    this.getPaymentData()
                ).then(this.onSuccess).catch(this.onError).finally(loader.loaderStop());
            }
        },

        /**
         * Before order created.
         *
         * @return {Promise}
         */
        beforeCreateOrder: function () {
            return new Promise(function (resolve, reject) {
                return setBillingAddressAction(globalMessageList).done(resolve).fail(reject);
            });
        },

        /**
         * Success callback for transaction.
         *
         * @param {Object} response
         */
        onSuccess: function (response) {
            if (!this.threeDSMode) {
                this.placeOrder();
                return;
            }

            if (response.liabilityShift === 'POSSIBLE' || response.liabilityShift === undefined) {
                this.placeOrder();
            } else {
                this.onError(new ResponseError(this.paymentMethodValidationError));
            }
        },

        /**
         * On PayPal order creation success.
         *
         * @param {Object} order
         */
        onOrderSuccess: function (order) {
            this.paymentsOrderId(order['mp_order_id']);
            this.paypalOrderId(order.id);
        },

        /**
         * Error callback for transaction.
         */
        onError: function (error) {
            var message = this.generalErrorMessage;

            if (error instanceof ResponseError) {
                message = error.message;
                this.reRender();
            } else if (error['debug_id']) {
                message = this.paymentMethodValidationError;
            }

            this.messageContainer.addErrorMessage({
                message: message
            });

            if (error instanceof Error) {
                console.log(error.toString());
            } else {
                console.log('Error' + JSON.stringify(error));
            }
        },

        /**
         * Re-render hosted fields in case of order creation error.
         */
        reRender: function () {
            this.hostedFields.instance.teardown().then(function () {
                this.hostedFields.destroy();
                this.isFormValid(false);
                this.ccType('');
                this.invalidFields([]);
                this.initHostedFields();
                this.afterRender();
            }.bind(this));
        },

        /**
         * Place order
         */
        placeOrderClick: function () {
            if (this.isPlaceOrderActionAllowed() === true) {
                loader.loaderStart();
                $('#' + this.formId).trigger('submit');
            }
        },

        /**
         * Check if customer checks the "Save for later" box upon checkout
         *
         * @returns {*}
         */
        checkShouldCardBeVaulted: function () {
            const checked = this.vaultEnabler.isActivePaymentTokenEnabler();

            this.shouldCardBeVaulted(checked);
            return checked;
        }

    });
});
