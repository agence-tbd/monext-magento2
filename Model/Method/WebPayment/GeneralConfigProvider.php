<?php

namespace Monext\Payline\Model\Method\WebPayment;

use Monext\Payline\Helper\Constants as HelperConstants;
use Monext\Payline\Model\Method\AbstractMethodConfigProvider;

class GeneralConfigProvider extends AbstractMethodConfigProvider
{
    public function getConfig()
    {
        $config = array();
        $config['payline']['general']['environment'] = $this->scopeConfig->getValue(HelperConstants::CONFIG_PATH_PAYLINE_GENERAL_ENVIRONMENT,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $config['payline']['general']['contracts'] = [];

        $contractCollection = $this->contractManagement->getUsedContracts();

        foreach ($contractCollection as $contract) {
            $config['payline']['general']['contracts'][] = [
                'id' => $contract->getId(),
                'number' => $contract->getNumber(),
                'cardType' => $contract->getCardType(),
                'logo' => $this->getCardTypeLogoUrl($contract->getCardType()),
                'label' => $contract->getLabel(),
            ];
        }
        return $config;
    }
}
