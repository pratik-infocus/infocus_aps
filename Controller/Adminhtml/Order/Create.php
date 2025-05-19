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

namespace Infocus\AdobePaymentService\Controller\Adminhtml\Order;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\PaymentServicesBase\Model\HttpException;
use Magento\PaymentServicesPaypal\Helper\OrderHelper;
use Magento\PaymentServicesPaypal\Model\OrderService;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote\Address as Address;
use Magento\ServiceProxy\Controller\Adminhtml\AbstractProxyController;
use Magento\Quote\Model\QuoteFactory;
use Magento\Backend\Model\Session\Quote as QuoteSession;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Class Create
 * @package Infocus\AdobePaymentService\Controller\Adminhtml\Order
 */
class Create extends AbstractProxyController implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_ServiceProxy::services';

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @var QuoteSession
     */
    private $quoteSession;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @param Context $context
     * @param QuoteSession $quoteSession
     * @param OrderService $orderService
     * @param QuoteFactory $quoteFactory
     * @param OrderHelper $orderHelper
     * @param OrderRepositoryInterface $orderRepository
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        Context $context,
        QuoteSession $quoteSession,
        OrderService $orderService,
        QuoteFactory $quoteFactory,
        OrderHelper $orderHelper,
        OrderRepositoryInterface $orderRepository,
        CartRepositoryInterface $quoteRepository
    ) {
        parent::__construct($context);
        $this->quoteSession = $quoteSession;
        $this->orderService = $orderService;
        $this->quoteFactory = $quoteFactory;
        $this->orderHelper = $orderHelper;
        $this->orderRepository = $orderRepository;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @inheritDoc
     */
    public function execute(): ResultInterface
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            $quoteId = $this->getRequest()->getPost('quote_id');
            $amount = $this->getRequest()->getPost('amount');
            $quote = $this->quoteFactory->create()->load($quoteId);
            $customerId = $quote->getCustomerId();
            $payer = $customerId !== null && $customerId != ""
                ? $this->orderService->buildPayer($quote, $customerId)
                : $this->orderService->buildGuestPayer($quote);
            $paymentSource = $this->getRequest()->getPost('payment_source');
            $orderIncrementId = $this->resolveOrderIncrementId($quote);
            $store = $quote->getStore();
            $response = $this->orderService->create(
                $store,
                [
                    'amount' => number_format($amount, 2, '.', ''),
                    'l2_data' => $this->orderHelper->getL2Data($quote, $paymentSource ?? ''),
                    'l3_data' => $this->orderHelper->getL3Data($quote, $paymentSource ?? ''),
                    'currency_code' => $quote->getCurrency()->getBaseCurrencyCode(),
                    'shipping_address' => $this->mapAddress($quote->getShippingAddress()),
                    'billing_address' => $this->mapAddress($quote->getBillingAddress()),
                    'payer' => $payer,
                    'is_digital' => $quote->isVirtual(),
                    'website_id' => $quote->getStore()->getWebsiteId(),
                    'store_code' => $quote->getStore()->getCode(),
                    'payment_source' => $paymentSource,
                    'quote_id' => $quoteId,
                    'order_increment_id' => $orderIncrementId,
                    'line_items' => $this->orderHelper->getLineItems($quote, $orderIncrementId),
                    'amount_breakdown' => $this->orderHelper->getAmountBreakdown($quote, $orderIncrementId),
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
            $result->setData($e->getMessage());
        }
        return $result;
    }

    /**
     * Map Commerce address fields to DTO
     *
     * @param Address $address
     * @return array|null
     */
    private function mapAddress(Address $address) :? array
    {
        return [
            'full_name' => $address->getFirstname() . ' ' . $address->getLastname(),
            'address_line_1' => $address->getStreet()[0],
            'address_line_2' => $address->getStreet()[1] ?? null,
            'admin_area_1' => $address->getRegion(),
            'admin_area_2' => $address->getCity(),
            'postal_code' => $address->getPostcode(),
            'country_code' => $address->getCountry()
        ];
    }

    /**
     * Resolve the order increment ID
     *
     * If the order is being reordered, the new order increment ID is based on the original order increment ID
     * and the call to $quote->reserveOrderId() is ignored.
     *
     * @see \Magento\Sales\Model\AdminOrder\Create::beforeSubmit
     *
     * @param Quote $quote
     * @return string
     */
    private function resolveOrderIncrementId(Quote $quote): string
    {
        if ($this->quoteSession->getReordered()) {
            return $this->generateIncrementIdFromParent();
        }
        return $this->orderHelper->reserveAndGetOrderIncrementId($quote);
    }

    /**
     * Generate the new order increment ID based on the original order
     *
     * @return string
     */
    private function generateIncrementIdFromParent(): string
    {
        $oldOrder = $this->orderRepository->get($this->quoteSession->getReordered());
        $originalId = $oldOrder->getOriginalIncrementId();
        if (!$originalId) {
            $originalId = $oldOrder->getIncrementId();
        }
        $orderEditIncrement = $oldOrder->getEditIncrement() + 1;
        return $originalId . '-' . $orderEditIncrement;
    }
}
