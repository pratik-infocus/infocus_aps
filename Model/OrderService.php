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

declare(strict_types=1);
namespace Infocus\AdobePaymentService\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PaymentServicesBase\Model\ServiceClientInterface;
use Magento\Framework\App\Request\Http;
use Magento\PaymentServicesBase\Model\HttpException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as Address;
use Magento\PaymentServicesBase\Model\Config as BaseConfig;
use Psr\Log\LoggerInterface;
use Magento\PaymentServicesPaypal\Model\Config;
use Magento\PaymentServicesPaypal\Model\OrderService as MagentoOrderService;
use Magento\PaymentServicesPaypal\Model\HostedFieldsConfigProvider;
use Magento\Store\Api\Data\StoreInterface;
class OrderService extends MagentoOrderService
{

    private const PAYPAL_ORDER = 'paypal-order';
    private const PAYPAL_ORDER_UPDATE = 'paypal-order-update';

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var ServiceClientInterface
     */
    private $httpClient;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var BaseConfig
     */
    private $baseConfig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param CartRepositoryInterface $quoteRepository
     * @param ServiceClientInterface $httpClient
     * @param Config $config
     * @param BaseConfig $baseConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        ServiceClientInterface $httpClient,
        Config $config,
        BaseConfig $baseConfig,
        LoggerInterface $logger
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->httpClient = $httpClient;
        $this->config = $config;
        $this->baseConfig = $baseConfig;
        $this->logger = $logger;
    }


    /**
     * Map DTO fields and send the order creation request to the backend service
     *
     * @param array $data
     * @return array
     * @throws HttpException
     * @throws NoSuchEntityException
     */
    public function create(StoreInterface $store,array $data) : array
    {
        $order = [
            self::PAYPAL_ORDER => [
                'amount' => [
                    'currency_code' => $data['currency_code'],
                    'value' => $data['amount'] ?? 0.00
                ],
                'is_digital' => !!$data['is_digital'] ?? false,
                'website_id' => $store->getWebsiteId(),
                'store_id' => $store->getStoreGroupId(),
                'storeview_id' => $store->getId(),
                'payment_source' => $data['payment_source'] ?? '',
                'vault' => $data['vault'] ?? false,
            ]
        ];
        $order[self::PAYPAL_ORDER]['shipping-address'] = $data['shipping_address'] ?? null;
        $order[self::PAYPAL_ORDER]['billing-address'] = $data['billing_address'] ?? null;
        $order[self::PAYPAL_ORDER]['payer'] = $data['payer'] ?? null;
        if ($data['quote_id'] !== null) {
            $order[self::PAYPAL_ORDER]['intent'] = $this->getPaymentIntent($data['quote_id']);
            $quote = $this->quoteRepository->get($data['quote_id']);
            $paymentMethod = $quote->getPayment()->getMethod() ?? "";
            if($paymentMethod =="partial_payment" && $data['payment_source'] == "paypal"){
                if($this->config->getPaymentIntent('payment_services_paypal_smart_buttons', $quote->getStoreId()) == "capture")
                {
                    $order[self::PAYPAL_ORDER]['intent'] = "capture";
                }
            }
        }
        if (!empty($data['order_increment_id'])) {
            $order[self::PAYPAL_ORDER]['order_increment_id'] = $data['order_increment_id'];
        }
        $softDescriptor = $this->config->getSoftDescriptor($data['store_code'] ?? null);
        if ($softDescriptor) {
            $order[self::PAYPAL_ORDER]['soft_descriptor'] = $softDescriptor;
        }

        $order = $this->applyL2Data($order, $data);
        $order = $this->applyL3Data($order, $data);
        $order = $this->applyLineItems($order, $data);
        $order = $this->applyAmountBreakdown($order, $data, self::PAYPAL_ORDER);

        $headers = [
            'Content-Type' => 'application/json',
            'x-scope-id' => $store->getWebsiteId()
        ];
        if (isset($data['vault']) && $data['vault']) {
            $headers['x-commerce-customer-id'] = $data['payer']['customer_id'];
        }
        if (isset($data['quote_id']) && $data['quote_id']) {
            $headers['x-commerce-quote-id'] = $data['quote_id'];
        }

        $path = '/' . $this->config->getMerchantId() . '/payment/paypal/order';
        $body = json_encode($order);
        $response = $this->httpClient->request(
            $headers,
            $path,
            Http::METHOD_POST,
            $body,
            'json',
            $this->baseConfig->getEnvironmentType($data['store_code'] ?? null)
        );

        $this->logger->debug(
            var_export(
                [
                    'request' => [
                        $path,
                        $headers,
                        Http::METHOD_POST,
                        $body
                    ],
                    'response' => $response
                ],
                true
            )
        );
        return $response;
    }

    public function getPaymentIntent(string $quoteId): string
    {
        $quote = $this->quoteRepository->get($quoteId);
        $paymentMethod = $quote->getPayment()->getMethod();
        $storeId = $quote->getStoreId();
        if ($paymentMethod === HostedFieldsConfigProvider::CC_VAULT_CODE) {
            return $this->config->getPaymentIntent(HostedFieldsConfigProvider::CODE, $storeId);
        }
        return $this->config->getPaymentIntent($paymentMethod, $storeId);
    }

    public function applyL2Data(array $order, array $data) : array
    {
        if (empty($data['l2_data'])) {
            return $order;
        }

        $order[self::PAYPAL_ORDER]['l2_data'] = $data['l2_data'];
        return $order;
    }

    /**
     * Apply L3 data to the order
     *
     * @param array $order
     * @param array $data
     * @return array
     */
    public function applyL3Data(array $order, array $data) : array
    {
        if (empty($data['l3_data'])) {
            return $order;
        }

        $order[self::PAYPAL_ORDER]['l3_data'] = $data['l3_data'];
        return $order;
    }

    /**
     * Apply Line items data to the order
     *
     * @param array $order
     * @param array $data
     * @return array
     */
    public function applyLineItems(array $order, array $data) : array
    {
        if (empty($data['line_items'])) {
            return $order;
        }

        $order[self::PAYPAL_ORDER]['line_items'] = $data['line_items'];
        return $order;
    }

    /**
     * Apply Line items operation data to the order
     *
     * @param array $order
     * @param array $data
     * @return array
     */
    public function applyLineItemsOperation(array $order, array $data) : array
    {
        if (empty($data['line_items'])) {
            return $order;
        }

        $order[self::PAYPAL_ORDER_UPDATE]['line_items'] = [
            'operation' => 'ADD',
            'value' => $data['line_items']
        ];

        return $order;
    }

    /**
     * Apply Amount Breakdown data to the order
     *
     * @param array $order
     * @param array $data
     * @param string $key
     * @return array
     */
    public function applyAmountBreakdown(array $order, array $data, string $key) : array
    {
        if (empty($data['amount_breakdown'])) {
            return $order;
        }

        $order[$key]['amount_breakdown'] = $data['amount_breakdown'];
        return $order;
    }
}
