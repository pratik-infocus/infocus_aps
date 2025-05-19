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
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Infocus\AdobePaymentService\Helper\Data;
use Infocus\PartialPayments\Helper\Data as HelperData;

/**
 * Class CaptureRequest
 * @package Infocus\AdobePaymentService\Gateway\Request
 */
class CaptureRequest implements BuilderInterface
{
    /**
     * @var Config
     */
    private $config;

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
     * @var HelperData
     */
    private $helperData;

    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param RequestInterface $request
     * @param ScopeConfigInterface $scopeConfig
     * @param HelperData $helperData
     */
    public function __construct(
        Config $config,
        StoreManagerInterface $storeManager,
        RequestInterface $request,
        ScopeConfigInterface $scopeConfig,
        HelperData $helperData
    ) {
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
        $this->helperData = $helperData;
    }

    /**
     * Build the capture request which will be sent to payment gateway
     *
     * @param array $buildSubject
     * @return array
     * @throws NoSuchEntityException
     */
    public function build(array $buildSubject)
    {
        $requestData = $this->request->getPostValue();

        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = SubjectReader::readPayment($buildSubject);

        /** @var $payment \Magento\Sales\Model\Order\Payment */
        $payment = $paymentDO->getPayment();
        $configValue = $this->scopeConfig->getValue(Data::SLEEP_CONFIG_PATH,ScopeInterface::SCOPE_STORE);
        $partialPaymentMethods = array(
            'payment_services_paypal_smart_buttons',
            'payment_services_paypal_hosted_fields',
            'payment_services_paypal_vault'
        );
        if($configValue > 0){
            sleep($configValue);
        }
        $orderPaymentMethod = $payment->getMethod();

            if(is_array($requestData) && array_key_exists("payment", $requestData)){
                if(is_array($requestData["payment"]) && array_key_exists("additional_information", $requestData["payment"])){
                    if(is_array($requestData["payment"]["additional_information"]) && array_key_exists("paypal_order_id", $requestData["payment"]["additional_information"])){
                        $this->helperData->log("Processing capture request for order: ".$payment->getOrder()->getOrderIncrementId());
                        $uri = '/payments/'
                        . $this->config->getMerchantId()
                        . '/payment/'
                        . $payment->getLastTransId()
                        . '/capture';
                        if($orderPaymentMethod == "payment_services_paypal_smart_buttons")
                        {
                            $uri = '/payments/'
                            . $this->config->getMerchantId()
                            . '/payment/paypal/order/'
                            .$requestData["payment"]["additional_information"]["paypal_order_id"]
                            . '/capture';
                        }
                        $websiteId = $this->storeManager->getStore($payment->getOrder()->getStoreId())->getWebsiteId();

                        $amountKey = 'order_' . $payment->getOrder()->getId() . '_amount';
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

                        return [
                            'uri' => $uri,
                            'method' => \Magento\Framework\App\Request\Http::METHOD_POST,
                            'body' => [
                                'capture-request' => [
                                    'amount' => [
                                        'currency_code' => $payment->getOrder()->getBaseCurrencyCode(),
                                        'value' => number_format($amount, 2, '.', '')
                                    ]
                                ]
                            ],
                            // 'body' => [
                            //     'mp-transaction' => [
                            //         'order-increment-id' => $payment->getOrder()->getIncrementId()
                            //     ]
                            // ],
                            'headers' => [
                                'Content-Type' => 'application/json',
                                'x-scope-id' => $websiteId
                            ]
                        ];
                    }
                }
                if(is_array($requestData["payment"]) && array_key_exists("paypal_order_id", $requestData["payment"]))
                {

                    $amountKey = 'order_' . $payment->getOrder()->getId() . '_amount';
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
                    $this->helperData->log("Processing authorize request for order: ".$payment->getOrder()->getOrderIncrementId());
                    $payment->authorize(true, $amount);
                    sleep($this->scopeConfig->getValue(Data::SLEEP_AUTHORIZE_PATH,ScopeInterface::SCOPE_STORE));
                    $this->helperData->log("Processing capture request for order: ".$payment->getOrder()->getOrderIncrementId());
                    $uri = '/payments/'
                        . $this->config->getMerchantId()
                        . '/payment/'
                        . $payment->getLastTransId()
                        . '/capture';
                    // $uri = '/payments/'
                    // . $this->config->getMerchantId()
                    // . '/payment/paypal/order/'
                    // . $payment->getAdditionalInformation('paypal_order_id')
                    // . '/capture';

                    $websiteId = $this->storeManager->getStore($payment->getOrder()->getStoreId())->getWebsiteId();
                    return [
                        'uri' => $uri,
                        'method' => \Magento\Framework\App\Request\Http::METHOD_POST,
                        'body' => [
                            'capture-request' => [
                                'amount' => [
                                    'currency_code' => $payment->getOrder()->getBaseCurrencyCode(),
                                    'value' => number_format($amount, 2, '.', '')
                                ]
                            ]
                        ],
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'x-scope-id' => $websiteId
                        ]
                    ];
                }
            }
            $this->helperData->log("Processing capture request for order: ".$payment->getOrder()->getId());
            $uri = '/payments/'
                . $this->config->getMerchantId()
                . '/payment/'
                . $payment->getAuthorizationTransaction()->getTxnId()
                . '/capture';
            $websiteId = $this->storeManager->getStore($payment->getOrder()->getStoreId())->getWebsiteId();

            return [
                'uri' => $uri,
                'method' => \Magento\Framework\App\Request\Http::METHOD_POST,
                'body' => [
                    'capture-request' => [
                        'amount' => [
                            'currency_code' => $payment->getOrder()->getBaseCurrencyCode(),
                            'value' => number_format(SubjectReader::readAmount($buildSubject), 2, '.', '')
                        ]
                    ]
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-scope-id' => $websiteId
                ]
            ];
    }
}
