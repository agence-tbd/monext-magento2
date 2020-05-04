<?php

namespace Monext\Payline\Controller\WebPayment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Api\OrderRepositoryInterface;
use Monext\Payline\Controller\Action;

class RedirectToPaymentGateway extends Action
{
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    public function __construct(
        Context $context,
        \Psr\Log\LoggerInterface $loggerPayline,
        OrderRepositoryInterface $orderRepository,
        CheckoutSession $checkoutSession
    )
    {
        parent::__construct($context, $loggerPayline);
        $this->orderRepository = $orderRepository;
        $this->checkoutSession = $checkoutSession;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        try {
            $order = $this->orderRepository->get($this->checkoutSession->getLastOrderId());
            $additionalInformation = $order->getPayment()->getAdditionalInformation();

            // TODO Handle case if data is not present
            $resultRedirect->setUrl($additionalInformation['do_web_payment_response_data']['redirect_url']);
        } catch(\Exception $e) {
            $this->loggerPayline->critical(__CLASS__. ' : ' .__FUNCTION__);
            $this->loggerPayline->critical('Token # '.$this->getToken());
            $this->loggerPayline->critical('Last Order ID # '.$this->checkoutSession->getLastOrderId());
            $this->loggerPayline->critical($e->getMessage());
            $resultRedirect->setPath('checkout');
        }

        return $resultRedirect;
    }
}
