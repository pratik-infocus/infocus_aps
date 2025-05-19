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

namespace Infocus\AdobePaymentService\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Event\Observer;
use Infocus\PartialPayments\Helper\Data as HelperData;
use Magento\PaymentServicesBase\Model\ServiceClientInterface;
use Magento\Sales\Model\Order as OrderModel;

/**
 * Class InvoicePayAfterObserver
 * @package Infocus\AdobePaymentService\Observer
 */
class InvoicePayAfterObserver implements ObserverInterface
{
	/**
	 * @var ServiceClientInterface
	 */
	private $httpClient;

    /**
	 * @var HelperData
	 */
	private $helperData;

    /**
	 * @var StoreManagerInterface
	 */
	private $storeManager;

	/**
	 * @param ServiceClientInterface $serviceClientInterface
     * @param HelperData $helperData
     * @param StoreManagerInterface $storeManager
	 */
	public function __construct(
		ServiceClientInterface $httpClient,
        HelperData $helperData,
        StoreManagerInterface $storeManager
    ) {
		$this->httpClient = $httpClient;
        $this->helperData = $helperData;
        $this->storeManager = $storeManager;
    }

	/**
     * Sets the order status after payment
	 * @param Observer
	 */
    public function execute(Observer $observer)
    {
        $invoice = $observer->getEvent()->getInvoice();
	    $order = $invoice->getOrder();
		$payment_method = $order->getPayment()->getMethod();
		if($payment_method == "partial_payment" || $payment_method == "payment_services_paypal_smart_buttons" || $payment_method == "payment_services_paypal_hosted_fields" || $payment_method == "payment_services_paypal_vault"){
			$internalOrderId = $order->getPayment()->getAdditionalInformation('payments_order_id');
			$websiteId = $order->getStore()->getWebsiteId();

			$requestOrder = [
				'order-id' => $order->getId(),
				'order-increment-id' => $order->getIncrementId()
			];

			$this->httpClient->request(
				[
					'Content-Type' => 'application/json',
					'x-scope-id' => $websiteId
				],
				'/payment/order/' . $internalOrderId,
				'PATCH',
				json_encode($requestOrder)
			);

            $orderStatusAfterFullPayment = $this->helperData->getFullPaymentOrderStatus($this->storeManager->getStore()->getWebsiteId());
            $orderStatusAfterPartialPayment = $this->helperData->getPartialPaymentOrderStatus($this->storeManager->getStore()->getWebsiteId());
			if($order->getTotalDue() == 0)
			{
				$order->setState($orderStatusAfterFullPayment)->setStatus($orderStatusAfterFullPayment);
				$order->addStatusToHistory($orderStatusAfterFullPayment, '',false);
				$order->save();
			}else{
                $order->setState(OrderModel::STATE_NEW);
                $order->setStatus($orderStatusAfterPartialPayment);
                $comment = __("Partial amount paid by customer");
                $order->addStatusToHistory($orderStatusAfterPartialPayment, $comment);
				$order->save();
			}
		}
    }
}
