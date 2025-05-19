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

/*browser:true*/

/* @api */
define([
    'underscore',
    'uiComponent',
    'mage/translate',
    'Magento_Checkout/js/model/payment/renderer-list',
    'uiLayout',
    'uiRegistry'
], function (_, Component, $t, rendererList, layout, registry) {
    'use strict';

    var vaultGroupName = 'vaultGroup';

    layout([{
        name: vaultGroupName,
        component: 'Magento_Checkout/js/model/payment/method-group',
        alias: 'vault',
        sortOrder: 10,
        title: $t('Stored Cards')
    }]);

    registry.get(vaultGroupName, function (vaultGroup) {
        _.each(window.checkoutConfig.payment.vault, function (config, index) {
            rendererList.push(
                {
                    type: index,
                    config: config.config,
                    component: config.component,
                    group: vaultGroup,

                    /**
                     * Custom payment method types comparator
                     * @param {String} typeA
                     * @param {String} typeB
                     * @return {Boolean}
                     */
                    typeComparatorCallback: function (typeA, typeB) {
                        // vault token items have the same name as vault payment without index
                        return typeA.substring(0, typeA.lastIndexOf('_')) === typeB;
                    }
                }
            );
        });
    });

    /**
     * Add view logic here if needed
     */
    return Component.extend({});
});
