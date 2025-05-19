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

var config = {
    config: {
        mixins: {
            'Magento_PaymentServicesPaypal/js/view/payment/method-renderer/vault': {
                'Infocus_AdobePaymentService/js/view/payment/method-renderer/vault-mixin': true
            }
        }
    }
};
