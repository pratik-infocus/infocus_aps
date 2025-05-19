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

namespace Infocus\AdobePaymentService\Model\Order\Payment\State;

use Magento\Framework\App\ObjectManager;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\StatusResolver;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Model\Order\Payment\State\CaptureCommand as MagentoCaptureCommand;

class CaptureCommand extends MagentoCaptureCommand
{
    /**
     * @var StatusResolver
     */
    private $statusResolver;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @param StatusResolver|null $statusResolver
     * @param RequestInterface $request
     */
    public function __construct(
        StatusResolver $statusResolver = null,
        RequestInterface $request
        )
    {
        $this->statusResolver = $statusResolver
            ? : ObjectManager::getInstance()->get(StatusResolver::class);
            $this->request = $request;
    }

    /**
     * Run command.
     *
     * @param OrderPaymentInterface $payment
     * @param string|float $amount
     * @param OrderInterface $order
     * @return \Magento\Framework\Phrase
     */
    public function execute(OrderPaymentInterface $payment, $amount, OrderInterface $order)
    {
        $requestData = $this->request->getPostValue();
        $state = Order::STATE_PROCESSING;
        $status = null;
        $message = 'Captured amount of %1 online.';

        if ($payment->getIsTransactionPending()) {
            $state = Order::STATE_PAYMENT_REVIEW;
            $message = 'An amount of %1 will be captured after being approved at the payment gateway.';
        }

        if ($payment->getIsFraudDetected()) {
            $state = Order::STATE_PAYMENT_REVIEW;
            $status = Order::STATUS_FRAUD;
            $message .= ' Order is suspended as its capturing amount %1 is suspected to be fraudulent.';
        }

        $message = $this->getNotificationMessage($payment) ?? $message;

        if (!isset($status)) {
            $status = $this->statusResolver->getOrderStatusByState($order, $state);
        }

        $order->setState($state);
        $order->setStatus($status);

        $amountKey = 'order_' . $order->getId() . '_amount';
        if (isset($requestData[$amountKey])) {
            $captureAmount = $requestData[$amountKey];
        }
        elseif(isset($requestData['payment']['pay_amount']))
        {
            $captureAmount = $requestData['payment']['pay_amount'];
        }
        else {
            $captureAmount = $amount;
        }
        return __($message, $order->getBaseCurrency()->formatTxt($captureAmount));
    }

    private function getNotificationMessage(OrderPaymentInterface $payment): ?string
    {
        $extensionAttributes = $payment->getExtensionAttributes();
        if ($extensionAttributes && $extensionAttributes->getNotificationMessage()) {
            return $extensionAttributes->getNotificationMessage();
        }
        return null;
    }

}
