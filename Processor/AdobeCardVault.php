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

namespace Infocus\AdobePaymentService\Processor;

use Infocus\PartialPayments\Model\Payment\MyAccount\Processor\PaymentProcessorInterface;
use Magento\Framework\Exception\LocalizedException;
use Infocus\AdobePaymentService\Processor\AbstractProcessor;

/**
 * Class AdobeCardVault
 * @package Infocus\AdobePaymentService\Processor
 */
class AdobeCardVault extends AbstractProcessor implements PaymentProcessorInterface
{
    const CUSTOMER_ID = 'customer_id';

    /**
     * @param array $preparedPayment
     * @param array $originalRequest
     * @throws LocalizedException
     * @return mixed
     */
    public function process(array $preparedPayment, array $originalRequest = [])
    {
        $preparedPayment['payment']['additional_information'][self::VAULT_ENABLER] = '1';
        $preparedPayment['payment']['additional_information'][self::CUSTOMER_ID] = $this->session->getCustomerId();
        return parent::process($preparedPayment, $originalRequest);
    }
}
