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

namespace Infocus\AdobePaymentService\Gateway\Request;

use Magento\PaymentServicesPaypal\Model\Config;
use Magento\PaymentServicesPaypal\Model\CustomerHeadersBuilder;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Infocus\AdobePaymentService\Helper\Data;
use Magento\Framework\App\ResourceConnection;
use Infocus\PartialPayments\Helper\Data as HelperData;

/**
 * Class SavedAuthorizationAndCaptureRequest
 * @package Infocus\AdobePaymentService\Gateway\Request
 */
class SavedAuthorizationAndCaptureRequest implements BuilderInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var CustomerHeadersBuilder
     */
    private $customerHeaderBuilder;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var HelperData
     */
    private $helperData;

    /**
     * @param Config $config
     * @param CustomerHeadersBuilder $customerHeaderBuilder
     * @param StoreManagerInterface $storeManager
     * @param RequestInterface $request
     * @param ScopeConfigInterface $scopeConfig
     * @param ResourceConnection $resource
     * @param HelperData $helperData
     */
    public function __construct(
        Config $config,
        CustomerHeadersBuilder $customerHeaderBuilder,
        StoreManagerInterface $storeManager,
        RequestInterface $request,
        ScopeConfigInterface $scopeConfig,
        ResourceConnection $resource,
        HelperData $helperData
    ) {
        $this->config = $config;
        $this->customerHeaderBuilder = $customerHeaderBuilder;
        $this->storeManager = $storeManager;
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
        $this->resource = $resource;
        $this->helperData = $helperData;
    }

    /**
     * Build authorization and capture request
     *
     * @param array $buildSubject
     * @return array
     * @throws NoSuchEntityException
     */
    public function build(array $buildSubject)
    {
        $requestData = $this->request->getPostValue();
        /** @var PaymentDataObjectInterface $payment */
        $payment = SubjectReader::readPayment($buildSubject);
        $extensionAttributes = $payment->getPayment()->getExtensionAttributes();
        $paymentToken = $extensionAttributes->getVaultPaymentToken();

        $amountKey = 'order_' . $payment->getPayment()->getOrder()->getId() . '_amount';
        if (isset($requestData[$amountKey])) {
            $amount = $requestData[$amountKey];
        }
        elseif(isset($requestData['payment']['pay_amount']))
        {
            $amount = $requestData['payment']['pay_amount'];
        }
        else {
            $amount = SubjectReader::readAmount($buildSubject);
        }

        if (isset($requestData['is-admin-vault'])) {
            $this->helperData->log("Processing authorization request for order: ".$payment->getPayment()->getOrder()->getOrderIncrementId());
            $payment->getPayment()->authorize(true,  $amount);
            sleep($this->scopeConfig->getValue(Data::SLEEP_AUTHORIZE_PATH,ScopeInterface::SCOPE_STORE));
        }

        if(is_array($requestData) && array_key_exists("payment", $requestData) && $amount !="")
        {
            $uri = '/payments/'
            . $this->config->getMerchantId()
            . '/payment/'
            . $payment->getPayment()->getLastTransId()
            . '/capture';
            $body = [
                'capture-request' => [
                    'amount' => [
                        'currency_code' => $payment->getPayment()->getOrder()->getBaseCurrencyCode(),
                        'value' => number_format((float)$amount, 2, '.', '')
                    ]
                ]
            ];
        }
        else{
            $uri = '/'
            . $this->config->getMerchantId()
            . '/payment/paypal/order/'
            . $payment->getPayment()->getAdditionalInformation('paypal_order_id')
            . '/capture';
            $body = [
                'mp-transaction' => [
                    'order-increment-id' => $payment->getOrder()->getOrderIncrementId()
                ]
            ];

        }
        $this->helperData->log("Processing capture request for order: ".$payment->getPayment()->getOrder()->getOrderIncrementId());

        $websiteId = $this->storeManager->getStore($payment->getOrder()->getStoreId())->getWebsiteId();
        if (isset($paymentToken)) {
            if(isset($requestData['paypal-hash'])){
                $customerId = $extensionAttributes->getVaultPaymentToken()->getCustomerId();
                $connection = $this->resource->getConnection();
                $paymentHash = $requestData['paypal-hash'];
                $gatewayToken = $connection->fetchOne("SELECT gateway_token from vault_payment_token where public_hash='".$paymentHash."' and customer_id = ".$customerId." and is_active= 1 and is_visible= 1");
                $body['mp-transaction']['payment-vault-id'] = $gatewayToken;
            }else {
                $body['mp-transaction']['payment-vault-id'] = $paymentToken->getGatewayToken();
            }
        }
        $request =  [
            'uri' => $uri,
            'method' => \Magento\Framework\App\Request\Http::METHOD_POST,
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
                'x-scope-id' => $websiteId
            ]
        ];

        $customHeaders = $this->customerHeaderBuilder->buildCustomerHeaders($payment);
        $request['headers'] = array_merge($request['headers'], $customHeaders);
        return $request;
    }
}
