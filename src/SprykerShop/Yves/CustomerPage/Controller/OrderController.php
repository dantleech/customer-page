<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerShop\Yves\CustomerPage\Controller;

use Generated\Shared\Transfer\ExpenseTransfer;
use Generated\Shared\Transfer\OrderListTransfer;
use Generated\Shared\Transfer\OrderTransfer;
use SprykerShop\Shared\CustomerPage\CustomerPageConfig;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderController extends AbstractCustomerController
{
    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Spryker\Yves\Kernel\View\View|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function indexAction(Request $request)
    {
        $viewData = $this->executeIndexAction($request);

        return $this->view(
            $viewData,
            $this->getFactory()->getCustomerOrderListWidgetPlugins(),
            '@CustomerPage/views/order/order.twig'
        );
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return array
     */
    protected function executeIndexAction(Request $request): array
    {
        $orderListTransfer = $this->getFactory()
            ->createOrderReader()
            ->getOrderList($request);

        return [
            'pagination' => $orderListTransfer->getPagination(),
            'orderList' => $orderListTransfer->getOrders(),
            'isOrderSearchEnabled' => $this->getFactory()->getConfig()->isOrderSearchEnabled(),
        ];
    }

    /**
     * @param \Generated\Shared\Transfer\OrderListTransfer $orderListTransfer
     * @param bool $isOrderSearchEnabled
     *
     * @return \Generated\Shared\Transfer\OrderListTransfer
     */
    protected function getOrderList(OrderListTransfer $orderListTransfer, bool $isOrderSearchEnabled): OrderListTransfer
    {
        if ($isOrderSearchEnabled) {
            return $this->getFactory()
                ->getSalesClient()
                ->searchOrders($orderListTransfer);
        }

        return $this->getFactory()
            ->getSalesClient()
            ->getPaginatedCustomerOrdersOverview($orderListTransfer);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Spryker\Yves\Kernel\View\View|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function detailsAction(Request $request)
    {
        $responseData = $this->getOrderDetailsResponseData($request->query->getInt('id'));

        return $this->view(
            $responseData,
            $this->getFactory()->getCustomerOrderViewWidgetPlugins(),
            '@CustomerPage/views/order-detail/order-detail.twig'
        );
    }

    /**
     * @param int $idSalesOrder
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     *
     * @return array
     */
    protected function getOrderDetailsResponseData(int $idSalesOrder): array
    {
        $customerTransfer = $this->getLoggedInCustomerTransfer();

        $orderTransfer = new OrderTransfer();
        $orderTransfer->setIdSalesOrder($idSalesOrder)
            ->setFkCustomer($customerTransfer->getIdCustomer());

        $orderTransfer = $this->getFactory()
            ->getSalesClient()
            ->getOrderDetails($orderTransfer);

        if ($orderTransfer->getIdSalesOrder() === null) {
            throw new NotFoundHttpException(sprintf(
                "Order with provided ID %s doesn't exist",
                $idSalesOrder
            ));
        }

        $shipmentGroupCollection = $this->getFactory()
            ->getShipmentService()
            ->groupItemsByShipment($orderTransfer->getItems());

        $shipmentGroupCollection = $this->getFactory()
            ->createShipmentGroupExpander()
            ->expandShipmentGroupsWithCartItems($shipmentGroupCollection, $orderTransfer);

        $orderShipmentExpenses = $this->prepareOrderShipmentExpenses($orderTransfer, $shipmentGroupCollection);

        return [
            'order' => $orderTransfer,
            'shipmentGroups' => $shipmentGroupCollection,
            'orderShipmentExpenses' => $orderShipmentExpenses,
        ];
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     * @param iterable|\Generated\Shared\Transfer\ShipmentGroupTransfer[] $shipmentGroupCollection
     *
     * @return iterable|\Generated\Shared\Transfer\ExpenseTransfer[]
     */
    protected function prepareOrderShipmentExpenses(
        OrderTransfer $orderTransfer,
        iterable $shipmentGroupCollection
    ): iterable {
        $orderShipmentExpenses = [];

        foreach ($orderTransfer->getExpenses() as $expenseTransfer) {
            if (
                $expenseTransfer->getType() !== CustomerPageConfig::SHIPMENT_EXPENSE_TYPE
                || $expenseTransfer->getShipment() === null
            ) {
                continue;
            }

            $shipmentHashKey = $this->findShipmentHashKeyByShipmentExpense($shipmentGroupCollection, $expenseTransfer);
            if ($shipmentHashKey === null) {
                $orderShipmentExpenses[] = $expenseTransfer;

                continue;
            }

            $orderShipmentExpenses[$shipmentHashKey] = $expenseTransfer;
        }

        return $orderShipmentExpenses;
    }

    /**
     * @param iterable|\Generated\Shared\Transfer\ShipmentGroupTransfer[] $shipmentGroupCollection
     * @param \Generated\Shared\Transfer\ExpenseTransfer $expenseTransfer
     *
     * @return string|null
     */
    protected function findShipmentHashKeyByShipmentExpense(
        iterable $shipmentGroupCollection,
        ExpenseTransfer $expenseTransfer
    ): ?string {
        foreach ($shipmentGroupCollection as $shipmentGroupTransfer) {
            if ($expenseTransfer->getShipment()->getIdSalesShipment() !== $shipmentGroupTransfer->getShipment()->getIdSalesShipment()) {
                continue;
            }

            return $shipmentGroupTransfer->getHash();
        }

        return null;
    }
}
