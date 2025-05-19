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

namespace Infocus\AdobePaymentService\Model\Ui\Adminhtml;

use Magento\PaymentServicesPaypal\Model\Ui\ConfigProvider;
use Magento\Framework\UrlInterface;
use Magento\PaymentServicesPaypal\Block\Adminhtml\Form\AdminVault;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentProviderInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;
use Magento\PaymentServicesPaypal\Model\Ui\Adminhtml\TokenUiComponentProvider as MagentoTokenProvider ;

/**
 * Class TokenUiComponentProvider
 * @package Infocus\AdobePaymentService\Model\Ui\Adminhtml
 */
class TokenUiComponentProvider extends MagentoTokenProvider
{
    /**
     * @var string
     */
    private const CREATE_ORDER_URL = 'infocuspaymentservice/order/create';

    /**
     * @var string
     */
    private const ADOBE_CREATE_ORDER_URL = 'paymentservicespaypal/order/create';

    /**
     * @var TokenUiComponentInterfaceFactory
     */
    private $componentFactory;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * Constructor
     *
     * @param TokenUiComponentInterfaceFactory $componentFactory
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        TokenUiComponentInterfaceFactory $componentFactory,
        UrlInterface $urlBuilder
    ) {
        $this->componentFactory = $componentFactory;
        $this->urlBuilder = $urlBuilder;
        parent::__construct($componentFactory,$urlBuilder);
    }

    /**
     * Build admin vault token components with custom configurations
     *
     * @param PaymentTokenInterface $paymentToken
     * @return \Magento\Vault\Model\Ui\TokenUiComponentInterface
     */
    public function getComponentForToken(PaymentTokenInterface $paymentToken)
    {
        if (str_contains($this->urlBuilder->getCurrentUrl(), 'sales/order_create')) {
            $jsonDetails = json_decode($paymentToken->getTokenDetails() ?: '{}', true);
            $component = $this->componentFactory->create(
                [
                    'config' => [
                        'code' => ConfigProvider::CC_VAULT_CODE,
                        'createOrderUrl' => $this->urlBuilder->getUrl(self::ADOBE_CREATE_ORDER_URL),
                        TokenUiComponentProviderInterface::COMPONENT_DETAILS => $jsonDetails,
                        TokenUiComponentProviderInterface::COMPONENT_PUBLIC_HASH => $paymentToken->getPublicHash(),
                        'template' => 'Magento_PaymentServicesPaypal::form/vault.phtml'
                    ],
                    'name' => AdminVault::class
                ]
            );
            return $component;
        }else{
            $jsonDetails = json_decode($paymentToken->getTokenDetails() ?: '{}', true);
            $component = $this->componentFactory->create(
                [
                    'config' => [
                        'code' => ConfigProvider::CC_VAULT_CODE,
                        'createOrderUrl' => $this->urlBuilder->getUrl(self::CREATE_ORDER_URL),
                        TokenUiComponentProviderInterface::COMPONENT_DETAILS => $jsonDetails,
                        TokenUiComponentProviderInterface::COMPONENT_PUBLIC_HASH => $paymentToken->getPublicHash(),
                        'template' => 'Infocus_AdobePaymentService::form/vault.phtml'
                    ],
                    'name' => AdminVault::class
                ]
            );
            return $component;
        }
    }
}
