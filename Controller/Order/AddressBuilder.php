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
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\PaymentServicesPaypal\Model\OrderService;
use Magento\PaymentServicesBase\Model\HttpException;
use Magento\Sales\Model\Order;

/**
 * Class AddressBuilder
 * @package Infocus\AdobePaymentService\Controller\Order
 */
class AddressBuilder implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const VAULT_PARAM_KEY = 'vault';

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

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
     * @var Order
     */
    private $order;

    /**
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param OrderService $orderService
     * @param ResultFactory $resultFactory
     * @param RequestInterface $request
     * @param Order $order
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        OrderService $orderService,
        ResultFactory $resultFactory,
        Order $order,
        RequestInterface $request
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->orderService = $orderService;
        $this->resultFactory = $resultFactory;
        $this->request = $request;
        $this->order = $order;
    }

    /**
     * Dispatch the order creation request with Commerce params
     *
     * @return ResultInterface
     */
    public function execute() : ResultInterface
    {
        try {
            $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $orderId = $this->request->getPost('order_id');
            $order = $this->order->load($orderId);
            $response = array();
            $response['cardholderName'] = $order->getBillingAddress()->getFirstname().' '.$order->getBillingAddress()->getLastname();
            if(is_array($order->getBillingAddress()->getStreet())){
                $street = $order->getBillingAddress()->getStreet();
                $response['streetAddress'] = $street[0];
                $response['extendedAddress'] = "";
                if(COUNT($order->getBillingAddress()->getStreet()) > 1){
                    $response['extendedAddress'] = $street[1];
                }
            }
            $response['region'] = $order->getBillingAddress()->getRegion();
            $response['locality'] = $order->getBillingAddress()->getCity();
            $response['postalCode'] = $order->getBillingAddress()->getPostcode();
            $response['countryCodeAlpha2'] = $order->getBillingAddress()->getCountryId();
            $result->setHttpResponseCode(200)->setData(['response' => $response]);
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
