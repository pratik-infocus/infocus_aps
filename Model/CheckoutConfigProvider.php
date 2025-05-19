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

namespace Infocus\AdobePaymentService\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class CheckoutConfigProvider
 * @package Infocus\AdobePaymentService\Model
 */
class CheckoutConfigProvider implements ConfigProviderInterface
{
    /**
	 * @var ScopeConfigInterface
	 */
    protected $scopeConfig;

    /**
	 * @var StoreManagerInterface
	 */
    protected $storeManager;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(ScopeConfigInterface $scopeConfig, StoreManagerInterface $storeManager)
    {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    public function getConfig()
    {
        return [
            'is_expiry_date_visible' => $this->scopeConfig->getValue(
                'infocus_partialpayments/settings/show_cards_expiry_date_customer_account',
                ScopeInterface::SCOPE_STORE, $this->storeManager->getStore()->getId())
        ];
    }
}
