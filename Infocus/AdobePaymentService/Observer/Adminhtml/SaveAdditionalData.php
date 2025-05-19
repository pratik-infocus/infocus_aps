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

namespace Infocus\AdobePaymentService\Observer\Adminhtml;

use Magento\Framework\Event\Observer;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\PaymentServicesBase\Model\Config;
use Magento\PaymentServicesPaypal\Observer\SaveAdditionalData as OriginalSaveAdditionalData;

class SaveAdditionalData extends OriginalSaveAdditionalData
{
    private const PAYMENT_MODE_KEY = 'payments_mode';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var string[]
     */
    private $additionalInformationList = [
        'payments_order_id',
        'paypal_order_id',
        'payment_source'
    ];

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Save additional data to payment.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $data = $this->readDataArgument($observer);
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        $paymentInfo = $this->readPaymentModelArgument($observer);
        $quote = $paymentInfo->getQuote();
        if ($quote) {
            $storeId = $quote->getStore()->getStoreId();
        } else {
            $order = $paymentInfo->getOrder();
            if ($order) {
                $storeId = $order->getStore()->getId();
            }
            else
            {
                $storeId = $paymentInfo->getQuote()->getStore()->getStoreId();
            }
        }

        $paymentInfo->setAdditionalInformation(
            self::PAYMENT_MODE_KEY,
            $this->config->getEnvironmentType($storeId)
        );
        if (!is_array($additionalData)) {
            return;
        }
        foreach ($this->additionalInformationList as $additionalInformationKey) {
            if (isset($additionalData[$additionalInformationKey])) {
                $paymentInfo->setAdditionalInformation(
                    $additionalInformationKey,
                    $additionalData[$additionalInformationKey]
                );
            }
        }
    }
}
