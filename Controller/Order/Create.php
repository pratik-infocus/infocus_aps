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

namespace Infocus\AdobePaymentService\Controller\Order;

use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\PaymentServicesPaypal\Model\OrderService;
use Magento\PaymentServicesBase\Model\HttpException;
use Magento\Sales\Model\Order;
use Magento\Quote\Model\QuoteFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\PaymentServicesPaypal\Helper\OrderHelper;
use Magento\Quote\Api\CartRepositoryInterface as QuoteRepositoryInterface;

/**
 * Class Create
 * @package Infocus\AdobePaymentService\Controller\Order
 */
class Create implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const VAULT_PARAM_KEY = 'vault';

    /**
     * @var Order
     */
    private $order;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var ResultFactory
     */
    private $resultFactory;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var OrderHelper
     */
    private $orderHelper;
    
    /**
     * @var QuoteRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @param OrderService $orderService
     * @param ResultFactory $resultFactory
     * @param RequestInterface $request
     * @param Order $order
     * @param QuoteFactory $quoteFactory
     * @param CustomerSession $customerSession
     * @param QuoteRepositoryInterface $quoteRepository
     * @param OrderHelper $orderHelper
     * 
     */
    public function __construct(
        OrderService $orderService,
        ResultFactory $resultFactory,
        RequestInterface $request,
        Order $order,
        QuoteFactory $quoteFactory,
        CustomerSession $customerSession,
        QuoteRepositoryInterface $quoteRepository,
        OrderHelper $orderHelper
    ) {
        $this->orderService = $orderService;
        $this->resultFactory = $resultFactory;
        $this->request = $request;
        $this->order = $order;
        $this->quoteFactory = $quoteFactory;
        $this->customerSession = $customerSession;
        $this->quoteRepository = $quoteRepository;
        $this->orderHelper = $orderHelper;
    }

    /**
     * Dispatch the order creation request with Commerce params
     *
     * @return ResultInterface
     */
    public function execute() : ResultInterface
    {
        $shouldCardBeVaulted = $this->request->getParam(self::VAULT_PARAM_KEY) === 'true';
        $paymentSource = $this->request->getPost('payment_source');
        $orderId = $this->request->getPost('order_increment');
        $amount = (float)$this->request->getPost('amount');
        $order = $this->order->load($orderId);
        $quoteId = $order->getQuoteId();
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            $quote = $this->quoteFactory->create()->load($quoteId);
            $isLoggedIn = $this->customerSession->isLoggedIn();
            $store = $quote->getStore();
            $response = $this->orderService->create(
                $store,
                [
                    'amount' => number_format($amount, 2, '.', ''),
                    'l2_data' => $this->orderHelper->getL2Data($quote, $paymentSource ?? ''),
                    'l3_data' => $this->orderHelper->getL3Data($quote, $paymentSource ?? ''),
                    'currency_code' => $quote->getCurrency()->getBaseCurrencyCode(),
                    'shipping_address' => $this->orderService->mapAddress($quote->getShippingAddress()),
                    'billing_address' => $this->orderService->mapAddress($quote->getBillingAddress()),
                    'payer' => $isLoggedIn
                        ? $this->orderService->buildPayer($quote, $this->customerSession->getCustomer()->getId())
                        : $this->orderService->buildGuestPayer($quote),
                    'is_digital' => $quote->isVirtual(),
                    'website_id' => $quote->getStore()->getWebsiteId(),
                    'payment_source' => $paymentSource,
                    'vault' => $shouldCardBeVaulted,
                    'quote_id' => $quoteId,
                    'order_increment_id' => $orderId,
                    'line_items' => $this->orderHelper->getLineItems($quote, $orderId),
                    'amount_breakdown' => $this->orderHelper->getAmountBreakdown($quote, $orderId),
                ]
            );

            $response = array_merge_recursive(
                $response,
                [
                    "paypal-order" => [
                        "amount" => $quote->getBaseGrandTotal(),
                        "currency_code" => $quote->getCurrency()->getBaseCurrencyCode()
                    ]
                ]
            );

            if (isset($response["paypal-order"]['id'])) {
                $quote->getPayment()->setAdditionalInformation('paypal_order_id', $response["paypal-order"]['id']);
                $quote->getPayment()->setAdditionalInformation('paypal_order_amount', $quote->getBaseGrandTotal());
                $this->quoteRepository->save($quote);
            }

            $result->setHttpResponseCode($response['status'])
                ->setData(['response' => $response]);
        } catch (HttpException $e) {
            $result->setHttpResponseCode(500);
        }
        return $result; 
    }

    /**
     * @inheritdoc
     */
    public function createCsrfValidationException(RequestInterface $request) :? InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function validateForCsrf(RequestInterface $request) :? bool
    {
        return true;
    }
}
