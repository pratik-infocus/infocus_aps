<?php


namespace Infocus\AdobePaymentService\Model\Order\Payment\Operations;

use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Operations\AuthorizeOperation as MagentoAuthorizeOperation;

class AuthorizeOperations extends MagentoAuthorizeOperation
{
    /**
     * Authorizes payment.
     *
     * @param OrderPaymentInterface $payment
     * @param bool $isOnline
     * @param string|float $amount
     * @return OrderPaymentInterface
     */
    public function authorize(OrderPaymentInterface $payment, $isOnline, $amount)
    {
        /**
         * @var $payment Payment
         */
        $payment->setShouldCloseParentTransaction(false);
        $partialPaymentMethods = array(
            'payment_services_paypal_smart_buttons',
            'payment_services_paypal_hosted_fields',
            'payment_services_paypal_vault'
        );
        $orderPaymentMethod = $payment->getMethod();
        $isSameCurrency = $payment->isSameCurrency();
        if (!$isSameCurrency || !$payment->isCaptureFinal($amount)) {
            if(!in_array($orderPaymentMethod, $partialPaymentMethods)) {
                $payment->setIsFraudDetected(true);
            }
        }

        // update totals
        $amount = $payment->formatAmount($amount, true);
        $payment->setBaseAmountAuthorized($amount);

        // do authorization
        $order = $payment->getOrder();
        if ($isOnline) {
            // invoke authorization on gateway
            $method = $payment->getMethodInstance();
            $method->setStore($order->getStoreId());
            $method->authorize($payment, $amount);
        }

        $message = $this->stateCommand->execute($payment, $amount, $order);
        // update transactions, order state and add comments
        $transaction = $payment->addTransaction(Transaction::TYPE_AUTH);
        $message = $payment->prependMessage($message);
        $payment->addTransactionCommentsToOrder($transaction, $message);

        return $payment;
    }
}

