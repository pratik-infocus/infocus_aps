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

namespace Infocus\AdobePaymentService\Model\Ui;

use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentProviderInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;
use Magento\Framework\UrlInterface;
use Magento\PaymentServicesPaypal\Model\Ui\ConfigProvider;
use Magento\Framework\App\Request\Http as HttpRequest;

/**
 * Class TokenUiComponentProvider
 * @package Infocus\AdobePaymentService\Model\Ui
 */
class TokenUiComponentProvider implements TokenUiComponentProviderInterface
{
    public const CC_VAULT_SOURCE = 'vault';
    private const CREATE_ORDER_URL = 'infocusadobepaymentservice/order/create';
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
     * @var HttpRequest
     */
    private $httpRequest;

    /**
     * TokenUiComponentProvider constructor
     *
     * @param TokenUiComponentInterfaceFactory $componentFactory
     * @param UrlInterface $urlBuilder
     * @param HttpRequest $httpRequest
     */
    public function __construct(
        TokenUiComponentInterfaceFactory $componentFactory,
        UrlInterface $urlBuilder,
        HttpRequest $httpRequest
    ) {
        $this->componentFactory = $componentFactory;
        $this->urlBuilder = $urlBuilder;
        $this->httpRequest = $httpRequest;
    }

    /**
     * Set UI Component to render stored cards and set card details for frontend my account
     *
     * @param PaymentTokenInterface $paymentToken
     * @return \Magento\Vault\Model\Ui\TokenUiComponentInterface
     */
    public function getComponentForToken(PaymentTokenInterface $paymentToken)
    {
        $controllerName = $this->httpRequest->getControllerName();
        $actionName = $this->httpRequest->getActionName();
        $routeName = $this->httpRequest->getRouteName();
        $jsonDetails = json_decode($paymentToken->getTokenDetails() ?: '{}', true);
        $componentName = ($routeName == 'infocus_partialpayments') ? 'Infocus_AdobePaymentService/js/view/payment/method-renderer/vault' : 'Magento_PaymentServicesPaypal/js/view/payment/method-renderer/vault';
        $orderUrl = ($routeName == 'infocus_partialpayments') ? self::CREATE_ORDER_URL : self::ADOBE_CREATE_ORDER_URL;
        $component = $this->componentFactory->create(
            [
                'config' => [
                    'code' => ConfigProvider::CC_VAULT_CODE,
                    'paymentSource' => self::CC_VAULT_SOURCE,
                    'createOrderUrl' => $this->urlBuilder->getUrl($orderUrl),
                    TokenUiComponentProviderInterface::COMPONENT_DETAILS => $jsonDetails,
                    TokenUiComponentProviderInterface::COMPONENT_PUBLIC_HASH => $paymentToken->getPublicHash()
                ],
                'name' => $componentName
            ]
        );
        return $component;
    }
}
