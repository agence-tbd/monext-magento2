<?php

namespace Monext\Payline\Model\Method;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Monext\Payline\Model\ContractManagement;
use Monext\Payline\PaylineApi\Constants as PaylineApiConstants;

abstract class AbstractMethodConfigProvider implements ConfigProviderInterface
{
    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    /**
     * @var MethodInterface
     */
    protected $method;

    /**
     * @var AssetRepository;
     */
    protected $assetRepository;

    /**
     * @var ContractManagement
     */
    protected $contractManagement;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    public function __construct(
        PaymentHelper $paymentHelper,
        AssetRepository $assetRepository,
        ContractManagement $contractManagement,
        UrlInterface $urlBuilder,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->paymentHelper = $paymentHelper;
        $this->assetRepository = $assetRepository;
        $this->contractManagement = $contractManagement;
        $this->urlBuilder = $urlBuilder;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return array
     */
    abstract public function getConfig();

    /**
     * @param $fieldName
     * @return mixed
     * @throws \ReflectionException
     */
    protected function getMethodConfigData($fieldName)
    {
        if(!isset($this->method)) {
            throw new \ReflectionException('Property method not init');
        }
        return $this->method->getConfigData($fieldName);
    }

    /**
     * @return string[]
     */
    public function getCardTypeImageFileNames()
    {
        return [
            PaylineApiConstants::PAYMENT_CONTRACT_CARD_TYPE_CB => 'cb.jpg',
            PaylineApiConstants::PAYMENT_CONTRACT_CARD_TYPE_CB_3DS => 'cb.jpg',
            PaylineApiConstants::PAYMENT_CONTRACT_CARD_TYPE_PAYPAL => 'paypal.png',
            PaylineApiConstants::PAYMENT_CONTRACT_CARD_TYPE_AMEX => 'amex.png',
            PaylineApiConstants::PAYMENT_CONTRACT_CARD_TYPE_ONEY => 'oney.png',
            PaylineApiConstants::PAYMENT_CONTRACT_CARD_TYPE_3XONEY => 'oney.png',
            PaylineApiConstants::PAYMENT_CONTRACT_CARD_TYPE_4XONEY => 'oney.png',
            PaylineApiConstants::PAYMENT_CONTRACT_CARD_TYPE_3XONEY_SF => 'oney.png',
            PaylineApiConstants::PAYMENT_CONTRACT_CARD_TYPE_4XONEY_SF => 'oney.png',
            PaylineApiConstants::PAYMENT_CONTRACT_CARD_TYPE_CASINO3X => 'floa3x.png',
            PaylineApiConstants::PAYMENT_CONTRACT_CARD_TYPE_CASINO4X => 'floa4x.png',
            PaylineApiConstants::PAYMENT_CONTRACT_CARD_TYPE_PAYCONIQ => 'payconiq-h40.png',
            PaylineApiConstants::PAYMENT_CONTRACT_CARD_TYPE_MULTIBANCO => 'multibanco.png',
            PaylineApiConstants::PAYMENT_CONTRACT_CARD_TYPE_MBWAY => 'mbway.png',
            PaylineApiConstants::PAYMENT_CONTRACT_CARD_TYPE_BANCONTACT => 'bancontact.png',
            PaylineApiConstants::PAYMENT_CONTRACT_CARD_TYPE_KLARNA_PAY => 'klarna.png',
            PaylineApiConstants::PAYMENT_CONTRACT_CARD_TYPE_KLARNA => 'klarna.png',
            PaylineApiConstants::PAYMENT_CONTRACT_CARD_TYPE_IDEAL => 'ideal.png',
            PaylineApiConstants::PAYMENT_CONTRACT_CARD_TYPE_CADHOC => 'cadhoc.png',
            PaylineApiConstants::PAYMENT_CONTRACT_CARD_TYPE_COFIDIS => 'cofidispay.png',
            PaylineApiConstants::PAYMENT_CONTRACT_CARD_TYPE_APPLEPAY => 'apple_pay.png',
        ];
    }

    public function getCardTypeLogoUrl($cardType)
    {
        try {
            $fileNames = $this->getCardTypeImageFileNames();
            $cardType=str_ireplace('_MNXT', '', $cardType);
            if (!isset($fileNames[$cardType])) {
                throw new LocalizedException(__('Payline card type logo url does not exists.'));
            }

            return $this->assetRepository->getUrlWithParams('Monext_Payline::images/'.$fileNames[$cardType], ['_secure' => true]);
        } catch (\Exception $e) {
            return $this->assetRepository->getUrlWithParams('Monext_Payline::images/default.png', ['_secure' => true]);
        }
    }
}
