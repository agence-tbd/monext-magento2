<?php

namespace Monext\Payline\Model;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Data\Collection;

use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Checkout\Api\PaymentInformationManagementInterface as CheckoutPaymentInformationManagementInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\BillingAddressManagementInterface as QuoteBillingAddressManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface as QuotePaymentMethodManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ShippingAddressManagementInterface as QuoteShippingAddressManagementInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order as Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\Repository as TransactionRepository;
use Monext\Payline\Api\PaymentManagementInterface as PaylinePaymentManagementInterface;
use Monext\Payline\Helper\Constants as HelperConstants;
use Monext\Payline\Helper\Data as HelperData;
use Monext\Payline\Model\CartManagement as PaylineCartManagement;
use Monext\Payline\Model\OrderIncrementIdTokenManagement;
use Monext\Payline\Model\OrderManagement as PaylineOrderManagement;
use Monext\Payline\PaylineApi\Client as PaylineApiClient;
use Monext\Payline\PaylineApi\Constants as PaylineApiConstants;
use Monext\Payline\PaylineApi\Request\DoCaptureFactory as RequestDoCaptureFactory;
use Monext\Payline\PaylineApi\Request\DoVoidFactory as RequestDoVoidFactory;
use Monext\Payline\PaylineApi\Request\DoRefundFactory as RequestDoRefundFactory;
use Monext\Payline\PaylineApi\Request\DoWebPaymentFactory as RequestDoWebPaymentFactory;
use Monext\Payline\PaylineApi\Request\GetWebPaymentDetailsFactory as RequestGetWebPaymentDetailsFactory;
use Monext\Payline\PaylineApi\Response\GetWebPaymentDetails as ResponseGetWebPaymentDetails;
use Monext\Payline\PaylineApi\Request\GetPaymentRecordFactory as PaymentRecordRequestFactory;
use Monolog\Logger as LoggerConstants;
use Psr\Log\LoggerInterface as Logger;

class PaymentManagement implements PaylinePaymentManagementInterface
{
    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepository;

    /**
     * @var CheckoutPaymentInformationManagementInterface
     */
    protected $checkoutPaymentInformationManagement;

    /**
     * @var QuotePaymentMethodManagementInterface
     */
    protected $quotePaymentMethodManagement;

    /**
     * @var RequestDoWebPaymentFactory
     */
    protected $requestDoWebPaymentFactory;

    /**
     * @var RequestGetWebPaymentDetailsFactory
     */
    protected $requestGetWebPaymentDetailsFactory;

    /**
     * @var RequestDoCaptureFactory
     */
    protected $requestDoCaptureFactory;

    /**
     * @var RequestDoVoidFactory
     */
    protected $requestDoVoidFactory;

    /**
     * @var RequestDoRefundFactory
     */
    protected $requestDoRefundFactory;

    /**
     * @var PaylineApiClient
     */
    protected $paylineApiClient;

    /**
     * @var PaylineCartManagement
     */
    protected $paylineCartManagement;

    /**
     * @var OrderIncrementIdTokenManagement
     */
    protected $orderIncrementIdTokenManagement;

    /**
     * @var TransactionRepository
     */
    protected $transactionRepository;

    /**
     * @var QuoteBillingAddressManagementInterface
     */
    protected $quoteBillingAddressManagement;

    /**
     * @var QuoteShippingAddressManagementInterface
     */
    protected $quoteShippingAddressManagement;

    /**
     * @var PaylineOrderManagement
     */
    protected $paylineOrderManagement;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var WalletManagement
     */
    protected $walletManagement;

    /**
     * @var HelperData
     */
    protected $helperData;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Logger
     */
    public $paylineLogger;

    /**
     * @var PaymentTypeManagementFactory
     */
    protected $paymentTypeManagementFactory;

    /**
     * @var PaymentRecordRequestFactory
     */
    protected $paymentRecordRequestFactory;

    /**
     * @var \Magento\Sales\Model\Order\Payment\Transaction\ManagerInterface
     */
    protected $transactionManager;

    /**
     * @var array
     */
    protected $gwpdResponseByToken = [];

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var SortOrderBuilder
     */
    private $sortOrderBuilder;


    /**
     * @param CartRepositoryInterface $cartRepository
     * @param CheckoutPaymentInformationManagementInterface $checkoutPaymentInformationManagement
     * @param QuotePaymentMethodManagementInterface $quotePaymentMethodManagement
     * @param RequestDoWebPaymentFactory $requestDoWebPaymentFactory
     * @param PaylineApiClient $paylineApiClient
     * @param CartManagement $paylineCartManagement
     * @param \Monext\Payline\Model\OrderIncrementIdTokenManagement $orderIncrementIdTokenManagement
     * @param RequestGetWebPaymentDetailsFactory $requestGetWebPaymentDetailsFactory
     * @param TransactionRepository $transactionRepository
     * @param RequestDoCaptureFactory $requestDoCaptureFactory
     * @param RequestDoVoidFactory $requestDoVoidFactory
     * @param RequestDoRefundFactory $requestDoRefundFactory
     * @param QuoteBillingAddressManagementInterface $quoteBillingAddressManagement
     * @param QuoteShippingAddressManagementInterface $quoteShippingAddressManagement
     * @param Transaction\ManagerInterface $transactionManager
     * @param OrderManagement $paylineOrderManagement
     * @param Logger $logger
     * @param WalletManagement $walletManagement
     * @param HelperData $helperData
     * @param FilterBuilder $filterBuilder
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param ScopeConfigInterface $scopeConfig
     * @param PaymentTypeManagementFactory $paymentTypeManagementFactory
     * @param PaymentRecordRequestFactory $paymentRecordRequestFactory
     * @param Logger $paylineLogger
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        CheckoutPaymentInformationManagementInterface $checkoutPaymentInformationManagement,
        QuotePaymentMethodManagementInterface $quotePaymentMethodManagement,
        RequestDoWebPaymentFactory $requestDoWebPaymentFactory,
        PaylineApiClient $paylineApiClient,
        PaylineCartManagement $paylineCartManagement,
        OrderIncrementIdTokenManagement $orderIncrementIdTokenManagement,
        RequestGetWebPaymentDetailsFactory $requestGetWebPaymentDetailsFactory,
        TransactionRepository $transactionRepository,
        RequestDoCaptureFactory $requestDoCaptureFactory,
        RequestDoVoidFactory $requestDoVoidFactory,
        RequestDoRefundFactory $requestDoRefundFactory,
        QuoteBillingAddressManagementInterface $quoteBillingAddressManagement,
        QuoteShippingAddressManagementInterface $quoteShippingAddressManagement,
        \Magento\Sales\Model\Order\Payment\Transaction\ManagerInterface $transactionManager,
        PaylineOrderManagement $paylineOrderManagement,
        Logger $logger,
        WalletManagement $walletManagement,
        HelperData $helperData,
        FilterBuilder $filterBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder,
        ScopeConfigInterface $scopeConfig,
        PaymentTypeManagementFactory $paymentTypeManagementFactory,
        PaymentRecordRequestFactory $paymentRecordRequestFactory,
        Logger $paylineLogger
    )
    {
        $this->cartRepository = $cartRepository;
        $this->checkoutPaymentInformationManagement = $checkoutPaymentInformationManagement;
        $this->quotePaymentMethodManagement = $quotePaymentMethodManagement;
        $this->requestDoWebPaymentFactory = $requestDoWebPaymentFactory;
        $this->paylineApiClient = $paylineApiClient;
        $this->paylineCartManagement = $paylineCartManagement;
        $this->orderIncrementIdTokenManagement = $orderIncrementIdTokenManagement;
        $this->requestGetWebPaymentDetailsFactory = $requestGetWebPaymentDetailsFactory;
        $this->transactionRepository = $transactionRepository;
        $this->requestDoCaptureFactory = $requestDoCaptureFactory;
        $this->requestDoVoidFactory = $requestDoVoidFactory;
        $this->requestDoRefundFactory = $requestDoRefundFactory;
        $this->quoteBillingAddressManagement = $quoteBillingAddressManagement;
        $this->quoteShippingAddressManagement = $quoteShippingAddressManagement;
        $this->paylineOrderManagement = $paylineOrderManagement;
        $this->logger = $logger;
        $this->walletManagement = $walletManagement;
        $this->helperData = $helperData;
        $this->filterBuilder = $filterBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->paylineLogger = $paylineLogger;
        $this->paymentTypeManagementFactory = $paymentTypeManagementFactory;
        $this->paymentRecordRequestFactory = $paymentRecordRequestFactory;
        $this->transactionManager = $transactionManager;
    }

    /**
     * @param $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return array|\Monext\Payline\Api\anyType
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function saveCheckoutPaymentInformationFacade(
        $cartId,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress = null
    )
    {
        $this->checkoutPaymentInformationManagement->savePaymentInformation($cartId, $paymentMethod, $billingAddress);
        return $this->wrapCallPaylineApiDoWebPaymentFacade($cartId);
    }

    /**
     * @param $cartId
     * @return array
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function wrapCallPaylineApiDoWebPaymentFacade($cartId)
    {
        $cart = $this->cartRepository->getActive($cartId);
        $response = $this->callPaylineApiDoWebPaymentFacade(
            $cart,
            $this->paylineCartManagement->getProductCollectionFromCart($cartId),
            $this->quotePaymentMethodManagement->get($cartId),
            $this->quoteBillingAddressManagement->get($cartId),
            $cart->getIsVirtual() ? null : $this->quoteShippingAddressManagement->get($cartId)
        );

        return [
            'token' => $response->getToken(),
            'redirect_url' => $response->getRedirectUrl(),
        ];
    }


    /**
     * @param $cartId
     * @return DataObject
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getTotalsForCartId($cartId) {

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->cartRepository->getActive($cartId);
        if ($quote->isVirtual()) {
            $quote->collectTotals();
        }

        $totals = [
            'grand_total' => $quote->getGrandTotal(),
            'base_currency_code' => $quote->getBaseCurrencyCode(),
            'tax_amount' => $quote->getShippingAddress()->getTaxAmount(),
            'shipping_amount' => $quote->getShippingAddress()->getShippingInclTax(),
            'discount_amount' => $quote->getShippingAddress()->getDiscountAmount()
        ];

        return new DataObject($totals);
    }



    /**
     * @param CartInterface $cart
     * @param ProductCollection $productCollection
     * @param PaymentInterface $payment
     * @param AddressInterface $billingAddress
     * @param AddressInterface|null $shippingAddress
     * @return \Monext\Payline\PaylineApi\Response\DoWebPayment
     * @throws \Exception
     */
    protected function callPaylineApiDoWebPaymentFacade(
        CartInterface $cart,
        ProductCollection $productCollection,
        PaymentInterface $payment,
        AddressInterface $billingAddress,
        AddressInterface $shippingAddress = null
    )
    {
        $totals = $this->getTotalsForCartId($cart->getId());

        $logData = [
            'cart_id' => $cart->getId(),
            'grand_total' => $totals->getGrandTotal(),
            'shipping_amount' => $totals->getShippingInclTax(),
            'discount_amount' => $totals->getDiscountAmount(),
        ];
        $this->paylineLogger->debug(__METHOD__, $logData);

        $cartSha = $this->helperData->getCartSha($cart, $productCollection, $totals, $payment, $billingAddress, $shippingAddress);
        if($cart->getReservedOrderId() && $this->helperData->shouldReuseToken()) {
            $orderToken = $this->orderIncrementIdTokenManagement->getExistingTokenByOrderIncrementId($cart->getReservedOrderId(), $cartSha);

            if($orderToken && $orderToken->getToken()) {
                $orderToken->setRedirectUrl();
                return $orderToken;
            }
        }

        $this->paylineCartManagement->handleReserveCartOrderId($cart->getId());

        $this->paylineLogger->debug(__METHOD__, ['reserved_order_id' => $cart->getReservedOrderId()]);

        if ($cart->getIsVirtual()) {
            $shippingAddress = null;
        }

        $response = $this->callPaylineApiDoWebPayment($cart, $productCollection, $totals, $payment, $billingAddress, $shippingAddress);
        if (!$response->isSuccess()) {
            throw new \Exception($response->getShortErrorMessage());
        }

        $this->orderIncrementIdTokenManagement->associateTokenToCart(
            $cart,
            $response->getToken(),
            $cartSha
        );

        return $response;
    }

    /**
     * @param CartInterface $cart
     * @param ProductCollection $productCollection
     * @param DataObject $totals
     * @param PaymentInterface $payment
     * @param AddressInterface $billingAddress
     * @param AddressInterface|null $shippingAddress
     * @return \Monext\Payline\PaylineApi\Response\DoWebPayment
     */
    protected function callPaylineApiDoWebPayment(
        CartInterface $cart,
        ProductCollection $productCollection,
        DataObject $totals,
        PaymentInterface $payment,
        AddressInterface $billingAddress,
        AddressInterface $shippingAddress = null
    )
    {
        /** @var \Monext\Payline\PaylineApi\Request\DoWebPayment $request */
        $request = $this->requestDoWebPaymentFactory->create();

        $request
            ->setCart($cart)
            ->setProductCollection($productCollection)
            ->setTotals($totals)
            ->setBillingAddress($billingAddress)
            ->setShippingAddress($shippingAddress)
            ->setPayment($payment);

        return $this->paylineApiClient->callDoWebPayment($request);
    }

    /**
     * @param $token
     * @return mixed|ResponseGetWebPaymentDetails
     */
    protected function callPaylineApiGetWebPaymentDetails($token)
    {

        if(!isset($this->gwpdResponseByToken[$token])) {
            $request = $this->requestGetWebPaymentDetailsFactory->create();
            $request->setToken($token);

            $this->gwpdResponseByToken[$token] = $this->paylineApiClient->callGetWebPaymentDetails($request);
        }

        return $this->gwpdResponseByToken[$token];
    }

    /**
     * @param TransactionInterface $authorizationTransaction
     * @param array $paymentData
     * @return \Monext\Payline\PaylineApi\Response\DoCapture
     */
    protected function callPaylineApiDoCapture(
        TransactionInterface $authorizationTransaction,
        array $paymentData
    )
    {
        $request = $this->requestDoCaptureFactory->create();
        $request
            ->setAuthorizationTransaction($authorizationTransaction)
            ->setPaymentData($paymentData);

        return $this->paylineApiClient->callDoCapture($request);
    }

    /**
     * @param array $paymentData
     * @return \Monext\Payline\PaylineApi\ResponseDoVoid
     */
    protected function callPaylineApiDoVoid(
        array $paymentData
    )
    {
        $request = $this->requestDoVoidFactory->create();
        $request->setPaymentData($paymentData);

        return $this->paylineApiClient->callDoVoid($request);
    }

    /**
     * @param OrderInterface $order
     * @param OrderPaymentInterface $payment
     * @param array $paymentData
     * @return \Monext\Payline\PaylineApi\ResponseDoRefund
     */
    protected function callPaylineApiDoRefund(
        OrderInterface $order,
        OrderPaymentInterface $payment,
        array $paymentData
    )
    {
        $request = $this->requestDoRefundFactory->create();
        $request
            ->setOrder($order)
            ->setPayment($payment)
            ->setPaymentData($paymentData);

        return $this->paylineApiClient->callDoRefund($request);
    }

    /**
     * @param $contractNumber
     * @param $paymentRecordId
     * @return \Monext\Payline\PaylineApi\Response\GetPaymentRecord
     */
    protected function callPaylinePaymentRecord($contractNumber, $paymentRecordId)
    {
        $request = $this->paymentRecordRequestFactory->create();
        $request->setContractNumber($contractNumber)
            ->setPaymentRecordId($paymentRecordId);

        return $this->paylineApiClient->callGetPaymentRecord($request);
    }

    /**
     * @param $token
     * @return $this
     * @throws LocalizedException
     */
    public function synchronizeNotificationPaymentWithPaymentGatewayFacade($token)
    {
        // IN CASE PAYMENT METHOD IS NOT PAYLINE WE EXIT BEFORE DOING ANYTHING
        $quote = $this->paylineCartManagement->getCartByToken($token);
        $logData = [
            'token' => $token,
            'quote_id' => $quote->getId(),
        ];

        $order = $this->paylineOrderManagement->getOrderByToken($token);
        if (!$order->getId()) {
            //Call GWPD to stop Payline notification
            $response = $this->callPaylineApiGetWebPaymentDetails($token);
            if($response->isAbandoned()) {
                $this->orderIncrementIdTokenManagement->flagTokenAsDisabled($token);
                $this->paylineLogger->info("Abandoned payment, call callPaylineApiGetWebPaymentDetails to stop notifications", $logData);
                return $this;
            }
        }

        return $this->synchronizePaymentWithPaymentGatewayFacade($token);
    }

    /**
     * @param $token
     * @param $restoreCartOnError
     * @return $this
     *
     * @throws LocalizedException
     */
    public function synchronizePaymentWithPaymentGatewayFacade($token, $restoreCartOnError = false)
    {
        // IN CASE PAYMENT METHOD IS NOT PAYLINE WE EXIT BEFORE DOING ANYTHING
        $quote = $this->paylineCartManagement->getCartByToken($token);
        $logData = [
            'token' => $token,
            'quote_id' => $quote->getId(),
        ];

        if($quote && $quote->getId()) {
            $this->paylineOrderManagement->checkQuotePaymentFromPayline($quote);
            $response = $this->callPaylineApiGetWebPaymentDetails($token);
            $transactionData = $response->getTransactionData();
            // IN CASE THERE IS NO TRANSACTION NO NEED TO CREATE AN ORDER
            if(empty($transactionData) || empty($transactionData['id'])) {
                $this->paylineLogger->info("Empty transaction data, payment is not finalized.", $logData);
                throw new \Exception('Payment is not finalized.');
            }
        } else {
            $this->paylineLogger->info("Cannot retrieve valid customer cart.", $logData);
            throw new \Exception('Cannot retrieve valid customer cart.');
        }

        $order = $this->paylineOrderManagement->getOrderByToken($token);
        if (!$order->getId()) {
            $this->paylineCartManagement->placeOrderByToken($token);
            $order = $this->paylineOrderManagement->getOrderByToken($token);
        }

        $logData = array_merge($logData, [
            'order_id' => $order->getId(),
            'grand_total' => $order->getGrandTotal(),
            'shipping_amount' => $order->getShippingInclTax(),
            'discount_amount' => $order->getDiscountAmount(),
        ]);
        $this->paylineLogger->debug(__METHOD__, $logData);

        $this->synchronizePaymentWithPaymentGateway($order->getPayment(), $token);

        if ($order->getPayment()->getData('payline_in_error')) {
            if ($restoreCartOnError) {
                $this->paylineCartManagement->restoreCartFromOrder($order);
            }

            $errorMessage = $order->getPayment()->hasData('payline_user_message') ? $order->getPayment()->getData('payline_user_message') : 'Payment is in error.';
            if($order->getPayment()->getData('payline_response')) {
                $errorMessage = $this->helperData->getUserMessageForCode($order->getPayment()->getData('payline_response'));
            }

            throw new LocalizedException(__($errorMessage));
        }

        return $this;
    }

    /**
     * @param OrderPayment $payment
     * @param $token
     * @return $this
     * @throws \Exception
     */
    protected function synchronizePaymentWithPaymentGateway(OrderPayment $payment, $token)
    {
        $response = $this->callPaylineApiGetWebPaymentDetails($token);
        $firstTransaction = $this->getFirstTransactionForOrder($payment->getOrder(), [TransactionInterface::TYPE_CAPTURE, TransactionInterface::TYPE_AUTH, TransactionInterface::TYPE_ORDER, TransactionInterface::TYPE_PAYMENT]);

        if($firstTransaction && $firstTransaction->getId()) {
            if($response->isDuplicate()) {
                $message = __('Duplicate detected. Payline API call to getWebPaymentDetails with return "%1 : %2" was ignored', $response->getResultCode(), $response->getLongErrorMessage());
            } else {
                $message = __('Tansaction already exist for this order. Payline API call to getWebPaymentDetails with return "%1 : %2" was ignored', $response->getResultCode(), $response->getLongErrorMessage());
            }

            $payment->getOrder()->addStatusHistoryComment($message);
            $payment->getOrder()->save();

            $exceptionMessage = 'Transaction already exists for this order.';
            if($response->isSuccess() && $this->callPaylineApiDoCancelPaymentFacade($payment, $response)) {
                $exceptionMessage .= '. Payline transaction successfully void or refund.';
            }
            throw new \Exception($exceptionMessage);
        }

        $payment->setData('payline_response', $response);
        $paymentTypeManagement = $this->paymentTypeManagementFactory->create($payment);

        $needCancelPayment = false;
        if ($response->isSuccess()) {
            if ($paymentTypeManagement->validate($response, $payment)) {
                $this->handlePaymentSuccessFacade($response, $payment);
            } else {
                $needCancelPayment = true;
            }
        } elseif ( $response->isWaitingAcceptance() ) {
            if ($paymentTypeManagement->validate($response, $payment)) {
                // TODO: Need to asssociate a transaction
                //$this->handlePaymentWaitingAcceptance($payment, $message);
                $this->handlePaymentWaitingAcceptanceFacade($response, $payment);
            } else {
                $needCancelPayment = true;
            }
        } else {
            $this->flagPaymentAsInError($payment, $response);
            if ($response->isCanceled()) {
                $paymentTypeManagement->handlePaymentCanceled($payment);
            } elseif ($response->isAbandoned()) {
                $this->handlePaymentAbandoned($payment);
            } elseif ($response->isFraud()) {
                $this->handlePaymentFraud($payment);
            } else {
                $this->handlePaymentRefused($payment);
            }
        }

        $payment->getOrder()->save();

        if($needCancelPayment) {
            if($this->callPaylineApiDoCancelPaymentFacade($payment, $response)) {
                $paymentTypeManagement->handlePaymentCanceled($payment);
            }
        }

        return $this;
    }

    /**
     * @param ResponseGetWebPaymentDetails $response
     * @param OrderPayment $payment
     * @return $this
     * @throws \Exception
     */
    protected function handlePaymentSuccessFacade(
        ResponseGetWebPaymentDetails $response,
        OrderPayment $payment
    )
    {
        $paymentTypeManagement = $this->paymentTypeManagementFactory->create($payment);
        $paymentTypeManagement->handlePaymentSuccess($response, $payment);
        $this->walletManagement->handleWalletReturnFromPaymentGateway($response, $payment);
        $this->paylineOrderManagement->sendNewOrderEmail($payment->getOrder());

        return $this;
    }

    /**
     * @param OrderPayment $payment
     * @param $message
     * @return void
     */
    protected function handlePaymentFraud(OrderPayment $payment, $message = null)
    {
        $this->paylineOrderManagement->handleSetOrderStateStatus(
            $payment->getOrder(),
            Order::STATE_PROCESSING,
            HelperConstants::ORDER_STATUS_PAYLINE_FRAUD,
            $message ?? $payment->getData('payline_error_message')
        );
    }

    /**
     * @param OrderPayment $payment
     * @param $message
     * @return void
     */
    protected function handlePaymentWaitingAcceptance(OrderPayment $payment, $message = null)
    {
        $this->paylineOrderManagement->handleSetOrderStateStatus(
            $payment->getOrder(),
            Order::STATE_PROCESSING,
            HelperConstants::ORDER_STATUS_PAYLINE_WAITING_ACCEPTANCE,
            $message ?? $payment->getData('payline_error_message')
        );
    }

    /**
     * @param ResponseGetWebPaymentDetails $response
     * @param OrderPayment $payment
     * @return $this
     */
    protected function handlePaymentWaitingAcceptanceFacade(
        ResponseGetWebPaymentDetails $response,
        OrderPayment $payment
    )
    {
        $message = $response->getResultCode() . ' : ' . $response->getShortErrorMessage();
        $this->handlePaymentWaitingAcceptance($payment, $message);
// TODO: Need to asssociate a transaction
//        $paymentTypeManagement = $this->paymentTypeManagementFactory->create($payment);
//        $paymentTypeManagement->handlePaymentWaitingAcceptance($response, $payment);
//        $this->walletManagement->handleWalletReturnFromPaymentGateway($response, $payment);
//        $this->paylineOrderManagement->sendNewOrderEmail($payment->getOrder());

        return $this;
    }


    /**
     * @param OrderPayment $payment
     * @param $message
     * @return void
     */
    protected function handlePaymentAbandoned(OrderPayment $payment, $message = null)
    {
        $this->paylineOrderManagement->handleSetOrderStateStatus(
            $payment->getOrder(),
            Order::STATE_CANCELED,
            HelperConstants::ORDER_STATUS_PAYLINE_ABANDONED,
            $message ?? $payment->getData('payline_error_message')
        );
    }

    /**
     * @param OrderPayment $payment
     * @param $message
     * @return void
     */
    protected function handlePaymentRefused(OrderPayment $payment, $message = null)
    {
        $this->paylineOrderManagement->handleSetOrderStateStatus(
            $payment->getOrder(),
            Order::STATE_CANCELED,
            HelperConstants::ORDER_STATUS_PAYLINE_REFUSED,
            $message ?? $payment->getData('payline_error_message')
        );
    }

    /**
     * @param OrderInterface $order
     * @param OrderPaymentInterface $payment
     * @param $amount
     * @return $this
     * @throws \Magento\Framework\Exception\InputException
     */
    public function callPaylineApiDoCaptureFacade(
        OrderInterface $order,
        OrderPaymentInterface $payment,
        $amount
    )
    {
        $token = $this->orderIncrementIdTokenManagement->getTokenByOrderIncrementId($order->getIncrementId());
        $response1 = $this->callPaylineApiGetWebPaymentDetails($token);

        if (!$response1->isSuccess()) {
            // TODO log
            throw new \Exception($response1->getShortErrorMessage());
        }

        $paymentData = $response1->getPaymentData();
        $paymentData['amount'] = $this->helperData->mapMagentoAmountToPaylineAmount($amount);

        $authorizationTransaction = $this->transactionRepository->getByTransactionType(
            Transaction::TYPE_AUTH,
            $payment->getId()
        );

        if (!$authorizationTransaction) {
            // TODO log
            throw new \Exception(__('No authorization transaction found for this order.'));
        }

        $response2 = $this->callPaylineApiDoCapture($authorizationTransaction, $paymentData);

        if (!$response2->isSuccess()) {
            // TODO log
            throw new \Exception($response2->getShortErrorMessage());
        }

        $payment->setTransactionId($response2->getTransactionId());

        return $this;
    }

    /**
     * @param OrderInterface $order
     * @param $payment
     * @return $this
     * @throws \Exception
     */
    public function callPaylineApiDoVoidFacade(
        OrderInterface $order,
        $payment
    )
    {
        // Check existing transaction - else void impossible
        if (!$payment->getTransactionId()) {
            $this->logger->log(LoggerConstants::ERROR, 'No transaction found for this order : ' . $order->getId());
            throw new \Exception(__('No transaction found for this order.'));
        }

        // Get API token
        $token = $this->orderIncrementIdTokenManagement->getTokenByOrderIncrementId($order->getIncrementId());
        $response1 = $this->callPaylineApiGetWebPaymentDetails($token);

        if (!$response1->isSuccess()) {
            $this->logger->log(LoggerConstants::ERROR, 'No payment details found : ' . $response1->getLongErrorMessage());
            throw new \Exception($response1->getShortErrorMessage());
        }

        $paymentData = $response1->getPaymentData();
        $paymentData['transactionID'] = $payment->getTransactionId();
        $paymentData['comment'] = __(
            'Transaction %s canceled for order %s from Magento Back-Office',
            $payment->getTransactionId(),
            $order->getRealOrderId()
        )->render();

        // Call API
        $response2 = $this->callPaylineApiDoVoid($paymentData);

        if (!$response2->isSuccess()) {
            $this->logger->log(LoggerConstants::ERROR, 'DoVoid error : ' . $response2->getLongErrorMessage());
            throw new \Exception($response2->getShortErrorMessage());
        }

        return $this;
    }

    /**
     * @param OrderPaymentInterface $payment
     * @param $paymentRecordId
     * @return $this
     * @throws \Exception
     */
    public function callPaylinePaymentRecordFacade(OrderPaymentInterface $payment, $paymentRecordId)
    {
        if ($payment->getOrder()->hasData('save_mode')) {
            $this->paylineLogger->debug('BO Ship Mode');
        } else {
            $this->paylineLogger->debug('NotifyCyclingPaymentFromPaymentGateway Mode');
        }

        $response = $this->callPaylinePaymentRecord($payment->getAdditionalInformation('contract_number'), $paymentRecordId);
        $orderIsUpdated = false;
        if (!$response->isSuccess()) {
            $this->logger->log(LoggerConstants::ERROR, 'PaymentRecord error : ' . $response->getLongErrorMessage());
            throw new \Exception($response->getShortErrorMessage());
        }

        $nbTxnSuccess = 0;
        foreach ($response->getBillingRecords() as $record) {
            if (in_array($record['status'], PaylineApiConstants::PAYMENT_BACK_CODES_RETURN_CYCLING_SUCCESS)) {
                ++$nbTxnSuccess;
                if ($this->checkIfTransactionExists($record['transaction']['id'], $payment) === false) {
                    $payment->setTransactionId($record['transaction']['id']);
                    $payment->setParentTransactionId($payment->getLastTransId());
                    $payment->setTransactionAdditionalInfo('payline_record', $record['transaction']);
                    $payment->registerCaptureNotification($this->helperData->mapPaylineAmountToMagentoAmount($record['amount']), true);
                    $orderIsUpdated = true;
                }
            } elseif (in_array($record['status'], PaylineApiConstants::PAYMENT_BACK_CODES_RETURN_CYCLING_ERROR)) {
                $payment->getOrder()->addStatusHistoryComment(__('Error code %1 => %2', $record['result']['code'], $record['result']['longMessage']), false);
                $orderIsUpdated = true;
            }
        }

        $this->paylineLogger->debug('Count billing records : ' . count($response->getBillingRecords()));
        $this->paylineLogger->debug('Nb records Sucess : ' . $nbTxnSuccess);
        if (count($response->getBillingRecords()) === $nbTxnSuccess) {
            $switchStatus = false;
            if (
                $payment->getOrder()->getState() === Order::STATE_COMPLETE
                && $payment->getOrder()->getStatus() === HelperConstants::ORDER_STATUS_PAYLINE_CYCLE_PAYMENT_CAPTURE
            ) {
                $switchStatus = true;
            }
            $this->paylineLogger->debug('Switch Status : ' . $switchStatus);
            $payment->getOrder()->addStatusHistoryComment(__('All payment cycle received'), $switchStatus);
            $payment->getOrder()->setPaiementCompleted(true);
            $orderIsUpdated = true;
        } else {
            $payment->getOrder()->setPaiementCompleted(false);
        }

        $isPaymentCyclingCompleted = ($payment->getOrder()->getPaiementCompleted()) ? '1' : '0';
        $this->paylineLogger->debug('Paiement Cycling Completed : ' . $isPaymentCyclingCompleted);
        $this->paylineLogger->debug('Order status : ' . $payment->getOrder()->getStatus());
        $this->paylineLogger->debug('Order state : ' . $payment->getOrder()->getState());

        //save_mode => flag pour la livraison après ou pendant les échéances
        if ($orderIsUpdated === true && !$payment->getOrder()->hasData('save_mode')) {
            $payment->getOrder()->save();
        }

        return $this;
    }

    /**
     * @param $transactionId
     * @param OrderPayment $payment
     * @return bool
     */
    protected function checkIfTransactionExists($transactionId, OrderPayment $payment)
    {
        return $this->transactionManager->isTransactionExists(
            $transactionId,
            $payment->getId(),
            $payment->getOrder()->getId()
        );
    }


    /**
     * @param OrderInterface $order
     * @return \Magento\Sales\Model\Order\Payment\Transaction
     */
    protected function getFirstTransactionForOrder(OrderInterface $order, $txnTypes = []) {

        $searchCriteria = $this->searchCriteriaBuilder;

        // Get first transaction used - Always use it for refund
        $searchCriteria->addFilter(TransactionInterface::ORDER_ID,$order->getId());

        if(!empty($txnTypes)) {
            $searchCriteria->addFilter(TransactionInterface::TXN_TYPE, $txnTypes, "in");
        }

        $searchCriteria->addSortOrder($this->sortOrderBuilder
            ->setField('created_at')
            ->setDirection(Collection::SORT_ORDER_ASC)
            ->create());

        return $this->transactionRepository->getList($searchCriteria->create())->getFirstItem();
    }

    public function callPaylineApiDoRefundFacade(
        OrderInterface $order,
        $payment,
        $amount
    )
    {
        $transaction = $this->getFirstTransactionForOrder($order, [TransactionInterface::TYPE_CAPTURE, TransactionInterface::TYPE_ORDER, TransactionInterface::TYPE_PAYMENT]);

        // Check existing transaction - else refund impossible
        if (!$transaction || ($transaction && !trim($transaction->getTxnId()))) {
            $this->logger->log(LoggerConstants::ERROR, 'No transaction found for this order : ' . $order->getId());
            throw new \Exception(__('No transaction found for this order.'));
        }

        // Get API token
        $token = $this->orderIncrementIdTokenManagement->getTokenByOrderIncrementId($order->getIncrementId());
        $response1 = $this->callPaylineApiGetWebPaymentDetails($token);

        if (!$response1->isSuccess()) {
            $this->logger->log(LoggerConstants::ERROR, 'No payment details found : ' . $response1->getLongErrorMessage());
            throw new \Exception($response1->getShortErrorMessage());
        }

        $paymentData = $response1->getPaymentData();
        $paymentData['amount'] = $this->helperData->mapMagentoAmountToPaylineAmount($amount);
        $paymentData['transactionID'] = $transaction->getTxnId();
        $paymentData['comment'] = __(
            'Transaction %s refunded for order %s from Magento Back-Office',
            $payment->getTransactionId(),
            $order->getRealOrderId()
        )->render();

        // Call API
        $response2 = $this->callPaylineApiDoRefund($order, $payment, $paymentData);

        if (!$response2->isSuccess()) {
            $this->logger->log(LoggerConstants::ERROR, 'DoRefund error : ' . $response2->getLongErrorMessage());
            throw new \Exception($response2->getShortErrorMessage());
        }

        $payment->setTransactionId($response2->getTransactionId());
        $payment->setParentTransactionId($transaction->getTxnId());

        return $this;
    }


    /**
     * @param OrderInterface $order
     * @param ResponseGetWebPaymentDetails $response
     * @param $amount
     * @return void|bool
     * @throws \Exception
     */
    public function callPaylineApiDoCancelPaymentFacade(
        OrderPayment $payment,
        ResponseGetWebPaymentDetails $response
    )
    {
        $order = $payment->getOrder();

        $paymentData = $response->getPaymentData();
        $transactionData = $response->getTransactionData();

        if(empty($transactionData['id'])) {
            return false;
        }

        $paymentData['transactionID'] = $transactionData['id'];
        $paymentData['comment'] = __(
            'Reset transaction %1 for order %2',
            $transactionData['id'],
            $order->getRealOrderId()
        )->render();
        $response1 = $this->callPaylineApiDoVoid($paymentData);

        if ($response1->isSuccess()) {
            $payment->getOrder()->addCommentToStatusHistory($paymentData['comment'])->save();
        } else {
            $this->paylineLogger->log(LoggerConstants::ERROR, 'DoRefund error : ' . $response1->getLongErrorMessage());
            $paymentData['comment'] = __(
                'Refund transaction %1 for order %2',
                $transactionData['id'],
                $order->getRealOrderId()
            )->render();

            $response2 = $this->callPaylineApiDoRefund($order, $order->getPayment(), $paymentData);
            if ($response2->isSuccess()) {
                $payment->getOrder()->addCommentToStatusHistory($paymentData['comment'])->save();
            }
        }
        return true;
    }

    /**
     * @param OrderPayment $payment
     * @param ResponseGetWebPaymentDetails|null $response
     * @return OrderPayment
     */
    protected function flagPaymentAsInError(OrderPayment $payment, $message = null)
    {
        $message = null;
        $response = null;
        if(!$message) {
            $response = $payment->getData('payline_response');
            $message = $response->getResultCode() . ' : ' . $response->getShortErrorMessage();
        }

        $payment->setData('payline_in_error', true);
        $payment->setData('payline_error_message', $message);
        $payment->setData('payline_user_message', $response ? $this->helperData->getUserMessageForCode($response) : $message);

        return $payment;
    }


}
