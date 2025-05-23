<?php

namespace Monext\Payline\Helper;

use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject;
use Magento\Framework\Math\Random as MathRandom;
use Magento\Framework\Serialize\Serializer\Json as Serialize;
use Magento\Framework\Validator\EmailAddress as EmailAddressValidator;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Store\Model\StoreManagerInterface;
use Monext\Payline\Helper\Constants as HelperConstants;
use Monext\Payline\PaylineApi\Constants as PaylineApiConstants;
use Monext\Payline\PaylineApi\Response\GetWebPaymentDetails as ResponseGetWebPaymentDetails;
use Magento\Store\Model\ScopeInterface;
use Monext\Payline\Model\System\Config\Source\BillingCycles;

class Data extends AbstractHelper
{
    private $delivery = null;

    private $prefix = null;
    /**
     * @var MathRandom
     */
    protected $mathRandom;

    /**
     * @var Serialize
     */
    protected $serialize;

    /**
     * @var EmailAddressValidator
     */
    protected $emailAddressValidator;

    /**
     * @var BillingCycles
     */
    private  $billingCycles;

    protected  $storeManager;

    /**
     * @param Context $context
     * @param MathRandom $mathRandom
     * @param Serialize $serialize
     * @param EmailAddressValidator $emailAddressValidator
     */
    public function __construct(
        Context $context,
        MathRandom $mathRandom,
        Serialize $serialize,
        EmailAddressValidator $emailAddressValidator,
        StoreManagerInterface $storeManager,
        BillingCycles $billingCycles
    ) {
        parent::__construct($context);

        $this->mathRandom = $mathRandom;
        $this->serialize = $serialize;
        $this->emailAddressValidator = $emailAddressValidator;
        $this->storeManager = $storeManager;
        $this->billingCycles = $billingCycles;
    }

    public function getNormalizedPhoneNumber($phoneNumberCandidate)
    {
        $normalizedPhone = false;
        if(!empty($phoneNumberCandidate)) {
            // "field": "purchase.delivery.recipient.phone_number"
            // format attendu: (+33|508|590|594|596|262|681|687|689)|0033|+33|33|+33(0)|0XXXXXXXXX
            $forbidenPhoneCars = [' ', '.', '(', ')', '-', '/', '\\', '#'];
            //$regexpPhone = '/^\+?[0-9]{1,14}$/';
            $regexpPhone = '/^\+?[0-9]{1,14}$/';

            $normalizedPhone = str_replace($forbidenPhoneCars, '', $phoneNumberCandidate);
            if (!preg_match($regexpPhone, $phoneNumberCandidate)) {
                $normalizedPhone = false;
            }
        }

        return $normalizedPhone;
    }

    public function isEmailValid($emailCandidate)
    {
        $pattern = '/\+/i';

        $charPlusExist = preg_match($pattern, $emailCandidate);

        if (strlen($emailCandidate) <= 50 && $this->emailAddressValidator->isValid($emailCandidate) && !$charPlusExist) {
            return true;
        } else {
            return false;
        }
    }

    public function buildPersonNameFromParts($firstName, $lastName, $prefix = null)
    {
        $name = '';

        if ($prefix) {
            $name .= $prefix . ' ';
        }
        $name .= $firstName;
        $name .= ' ' . $lastName;

        return $name;
    }

    public function generateRandomWalletId()
    {
        return $this->mathRandom->getRandomString(50);
    }

    public function isWalletEnabled($paymentMethod)
    {
        return $this->scopeConfig->getValue('payment/'.$paymentMethod.'/wallet_enabled',
            ScopeInterface::SCOPE_STORE);
    }

    public function mapMagentoAmountToPaylineAmount($magentoAmount)
    {
        return round((float)$magentoAmount * 100, 0);
    }

    public function mapPaylineAmountToMagentoAmount($paylineAmount)
    {
        return $paylineAmount / 100;
    }

    public function getMatchingConfigurableStatus(\Magento\Sales\Model\Order $order, $status)
    {
        if (empty($status)) {
            return null;
        }

        $path = 'payment/' . $order->getPayment()->getMethod() . '/order_status_' . $status;
        if ($configurableStatus = $this->scopeConfig->getValue($path,
            ScopeInterface::SCOPE_STORE)) {
            $status = $configurableStatus;
        }
        return $status;
    }

    public function isPaymentQuoteFromPayline(\Magento\Quote\Model\Quote\Payment $payment)
    {
        return in_array($payment->getMethod(),HelperConstants::AVAILABLE_WEB_PAYMENT_PAYLINE);
    }

    public function isPaymentFromPayline(\Magento\Sales\Model\Order\Payment $payment)
    {
        return in_array($payment->getMethod(),HelperConstants::AVAILABLE_WEB_PAYMENT_PAYLINE);
    }

    public function getDeliverySetting() {
        if(is_null($this->delivery)) {
            $this->delivery = [];
            $addressConfigSerialized = $this->scopeConfig->getValue(HelperConstants::CONFIG_PATH_PAYLINE_DELIVERY,
                ScopeInterface::SCOPE_STORE);
            if ($addressConfigSerialized) {
                try {
                    $this->delivery = $this->serialize->unserialize($addressConfigSerialized);
                } catch (\Exception $e) {
                    $this->_logger->error($e->getMessage());
                }
            }
        }
        return $this->delivery;
    }

    public function getPrefixSetting() {
        if(is_null($this->prefix)) {
            $this->prefix = [];
            $prefixConfigSerialized = $this->scopeConfig->getValue(HelperConstants::CONFIG_PATH_PAYLINE_PREFIX,
                ScopeInterface::SCOPE_STORE);
            if ($prefixConfigSerialized) {
                try {
                    $this->prefix = $this->serialize->unserialize($prefixConfigSerialized);
                } catch (\Exception $e) {
                    $this->_logger->error($e->getMessage());
                }
            }
        }
        return $this->prefix;
    }

    public function getDefaultDeliveryTime() {
        return $this->scopeConfig->getValue(HelperConstants::CONFIG_PATH_PAYLINE_DEFAULT_DELIVERYTIME);
    }

    public function getDefaultDeliveryMode() {
        return $this->scopeConfig->getValue(HelperConstants::CONFIG_PATH_PAYLINE_DEFAULT_DELIVERYMODE);
    }

    public function getDefaultDeliveryExpectedDelay() {
        return $this->scopeConfig->getValue(HelperConstants::CONFIG_PATH_PAYLINE_DEFAULT_DELIVERY_EXPECTED_DELAY);
    }

    public function getDefaultPrefix() {
        return $this->scopeConfig->getValue(HelperConstants::CONFIG_PATH_PAYLINE_DEFAULT_PREFIX);
    }

    public function getNxMinimumAmountCart($store = null)
    {
        $amount = $this->scopeConfig->getValue(HelperConstants::CONFIG_PATH_PAYLINE_NX_MINIMUM_AMOUNT, ScopeInterface::SCOPE_STORE, $store);
        $amount = ($amount < 0) ? 0 : $amount;
        return $amount;
    }

    public function getTokenUsage() {
        return $this->scopeConfig->getValue(HelperConstants::CONFIG_PATH_PAYLINE_GENERAL_TOKEN_USAGE,
            ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return string
     */
    public function getMerchantName()
    {
        $merchantName = $this->scopeConfig->getValue(HelperConstants::CONFIG_PATH_PAYLINE_GENERAL_MERCHANT_NAME,
            ScopeInterface::SCOPE_STORE) ??
            $this->scopeConfig->getValue(\Magento\Store\Model\Information::XML_PATH_STORE_INFO_NAME,
                ScopeInterface::SCOPE_STORE) ??
            '';

        if(empty($merchantName)) {
            $merchantName = $this->storeManager->getStore()->getFrontendName();
        }

        if(empty($merchantName)) {
            $merchantName = $this->storeManager->getStore()->getName();
        }

        return  preg_replace('/[^A-Z0-9]/', '', strtoupper($merchantName));
    }

    /**
     * @param ResponseGetWebPaymentDetails $response
     * @return mixed
     */
    public function getUserMessageForCode(ResponseGetWebPaymentDetails $response)
    {
        $resultCode = $response->getResultCode();

        $configPath = HelperConstants::CONFIG_PATH_PAYLINE_ERROR_TYPE . substr($resultCode, 1,1);
        $errorMessage = $this->scopeConfig->getValue($configPath, ScopeInterface::SCOPE_STORE);
        if(empty($errorMessage)) {
            $errorMessage = $this->scopeConfig->getValue(HelperConstants::CONFIG_PATH_PAYLINE_ERROR_DEFAULT, ScopeInterface::SCOPE_STORE);
        }

        return !empty($errorMessage) ? $errorMessage : $response->getLongErrorMessage();
    }


    public function getDefaultCategories() {
        return array(
            array( 'value' => '1', 'name' => __('Computer (hardware and software)')),
            array( 'value' => '2', 'name' => __('Electronics - TV - Hifi')),
            array( 'value' => '3', 'name' => __('Phone')),
            array( 'value' => '4', 'name' => __('Home appliance')),
            array( 'value' => '5', 'name' => __('Habitat and garden')),
            array( 'value' => '6', 'name' => __('Fashion Clothing')),
            array( 'value' => '7', 'name' => __('Beauty product')),
            array( 'value' => '8', 'name' => __('Jewelry')),
            array( 'value' => '9', 'name' => __('Sport')),
            array( 'value' => '10', 'name' => __('Hobbies')),
            array( 'value' => '11', 'name' => __('Automobiles / motorcycles')),
            array( 'value' => '12', 'name' => __('furnishing')),
            array( 'value' => '13', 'name' => __('children')),
            array( 'value' => '14', 'name' => __('Video games')),
            array( 'value' => '15', 'name' => __('Toys')),
            array( 'value' => '16', 'name' => __('Animals')),
            array( 'value' => '17', 'name' => __('Food')),
            array( 'value' => '18', 'name' => __('Gifts')),
            array( 'value' => '19', 'name' => __('Shows')),
            array( 'value' => '20', 'name' => __('traveling')),
            array( 'value' => '21', 'name' => __('Auction')),
            array( 'value' => '22', 'name' => __('Particular services')),
            array( 'value' => '23', 'name' => __('Professional Services')),
            array( 'value' => '24', 'name' => __('Music')),
            array( 'value' => '25', 'name' => __('Book')),
            array( 'value' => '26', 'name' => __('Photo'))
        );
    }


    /**
     * @return bool
     */
    public function shouldReuseToken()
    {
        if ($this->scopeConfig->getValue('payment/'.HelperConstants::WEB_PAYMENT_CPT.'/integration_type',
                ScopeInterface::SCOPE_STORE) == PaylineApiConstants::INTEGRATION_TYPE_REDIRECT) {
            return false;
        }

        if($this->getTokenUsage() == HelperConstants::TOKEN_USAGE_RECYCLE) {
            return true;
        }
        return false;
    }


    /**
     * @param CartInterface $cart
     * @param ProductCollection $productCollection
     * @param DataObject $totals
     * @param PaymentInterface $payment
     * @param AddressInterface $billingAddress
     * @param AddressInterface|null $shippingAddress
     * @return string
     */
    public function getCartSha(
        CartInterface $cart,
        ProductCollection $productCollection,
        DataObject $totals,
        PaymentInterface $payment,
        AddressInterface $billingAddress,
        AddressInterface $shippingAddress = null
    ) {

        if(!$cart->getReservedOrderId()) {
            return '';
        }

        $shippingCountryId = $billingAddress->getCountryId();
        if ($shippingAddress) {
            $shippingCountryId = $shippingAddress->getCountryId();
        }

        $cartDataKeys = [
            $cart->getId(),
            $billingAddress->getCountryId(),
            $shippingCountryId,
            $totals->getGrandTotal(),
            $totals->getTaxAmount(),
            $totals->getBaseCurrencyCode()
        ];

        return sha1(implode(':', $cartDataKeys));
    }

    /**
     * @param $payment
     * @param \Monext\Payline\PaylineApi\AbstractResponse $response
     * @return void
     */
    public function setPaymentAdditionalInformation($payment, $response, $keys=[]) {

        foreach ($keys as $key) {
            $data = $response->getData()[$key] ?? [];
            foreach (array_filter($data, fn($value) => !is_null($value)) as $dataKey => $dataValue) {
                $payment->setTransactionAdditionalInfo($key.':'.$dataKey, $dataValue);
            }
        }
    }

    /**
     * @param Transaction $transaction
     * @param $key
     * @param $setTxnId
     * @return array
     */
    public function getPaymentDataFromTransaction(Transaction $transaction, $key='payment', $setTxnId=true)
    {
        return $this->getDataFromTransactionAdditionalInformation($transaction, 'payment', true);
    }

    /**
     * @param Transaction $transaction
     * @param $key
     * @param $setTxnId
     * @return array
     */
    protected function getDataFromTransactionAdditionalInformation(Transaction $transaction, $key, $setTxnId=false)
    {
        $paymentData = [];
        foreach ($transaction->getAdditionalInformation() as $paymentKey=>$paymentValue) {
            if(preg_match('/'.$key.':(.*)/', $paymentKey, $match)) {
                $paymentData[$match[1]] = $paymentValue;
            }
        }

        if($setTxnId) {
            $paymentData['transactionID'] = $transaction->getTxnId();
        }

        return $paymentData;
    }

    /**
     * @param string $paymentMethod
     * @return string
     */
    public function getPaymentMode(string $paymentMethod)
    {
        switch ($paymentMethod) {
            case HelperConstants::WEB_PAYMENT_NX:
                return PaylineApiConstants::PAYMENT_MODE_NX;
            case HelperConstants::WEB_PAYMENT_REC:
                return PaylineApiConstants::PAYMENT_MODE_REC;
            default:
                return PaylineApiConstants::PAYMENT_MODE_CPT;
        }
    }

    public function getRecAllowedType($store = null)
    {
        return $this->scopeConfig->getValue(HelperConstants::CONFIG_PATH_PAYLINE_REC_ALLOWED_TYPE, ScopeInterface::SCOPE_STORE, $store);
    }
    public function getRecAllowedProductByType($allowedType, $store = null)
    {
        return $this->scopeConfig->getValue('payment/'.HelperConstants::WEB_PAYMENT_REC.'/allowed_'.$allowedType, ScopeInterface::SCOPE_STORE, $store);
    }
    public function getRecBillingCycle($store = null)
    {
        return $this->scopeConfig->getValue(HelperConstants::CONFIG_PATH_PAYLINE_REC_BILLING_CYCLE, ScopeInterface::SCOPE_STORE, $store);
    }
    public function getRecStartCycle($store = null)
    {
        return $this->scopeConfig->getValue(HelperConstants::CONFIG_PATH_PAYLINE_REC_START_CYCLE, ScopeInterface::SCOPE_STORE, $store);
    }
    public function getRecBillingDay($store = null)
    {
        return $this->scopeConfig->getValue(HelperConstants::CONFIG_PATH_PAYLINE_REC_BILLING_DAY, ScopeInterface::SCOPE_STORE, $store);
    }
    public function getRecBillingNumber($store = null)
    {
        return $this->scopeConfig->getValue(HelperConstants::CONFIG_PATH_PAYLINE_REC_BILLING_NUMBER, ScopeInterface::SCOPE_STORE, $store);
    }

    public function getIntervalMapping()
    {
        return array(
            10 => array('unit' => 'day', 'multiplier' => 1),
            20 => array('unit' => 'week', 'multiplier' => 1),
            30 => array('unit' => 'week', 'multiplier' =>  2),
            40 => array('unit' => 'month', 'multiplier' => 1),
        );
    }
}
