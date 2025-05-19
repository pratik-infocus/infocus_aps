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
/* eslint new-cap: [1] */
/* global Class, AdminInvoicePayment, Event, alert, FORM_KEY, $, Ajax, $H, Form, productConfigure, setLocation, order */
define([
    'jquery',
    'mage/template',
    'patialInvoiceState',
    'transparent',
    'prototype'
], function (jQuery, mageTemplate, partialState) {
    window.AdminInvoicePayment = new Class.create();

    AdminInvoicePayment.prototype = {
        isOnlineMethod: false,
        initialize: function (data) {
            this.data = data || {};
            this.excludedPaymentMethods = [];
            this.customerId = data.customer_id ? data.customer_id : false;
            this.storeId = data.store_id ? data.store_id : false;
            this.options = this.defaultOptions = {
                editFormSelector: '#edit_form',
                hiddenFormTmpl:
                '<form target="<%= data.target %>" action="<%= data.action %>"' +
                'method="POST" hidden' +
                'enctype="application/x-www-form-urlencoded" class="no-display">' +
                '<% _.each(data.inputs, function(val, key){ %>' +
                '<input value="<%= val %>" name="<%= key %>" type="hidden">' +
                '<% }); %>' +
                '</form>',
                cgiUrl: null,
                orderSaveUrl: null,
                controller: null,
                gateway: null,
                dateDelim: null,
                cardFieldsMap: null,
                expireYearLength: 2
            };

            if (jQuery('#capture_case')) {
                this.rebuildCaptureCaseSelect();
                jQuery('#capture_case').on('change', function (event) {
                    var elem = jQuery(event.currentTarget),
                        captureCase = elem.val(),
                        methodName,
                        availableMethod,
                        allowedMethods;

                    if (captureCase != '' && typeof this.data.capture_data[captureCase] !== 'undefined') {
                        availableMethod = null;
                        allowedMethods = this.data.capture_data[captureCase];
                        if (captureCase == 'offline') {
                            jQuery('.submit-button.primary').prop('disabled', false);
                            jQuery('#invoice-billing_method-pay_amount').hide();
                        }
                        jQuery('#invoice_payment_form').find('[name="payment[method]"]').each(function (i, element) {
                            methodName = jQuery(element).val();
                            if (allowedMethods.indexOf(methodName) > -1) {
                                availableMethod = methodName;
                                jQuery('#invoice_payment_form .payment_method_' + methodName).show();
                                jQuery(element).attr('disabled', false);
                                if (jQuery(element).is(':checked')) {
                                    jQuery('#invoice_payment_form .payment_method_' + methodName)
                                        .find('input', 'select', 'textarea').attr('disabled', false);
                                }
                            } else {
                                if (methodName == this.paymentMethod) {
                                    this.paymentMethod = null;
                                }
                                jQuery('#invoice_payment_form .payment_method_' + methodName).hide()
                                    .find('input', 'select', 'textarea').attr('disabled', true);
                            }
                        }, this);
                        if (this.paymentMethod == null && availableMethod != null) {
                            jQuery('input[name="payment[method]"]').first().trigger('click');
                        }
                    }
                    else if (captureCase == '') {
                        jQuery('#invoice_payment_form').find('[name="payment[method]"]').each(function (i, element) {
                            methodName = jQuery(element).val();
                            jQuery('#invoice_payment_form .payment_method_' + methodName).show();
                            jQuery('#invoice-billing_method-pay_amount').show();
                            jQuery(element).attr('disabled', false);
                        }, this);
                    }
                    this.isOnlineMethod = !!allowedMethods;
                }.bind(this));
            }

            this.hiddenFormTmpl = mageTemplate(this.options.hiddenFormTmpl);
            this.bindChangeParialState();
        },

        bindChangeParialState: function () {
            partialState.isReadyToPay.subscribe(this.disableSubmit, this);
        },

        submitInvoice: function (event) {
            var form = jQuery('#edit_form');
            jQuery(document).trigger('partial.beforeSubmitInvoice', [event, form, this.isOnlineMethod, this.paymentMethod]);

            if (form.valid()) {
                if (this.isZeroPayment()) {
                    form.trigger('submit');
                    this.disableSubmitForm(form);
                } else {
                    this._invoiceSave(event, form);
                }
            } else {
                jQuery('body').trigger('processStop');
                event.preventDefault();
            }
        },

        _invoiceSave: function (event, form) {
            if (!this.options.orderSaveUrl || this.paymentMethod === 'partial_payment') {
                this.disableSubmitForm(form);
                return;
            }
            this.disableSubmit(jQuery(event.currentTarget));
            event.preventDefault();
            jQuery('body').trigger('processStart');
            var partialInvoiceSumInput = jQuery('#order-pay_amount-input'),
                sendEmailCheckbox = jQuery('#send_email'),
                sendAdditionalEmailCheckbox = jQuery('#send_additional_invoice_email'),
                partialInvoiceControl = jQuery('#order-pay_amount-control'),
                postData = {
                    'order_id': this.orderId,
                    'form_key': FORM_KEY,
                    'cc_type': this.ccType()
                };
            if (partialInvoiceControl && partialInvoiceControl.val() === '1') {
                postData['payment[pay_amount]'] = partialInvoiceSumInput.val();
            }

            if (sendEmailCheckbox.length) {
                postData['send_email'] = sendEmailCheckbox.is(':checked') ? 1  : 0
            }

            if (sendAdditionalEmailCheckbox.length) {
                postData['send_additional_invoice_email'] = sendAdditionalEmailCheckbox.is(':checked') ? 1 : 0
            }

            jQuery.ajax({
                url: this.options.orderSaveUrl,
                type: 'post',
                context: this,
                data: postData,
                dataType: 'json',

                /**
                 * Success callback
                 * @param {Object} response
                 */
                success: function (response) {
                    if (response.success && response[this.options.gateway]) {
                        this._postPaymentToGateway(response);
                    } else {
                        this._processErrors(response);
                    }
                }
            });
        },

        disableSubmit: function (isDisable) {
            jQuery(this.options.editFormSelector + ' .submit-button').prop('disabled', isDisable);
        },

        disableSubmitForm: function (form) {
            form.on('submit', function () {
                partialState.isReadyToPay(true);
            });
        },

        /**
         * Processing errors
         *
         * @param {Object} response
         * @private
         */
        _processErrors: function (response) {
            var msg = response['error_messages'];
            jQuery('body').trigger('processStop');
            if (typeof msg === 'object') {
                alert({
                    content: msg.join('\n')
                });
            }

            if (msg) {
                alert({
                    content: msg
                });
            }
        },

        /**
         * Post data to gateway for credit card validation.
         *
         * @param {Object} response
         * @private
         */
        _postPaymentToGateway: function (response) {
            var $iframeSelector = jQuery('[data-container="' + this.options.gateway + '-transparent-iframe"]'),
                data,
                tmpl,
                iframe;

            data = this._preparePaymentData(response);
            tmpl = this.hiddenFormTmpl({
                data: {
                    target: $iframeSelector.attr('name'),
                    action: this.options.cgiUrl,
                    inputs: data
                }
            });

            iframe = $iframeSelector
                .on('submit', function (event) {
                    event.stopPropagation();
                });
            jQuery(tmpl).appendTo(iframe).submit();
            iframe.html('');
        },

        /**
         * Add credit card fields to post data for gateway.
         *
         * @param {Object} response
         * @private
         */
        _preparePaymentData: function (response) {
            var ccfields,
                data,
                preparedata,
                holder;

            data = response[this.options.gateway].fields;
            ccfields = this.options.cardFieldsMap;

            if (this.element.find('[data-container="' + this.options.gateway + '-cc-cvv"]').length) {
                data[ccfields.cccvv] = this.element.find(
                    '[data-container="' + this.options.gateway + '-cc-cvv"]'
                ).val();
            }
            preparedata = this._prepareExpDate();
            data[ccfields.ccexpdate] = preparedata.month + this.options.dateDelim + preparedata.year;
            data[ccfields.ccnum] = this.element.find(
                '[data-container="' + this.options.gateway + '-cc-number"]'
            ).val();

            if (this.options.gateway !== 'securepay_admin') {
                return data;
            }

            preparedata = this._prepareExpDate();
            data['EPS_EXPIRYMONTH'] = preparedata.month;
            data['EPS_EXPIRYYEAR'] = preparedata.year;

            holder = this.element.find('[data-container="' + this.options.gateway + '-cc-holder"]').val();
            if (holder) {
                data['EPS_PAYORREF'] = holder.replace(/ /g, '').substring(0, 29);
            } else {
                data['EPS_PAYORREF'] = '';
            }

            return data;
        },

        /**
         * Grab Month and Year into one
         * @returns {Object}
         * @private
         */
        _prepareExpDate: function () {
            var year = this.element.find('[data-container="' + this.options.gateway + '-cc-year"]').val(),
                month = parseInt(
                    this.element.find('[data-container="' + this.options.gateway + '-cc-month"]').val(), 10
                );

            if (year.length > this.options.expireYearLength) {
                year = year.substring(year.length - this.options.expireYearLength);
            }

            if (month < 10) {
                month = '0' + month;
            }

            return {
                month: month, year: year
            };
        },

        /**
         * @returns {String}
         */
        ccType: function () {
            return this.element.find(
                '[data-container="' + this.options.gateway + '-cc-type"]'
            ).val();
        },

        rebuildCaptureCaseSelect: function () {
            /**
             * @TODO implement rebuilding capture case select if there are no payments, which are allowing all options
             */
        },

        switchPaymentMethod: function (method) {
            jQuery('#edit_form').trigger('changePaymentMethod', [method]);
            this.setPaymentMethod(method);
            var data = {};
            data['order[payment_method]'] = method;
            this.loadArea(['card_validation'], true, data);
        },

        setPaymentMethod: function (method) {
            var form,
                widget;
            if (this.paymentMethod && $('payment_form_' + this.paymentMethod)) {
                form = 'payment_form_' + this.paymentMethod;
                [form + '_before', form, form + '_after'].each(function (el) {
                    var block = $(el);
                    if (block) {
                        block.hide();
                        block.select('input', 'select', 'textarea').each(function (field) {
                            field.disabled = true;
                        });
                    }
                });
            }

            if (!this.paymentMethod || method) {
                $('invoice_payment_form').select('input', 'select', 'textarea').each(function (elem) {
                    if (elem.type != 'radio') elem.disabled = true;
                });
            }

            if ($('payment_form_' + method)) {
                this.paymentMethod = method;
                form = 'payment_form_' + method;
                [form + '_before', form, form + '_after'].each(function (el) {
                    var block = $(el);
                    if (block) {
                        block.show();
                        block.select('input', 'select', 'textarea').each(function (field) {
                            field.disabled = false;
                            if (!el.include('_before') && !el.include('_after') && !field.bindChange) {
                                field.bindChange = true;
                                field.paymentContainer = form;
                                field.method = method;
                                field.observe('change', function (event) {
                                    var elem = Event.element(event),
                                        data;
                                    if (elem && elem.method) {
                                        data = this.getPaymentData(elem.method);
                                        if (data) {
                                            this.loadArea(['card_validation'], true, data);
                                        } else {
                                            return;
                                        }
                                    }
                                }.bind(this));
                            }
                        }, this);
                    }
                }, this);
                var captureData = this.data.capture_data;
                if (jQuery('#capture_case').length && captureData) {
                    for (var prop in captureData) {
                        if (captureData.hasOwnProperty(prop) && captureData[prop].length) {
                            if (captureData[prop].some(function (mthd) {
                                return mthd === method;
                            })) {
                                jQuery('#capture_case option[value="' + prop + '"]').prop('disabled', false);
                            } else {
                                jQuery('#capture_case option[value="' + prop + '"]').prop('disabled', true);
                            }
                        }
                    }
                }
            }
            this.element = jQuery('#payment_form_' + this.paymentMethod);
            widget = this.element.data('transparent');
            if (widget && widget.options) {
                this.options = jQuery.extend(this.options, widget.options);
            } else {
                this.options = this.defaultOptions;
            }
        },

        getAreaId: function (area) {
            return 'order-' + area;
        },

        serializeData: function (container) {
            var fields = $(container).select('input', 'select', 'textarea'),
                data = Form.serializeElements(fields, true);

            return $H(data);
        },

        /**
         * Prevent from sending credit card information to server for some payment methods
         *
         * @returns {boolean}
         */
        isPaymentValidationAvailable: function () {
            return ((typeof this.paymentMethod) === 'undefined' || this.excludedPaymentMethods.indexOf(this.paymentMethod) == -1);
        },

        addExcludedPaymentMethod: function (method) {
            this.excludedPaymentMethods.push(method);
        },

        getPaymentData: function (currentMethod) {
            if (typeof (currentMethod) === 'undefined') {
                if (this.paymentMethod) {
                    currentMethod = this.paymentMethod;
                } else {
                    return false;
                }
            }
            if (this.isPaymentValidationAvailable() == false) {
                return false;
            }
            var data = {},
                fields = $('payment_form_' + currentMethod).select('input', 'select'),
                i;
            for (i = 0; i < fields.length; i++) {
                data[fields[i].name] = fields[i].getValue();
            }
            if ((typeof data['payment[cc_type]']) !== 'undefined' && (!data['payment[cc_type]'] || !data['payment[cc_number]'])) {
                return false;
            }
            return data;
        },

        loadArea: function (area, indicator, params) {
            var deferred = new jQuery.Deferred(),
                url = this.loadBaseUrl;
            if (area) {
                area = this.prepareArea(area);
                url += 'block/' + area;
            }
            if (indicator === true) indicator = 'html-body';
            params = this.prepareParams(params);
            params.json = true;
            if (!this.loadingAreas) this.loadingAreas = [];
            if (indicator) {
                this.loadingAreas = area;
                new Ajax.Request(url, {
                    parameters: params,
                    loaderArea: indicator,
                    onSuccess: function (transport) {
                        var response = transport.responseText.evalJSON();
                        this.loadAreaResponseHandler(response);
                        deferred.resolve();
                    }.bind(this)
                });
            } else {
                new Ajax.Request(url, {
                    parameters: params,
                    loaderArea: indicator,
                    onSuccess: function (transport) {
                        deferred.resolve();
                    }
                });
            }
            if (typeof productConfigure !== 'undefined' && area instanceof Array && area.indexOf('items') != -1) {
                productConfigure.clean('quote_items');
            }
            return deferred.promise();
        },

        setLoadBaseUrl: function (url) {
            this.loadBaseUrl = url;
        },

        setOrderId: function (id) {
            this.orderId = id;
        },

        loadAreaResponseHandler: function (response) {
            var i, id;
            if (response.error) {
                alert({
                    content: response.message
                });
            }
            if (response.ajaxExpired && response.ajaxRedirect) {
                setLocation(response.ajaxRedirect);
            }
            if (!this.loadingAreas) {
                this.loadingAreas = [];
            }
            if (typeof this.loadingAreas === 'string') {
                this.loadingAreas = [this.loadingAreas];
            }
            if (this.loadingAreas.indexOf('message') == -1) {
                this.loadingAreas.push('message');
            }
            if (response.header) {
                jQuery('.page-actions-inner').attr('data-title', response.header);
            }

            for (i = 0; i < this.loadingAreas.length; i++) {
                id = this.loadingAreas[i];
                if ($(this.getAreaId(id))) {
                    if (id != 'message' || response[id]) {
                        $(this.getAreaId(id)).update(response[id]);
                    }
                    if ($(this.getAreaId(id)).callback) {
                        this[$(this.getAreaId(id)).callback]();
                    }
                }
            }
        },

        prepareArea: function (area) {
            if (this.giftMessageDataChanged) {
                return area.without('giftmessage');
            }
            return area;
        },

        saveData: function (data) {
            this.loadArea(false, false, data);
        },

        showArea: function (area) {
            var id = this.getAreaId(area);
            if ($(id)) {
                $(id).show();
                this.areaOverlay();
            }
        },

        hideArea: function (area) {
            var id = this.getAreaId(area);
            if ($(id)) {
                $(id).hide();
                this.areaOverlay();
            }
        },

        areaOverlay: function () {
            $H(order.overlayData).each(function (e) {
                e.value.fx();
            });
        },

        prepareParams: function (params) {
            if (!params) {
                params = {};
            }
            if (!params.customer_id) {
                params.customer_id = this.customerId;
            }
            if (!params.store_id) {
                params.store_id = this.storeId;
            }
            if (!params.currency_id) {
                params.currency_id = this.currencyId;
            }
            if (!params.form_key) {
                params.form_key = FORM_KEY;
            }
            if (this.isPaymentValidationAvailable()) {
                var data = this.serializeData('invoice_payment_form');
                if (data) {
                    data.each(function (value) {
                        params[value[0]] = value[1];
                    });
                }
            } else {
                params['payment[method]'] = this.paymentMethod;
            }
            return params;
        },
        isZeroPayment: function () {
            var $zeroPayment = jQuery('#invoice-zero-payment');
            if ($zeroPayment.length && $zeroPayment.val() === '1') {
                return true;
            }
            return false;
        }
    };
});
