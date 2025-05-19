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

use Magento\Sales\Model\ResourceModel\Order\Handler\State as StateHandler;
use Magento\Store\Model\StoreManagerInterface;
use Infocus\PartialPayments\Helper\Data as HelperData;
use Magento\Sales\Model\Order;

/**
 * Checking order status and adjusting order status before saving
 * Class OrderState
 * @package Infocus\AdobePaymentService\Model
 */
class OrderState extends StateHandler
{
    /**
	 * @var HelperData
	 */
	private $helperData;

    /**
	 * @var StoreManagerInterface
	 */
	private $storeManager;
    /**
     * @param HelperData $helperData
     * @param StoreManagerInterface $storeManager
	 */
	public function __construct(
        HelperData $helperData,
        StoreManagerInterface $storeManager
    ) {
        $this->helperData = $helperData;
        $this->storeManager = $storeManager;
    }
    /**
     * Check order status and adjust the status before save
     *
     * @param Order $order
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function check(Order $order)
    {
        $currentState = $order->getState();
        if ($currentState == Order::STATE_NEW && $order->getIsInProcess()) {
            $payment_method = $order->getPayment()->getMethod();
            if($payment_method == "partial_payment" || $payment_method == "payment_services_paypal_hosted_fields" || $payment_method == "payment_services_paypal_vault")
            {
                $orderStatusAfterFullPayment = $this->helperData->getFullPaymentOrderStatus($this->storeManager->getStore()->getWebsiteId());
                $orderStatusAfterPartialPayment = $this->helperData->getPartialPaymentOrderStatus($this->storeManager->getStore()->getWebsiteId());
                if($order->getTotalDue() == 0)
                {
                    $order->setState($orderStatusAfterFullPayment)->setStatus($orderStatusAfterFullPayment);
                }else{
                    $order->setState(Order::STATE_NEW);
                    $order->setStatus($orderStatusAfterPartialPayment);
                }
            }
            else
            {
                $order->setState(Order::STATE_PROCESSING)->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING));
            }
            $currentState = Order::STATE_NEW;
        }

        if (!$order->isCanceled() && !$order->canUnhold() && !$order->canInvoice()) {
            if (in_array($currentState, [Order::STATE_PROCESSING, Order::STATE_COMPLETE])
                && !$order->canCreditmemo()
                && !$order->canShip()
                && $order->getIsNotVirtual()
            ) {
                $order->setState(Order::STATE_CLOSED)
                    ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_CLOSED));
            } elseif ($currentState === Order::STATE_PROCESSING && !$order->canShip()) {
                $order->setState(Order::STATE_COMPLETE)
                    ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_COMPLETE));
            }
        }
        return $this;
    }
}
