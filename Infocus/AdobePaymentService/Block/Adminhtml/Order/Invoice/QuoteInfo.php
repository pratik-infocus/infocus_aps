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

namespace Infocus\AdobePaymentService\Block\Adminhtml\Order\Invoice;

use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\Element\Template;
use Magento\Framework\Registry;

/**
 * Class QuoteInfo
 * @package Infocus\AdobePaymentService\Block\Adminhtml\Order\Invoice
 */
class QuoteInfo extends Template
{
    /**
     * @var Registry
     */
    protected $_registry;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        array $data = []
    ) {
        $this->_registry = $registry;
        parent::__construct($context, $data);
    }

    /**
     * @return \Magento\Sales\Model\Order\Invoice
     */
    public function getInvoice()
    {
        return $this->_registry->registry('current_invoice');
    }

    /**
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->getInvoice()->getOrder();
    }

    /**
     * @return int
     */
    public function getQuoteId()
    {
        return $this->getOrder()->getQuoteId();
    }

    /**
     * Get name from billing address
     * @return string
     */
    public function getName()
    {
        $firstName = $this->getOrder()->getBillingAddress()->getFirstname() ?? "";
        $lastName = $this->getOrder()->getBillingAddress()->getLastname() ?? "";
        return $firstName." ". $lastName;
    }

    /**
     * Get street0 from billing address
     * @return string
     */
    public function getStreetAddress()
    {
        $streetLines = $this->getOrder()->getBillingAddress()->getStreet();
        return isset($streetLines[0]) ? $streetLines[0] : '';
    }

    /**
     * Get street1 from billing address
     * @return string
     */
    public function getExtendedAddress()
    {
        $streetLines = $this->getOrder()->getBillingAddress()->getStreet();
        return isset($streetLines[1]) ? $streetLines[1] : '';
    }

    /**
    * Get region from billing address
    * @return string
    */
    public function getRegion()
    {
        return $this->getOrder()->getBillingAddress()->getRegion() ?? "";
    }

    /**
     * Get city from billing address
     * @return string
     */
    public function getLocality()
    {
        return $this->getOrder()->getBillingAddress()->getCity() ?? "";
    }

    /**
     * Get zipcode from billing address
     * @return string
     */
    public function getPostalCode()
    {
        return $this->getOrder()->getBillingAddress()->getPostcode() ?? "";
    }

    /**
     * Get country code from billing address
     * @return string
     */
    public function getCountryCodeAlpha2()
    {
        return $this->getOrder()->getBillingAddress()->getCountryId() ?? "";
    }
}
