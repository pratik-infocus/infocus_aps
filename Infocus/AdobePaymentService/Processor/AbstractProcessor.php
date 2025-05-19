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

namespace Infocus\AdobePaymentService\Processor;

use Infocus\PartialPayments\Model\Payment\MyAccount\AbstractMyAccountProcessor;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Registry;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Vault\Model\ResourceModel\PaymentToken;
use Magento\Vault\Model\ResourceModel\PaymentTokenFactory;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Infocus\PartialPayments\Helper\Data as HelperData;
use Magento\Framework\App\RequestInterface;

/**
 * Class AbstractProcessor
 * @package Infocus\AdobePaymentService\Processor
 */
class AbstractProcessor extends AbstractMyAccountProcessor
{
    const PUBLIC_HASH = PaymentTokenInterface::PUBLIC_HASH;
    const CUSTOMER_ID = PaymentTokenInterface::CUSTOMER_ID;
    const PAYMENT_NONCE = 'payment_method_nonce';
    const VAULT_ENABLER = 'is_active_payment_token_enabler';

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var PaymentTokenFactory
     */
    protected $paymentTokenResourceModelFactory;

    /**
     * @var null|PaymentToken
     */
    protected $paymentTokenResourceModel;

    /**
     * @var bool
     */
    protected $isVaultEmulated = false;

    /**
     * @var null
     */
    protected $publicHash = null;

    /**
     * @var bool
     */
    protected $firstNonceUsed = false;

    /**
     * @var bool
     */
    protected $useVault = false;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var HelperData
     */
    protected $helperData;
    
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * AbstractProcessor constructor.
     *
     * @param DataObjectHelper $dataObjectHelper
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param Session $session
     * @param HelperData $helperData
     * @param PaymentTokenFactory $paymentTokenFactory
     * @param Registry $registry
     * @param RequestInterface $request
     */
    public function __construct(
        DataObjectHelper $dataObjectHelper,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        OrderRepositoryInterface $orderRepository,
        Session $session,
        HelperData $helperData,
        PaymentTokenFactory $paymentTokenFactory,
        Registry $registry,
        RequestInterface $request
    ) {
        parent::__construct($orderPaymentRepository, $dataObjectHelper, $orderRepository, $helperData,$request);
        $this->registry = $registry;
        $this->session = $session;
        $this->paymentTokenResourceModelFactory = $paymentTokenFactory;
    }

    /**
     * Get the current customer Id
     * @return int|null
     */
    protected function getCustomerId()
    {
        return $this->session->getCustomerId();
    }

    /**
     * @return PaymentToken
     */
    protected function getPaymentTokenResourceModel()
    {
        if (null === $this->paymentTokenResourceModel) {
            $this->paymentTokenResourceModel = $this->paymentTokenResourceModelFactory->create();
        }
        return $this->paymentTokenResourceModel;
    }

    /**
     * Reads Payment token from Order Payment
     *
     * @param OrderPaymentExtensionInterface | null $extensionAttributes
     * @return PaymentTokenInterface | null
     */
    protected function getPaymentToken(OrderPaymentExtensionInterface $extensionAttributes = null)
    {
        if (null === $extensionAttributes) {
            return null;
        }

        /** @var PaymentTokenInterface $paymentToken */
        $paymentToken = $extensionAttributes->getVaultPaymentToken();

        if (null === $paymentToken || empty($paymentToken->getGatewayToken())) {
            return null;
        }

        return $paymentToken;
    }

    /**
     * Get public hash token
     * @param array $paymentInformation
     * @return mixed|null
     */
    protected function getPublicHash(array $paymentInformation = [])
    {
        return $this->getParamFromAdditionalData(
            $paymentInformation,
            self::PUBLIC_HASH
        );
    }

    /**
     * @param array $preparedPayment
     * @param array $originalRequest
     * @throws LocalizedException
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function process(array $preparedPayment, array $originalRequest = [])
    {
        $customerId = $this->session->getCustomerId();
        if (!$customerId) {
            throw new LocalizedException(__('You are not logged in'));
        }

        $paymentRequest = $this->getPaymentRequest($preparedPayment);
        $nonce = $this->getParamFromAdditionalData(
            $paymentRequest,
            self::PAYMENT_NONCE
        );

        if ($nonce) {
            $this->publicHash = $this->getParamFromAdditionalData(
                $paymentRequest,
                self::PUBLIC_HASH
            );

            $this->useVault = (bool)$this->getParamFromAdditionalData(
                $paymentRequest,
                self::VAULT_ENABLER
            );

            if (!$this->useVault) {
                $this->isVaultEmulated = true;
                $paymentRequest['additional_information'][self::VAULT_ENABLER] = '1';
            }
        }

        $paymentRequest = array_replace_recursive($preparedPayment['payment'], $paymentRequest);
        $preparedPayment['payment'] = $paymentRequest;
        $this->doPayment($preparedPayment);
    }

    /**
     * @param OrderPaymentInterface $payment
     * @return OrderPaymentInterface
     * @throws LocalizedException
     */
    protected function doFinally(OrderPaymentInterface $payment)
    {
        //Remove from saved cc
        if ($this->isVaultEmulated && $this->isLastOrder) {
            $this->getPaymentTokenResourceModel()->getConnection()->delete(
                $this->paymentTokenResourceModel->getMainTable(),
                [
                    $this->getPaymentTokenResourceModel()->getConnection()
                        ->quoteInto(PaymentTokenInterface::PUBLIC_HASH . ' = ?' , $this->publicHash),
                    $this->getPaymentTokenResourceModel()->getConnection()
                        ->quoteInto(PaymentTokenInterface::CUSTOMER_ID . ' = ?', $this->getCustomerId()),
                ]
            );
        }

        return parent::doFinally($payment);
    }

    /**
     * @param OrderPaymentInterface $payment
     * @return OrderPaymentInterface
     */
    protected function doSuccess(OrderPaymentInterface $payment)
    {
        $this->firstNonceUsed = true;

        $paymentExtensionAttributes = $payment->getExtensionAttributes();
        $paymentToken = $this->getPaymentToken($paymentExtensionAttributes);

        $this->publicHash = $paymentToken instanceof PaymentTokenInterface ? $paymentToken->getPublicHash() : null;

        return parent::doSuccess($payment);
    }
}
