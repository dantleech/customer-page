<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerShop\Yves\CustomerPage\Reader;

use Generated\Shared\Transfer\FilterTransfer;
use Generated\Shared\Transfer\OrderListFormatTransfer;
use Generated\Shared\Transfer\OrderListTransfer;
use Generated\Shared\Transfer\PaginationTransfer;
use SprykerShop\Yves\CustomerPage\CustomerPageConfig;
use SprykerShop\Yves\CustomerPage\Dependency\Client\CustomerPageToCustomerClientInterface;
use SprykerShop\Yves\CustomerPage\Dependency\Client\CustomerPageToSalesClientInterface;
use Symfony\Component\HttpFoundation\Request;

class OrderReader implements OrderReaderInterface
{
    protected const PARAM_PAGE = 'page';
    protected const PARAM_PER_PAGE = 'perPage';

    protected const DEFAULT_PAGE = 1;

    /**
     * @var \SprykerShop\Yves\CustomerPage\Dependency\Client\CustomerPageToSalesClientInterface
     */
    protected $salesClient;

    /**
     * @var \SprykerShop\Yves\CustomerPage\Dependency\Client\CustomerPageToCustomerClientInterface
     */
    protected $customerClient;

    /**
     * @var \SprykerShop\Yves\CustomerPage\CustomerPageConfig
     */
    protected $customerPageConfig;

    /**
     * @param \SprykerShop\Yves\CustomerPage\Dependency\Client\CustomerPageToSalesClientInterface $salesClient
     * @param \SprykerShop\Yves\CustomerPage\Dependency\Client\CustomerPageToCustomerClientInterface $customerClient
     * @param \SprykerShop\Yves\CustomerPage\CustomerPageConfig $customerPageConfig
     */
    public function __construct(
        CustomerPageToSalesClientInterface $salesClient,
        CustomerPageToCustomerClientInterface $customerClient,
        CustomerPageConfig $customerPageConfig
    ) {
        $this->salesClient = $salesClient;
        $this->customerPageConfig = $customerPageConfig;
        $this->customerClient = $customerClient;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Generated\Shared\Transfer\OrderListTransfer
     */
    public function getOrderList(Request $request): OrderListTransfer
    {
        $orderListTransfer = $this->createOrderListTransfer($request);

        if ($this->customerPageConfig->isOrderSearchEnabled()) {
            return $this->salesClient->searchOrders($orderListTransfer);
        }

        return $this->salesClient->getPaginatedCustomerOrdersOverview($orderListTransfer);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Generated\Shared\Transfer\OrderListTransfer
     */
    protected function createOrderListTransfer(Request $request): OrderListTransfer
    {
        $orderListTransfer = new OrderListTransfer();

        $orderListTransfer->setFilter($this->createFilterTransfer());
        $orderListTransfer->setPagination($this->createPaginationTransfer($request));
        $orderListTransfer->setFormat($this->createOrderListFormatTransfer());

        $customerTransfer = $this->customerClient->getCustomer();

        $orderListTransfer->setCustomer($customerTransfer);
        $orderListTransfer->setIdCustomer($customerTransfer->getIdCustomer());

        return $orderListTransfer;
    }

    /**
     * @return \Generated\Shared\Transfer\OrderListFormatTransfer
     */
    protected function createOrderListFormatTransfer(): OrderListFormatTransfer
    {
        $orderListFormatTransfer = new OrderListFormatTransfer();

        $orderListFormatTransfer->setExpandWithItems(
            $this->customerPageConfig->isOrderSearchEnabled() && $this->customerPageConfig->isOrderSearchOrderItemsVisible()
        );

        return $orderListFormatTransfer;
    }

    /**
     * @return \Generated\Shared\Transfer\FilterTransfer
     */
    protected function createFilterTransfer(): FilterTransfer
    {
        $filterTransfer = new FilterTransfer();
        $filterTransfer->setOrderBy($this->customerPageConfig->getDefaultOrderHistorySortField());
        $filterTransfer->setOrderDirection($this->customerPageConfig->getDefaultOrderHistorySortDirection());

        return $filterTransfer;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Generated\Shared\Transfer\PaginationTransfer
     */
    protected function createPaginationTransfer(Request $request): PaginationTransfer
    {
        $paginationTransfer = new PaginationTransfer();

        $paginationTransfer->setPage(
            $request->query->getInt(static::PARAM_PAGE, static::DEFAULT_PAGE)
        );
        $paginationTransfer->setMaxPerPage(
            $request->query->getInt(static::PARAM_PER_PAGE, $this->customerPageConfig->getDefaultOrderHistoryPerPage())
        );

        return $paginationTransfer;
    }
}