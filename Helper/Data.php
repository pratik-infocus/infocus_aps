<?php
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
 * @package Partial Payment module for Magento 2
 */

namespace Infocus\AdobePaymentService\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

/**
 * Class Data
 * @package Infocus\AdobePaymentService\Helper
 */
class Data extends AbstractHelper
{
    const SLEEP_CONFIG_PATH = 'setting/general/sleep';
    const SLEEP_AUTHORIZE_PATH = 'setting/general/authorize_delay';
}
