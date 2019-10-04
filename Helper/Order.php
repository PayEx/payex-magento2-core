<?php

namespace SwedbankPay\Core\Helper;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceManagementInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order as OrderModel;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Service\InvoiceService;

class Order
{
    const STATUS_PENDING = 'swedbank_pay_pending';
    const STATUS_REVERSED = 'swedbank_pay_reversed';
    const STATUS_CANCELLED_REVERSAL = 'swedbank_pay_cancelled_reversal';

    /**
     * @var OrderRepository
     */
    protected $orderRepo;

    /**
     * @var OrderManagementInterface
     */
    protected $orderManagement;

    /**
     * @var InvoiceRepositoryInterface
     */
    protected $invoiceRepo;

    /**
     * @var InvoiceService|InvoiceManagementInterface
     */
    protected $invoiceManagement;

    /**
     * @var InvoiceSender
     */
    protected $invoiceSender;

    public function __construct(
        OrderRepositoryInterface $orderRepo,
        OrderManagementInterface $orderManagement,
        InvoiceRepositoryInterface $invoiceRepo,
        InvoiceManagementInterface $invoiceManagement,
        InvoiceSender $invoiceSender
    ) {
        $this->orderRepo = $orderRepo;
        $this->orderManagement = $orderManagement;
        $this->invoiceRepo = $invoiceRepo;
        $this->invoiceManagement = $invoiceManagement;
        $this->invoiceSender = $invoiceSender;
    }

    /**
     * @param OrderInterface $order
     * @throws \Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createInvoice(OrderInterface $order)
    {
        /** @var $order OrderModel */
        if (!$order->canInvoice() || $order->getState() == 'new') {
            return;
        }

        $invoice = $this->invoiceManagement->prepareInvoice($order);
        $invoice->register();
        $invoice->capture();

        $this->invoiceRepo->save($invoice);

        $this->invoiceSender->send($invoice);

        $order->addCommentToStatusHistory(
            __('Notified customer about invoice #%1.', $invoice->getId(), false)
        )->setIsCustomerNotified(true);

        $this->orderRepo->save($order);
    }

    public function cancelOrder(OrderInterface $order, $comment = '')
    {
        /** @var $order OrderModel */
        if (!$order->canCancel()) {
            return;
        }

        $this->orderManagement->cancel($order->getId());

        if ($comment) {
            $order->addCommentToStatusHistory($order->getStatus(), $comment);
            $this->orderRepo->save($order);
        }
    }

    public function setStatus(OrderInterface $order, $newStatus, $comment = '')
    {
        /** @var $order OrderModel */
        if ($order->getStatus() == $newStatus) {
            return;
        }

        $newState = true;

        switch ($newStatus) {
            case self::STATUS_PENDING:
                $order->setState(OrderModel::STATE_PENDING_PAYMENT);
                break;
            case self::STATUS_REVERSED:
                $order->setState(OrderModel::STATE_COMPLETE);
                break;
            case self::STATUS_CANCELLED_REVERSAL:
                $order->setState(OrderModel::STATE_PENDING_PAYMENT);
                break;
            default:
                $newState = false;
                break;
        }

        if ($newState) {
            $order->setStatus($newStatus);

            if ($comment) {
                $order->addCommentToStatusHistory($order->getStatus(), $comment);
            }

            $this->orderRepo->save($order);
        }
    }
}
