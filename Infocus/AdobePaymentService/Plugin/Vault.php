<?php

namespace Infocus\AdobePaymentService\Plugin;
class Vault
{
    public function afterCanCapturePartial(
        \Magento\Vault\Model\Method\Vault $subject,
        $result
    ) {
        return true;
    }
}
