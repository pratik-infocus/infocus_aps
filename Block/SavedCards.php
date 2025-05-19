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
 * @package Partial Payment for Adobe Payment Service for Magento 2
 */

namespace Infocus\AdobePaymentService\Block;

use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;

/**
 * Class SavedCards
 * @package Infocus\AdobePaymentService\Block
 */
class SavedCards extends Template
{
    /**
     *  show expiry on frontend checkout paage
     * @return bool| mixed
     */
    public function getShowExpiryYesNo()
    {
        return $this->_scopeConfig->getValue('infocus_partialpayments/settings/show_cards_expiry_date_customer_account', ScopeInterface::SCOPE_STORE,$this->_storeManager->getStore()->getId());
    }
}
