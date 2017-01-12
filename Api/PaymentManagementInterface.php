<?php

namespace Monext\Payline\Api;

use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\Data\TotalsInterface;

/**
 * @api
 */
interface PaymentManagementInterface
{
    /**
     * @param int $cartId
     * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
     * @param \Magento\Quote\Api\Data\AddressInterface|null $billingAddress
     * @return array
     */
    public function savePaymentInformationFacade(
        $cartId,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress = null
    );
    
    public function wrapCallPaylineApiDoWebPayment($cartId);
        
    public function callPaylineApiDoWebPayment(
        CartInterface $cart,
        TotalsInterface $totals,
        PaymentInterface $payment
    );
}
