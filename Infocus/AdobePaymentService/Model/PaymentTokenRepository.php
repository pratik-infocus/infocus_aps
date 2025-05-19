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

namespace Infocus\AdobePaymentService\Model;

use Magento\Vault\Model\PaymentTokenRepository as CorePaymentTokenRepository;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Vault\Api\Data;
use Magento\Vault\Api\Data\PaymentTokenSearchResultsInterfaceFactory;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\ResourceModel\PaymentToken as PaymentTokenResourceModel;
use Magento\Vault\Model\ResourceModel\PaymentToken\Collection;
use Magento\Vault\Model\ResourceModel\PaymentToken\CollectionFactory;
use Magento\Vault\Model\PaymentTokenFactory;

/**
 * Class PaymentTokenRepository
 * @package Infocus\AdobePaymentService\Model
 */
class PaymentTokenRepository extends CorePaymentTokenRepository
{
    /**
     * @var PaymentTokenResourceModel
     */
    protected $resourceModel;

    /**
     * @var PaymentTokenFactory
     */
    protected $paymentTokenFactory;

    /**
     * @var FilterBuilder
     */
    protected $filterBuilder;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var PaymentTokenSearchResultsInterfaceFactory
     */
    protected $searchResultsFactory;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var CollectionProcessorInterface|null
     */
    protected $collectionProcessor;

    /**
     * PaymentTokenRepository constructor.
     *
     * @param PaymentTokenResourceModel $resourceModel
     * @param PaymentTokenFactory $paymentTokenFactory
     * @param FilterBuilder $filterBuilder
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param PaymentTokenSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionFactory $collectionFactory
     * @param CollectionProcessorInterface|null $collectionProcessor
     */
    public function __construct(
        PaymentTokenResourceModel $resourceModel,
        PaymentTokenFactory $paymentTokenFactory,
        FilterBuilder $filterBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        PaymentTokenSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionFactory $collectionFactory,
        CollectionProcessorInterface $collectionProcessor = null
    ) {
        parent::__construct($resourceModel, $paymentTokenFactory,$filterBuilder, $searchCriteriaBuilder,$searchResultsFactory, $collectionFactory,$collectionProcessor);
        $this->resourceModel = $resourceModel;
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->filterBuilder = $filterBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Performs persist operations for a specified payment token.
     *
     * @param \Magento\Vault\Api\Data\PaymentTokenInterface $paymentToken The payment token.
     * @return \Magento\Vault\Api\Data\PaymentTokenInterface Saved payment token data.
     */
    public function save(Data\PaymentTokenInterface $paymentToken)
    {
        if($paymentToken->getIsActive() && $paymentToken->getPaymentMethodCode() == "payment_services_paypal_hosted_fields"){
            $paymentToken->setIsVisible(1);
        }
        /** @var PaymentToken $paymentToken */
        $this->resourceModel->save($paymentToken);
        return $paymentToken;
    }
}
