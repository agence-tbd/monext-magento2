<?php

namespace Monext\Payline\Controller\WebPayment;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\RawFactory as ResultRawFactory;
use Monext\Payline\Controller\Action;
use Monext\Payline\Model\PaymentManagement as PaylinePaymentManagement;

class NotifyFromPaymentGateway extends Action
{
    /**
     * @var ResultRawFactory 
     */
    protected $resultRawFactory;
    
    /**
     * @var PaylinePaymentManagement 
     */
    protected $paylinePaymentManagement;
    
    public function __construct(
        Context $context,
        ResultRawFactory $resultRawFactory,
        PaylinePaymentManagement $paylinePaymentManagement
    )
    {
        parent::__construct($context);
        $this->resultRawFactory = $resultRawFactory;
        $this->paylinePaymentManagement = $paylinePaymentManagement;
    }
    
    public function execute() 
    {
        // TODO handle exception
        $this->paylinePaymentManagement->handlePaymentGatewayNotifyByToken($this->getToken());
        
        $resultRaw = $this->resultRawFactory->create();
        $resultRaw->setContents('');
        return $resultRaw;
    }
}