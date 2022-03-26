<?php
/**
 * Generalization over Omnipay and Payum
 *
 * @link      https://github.com/hiqdev/php-merchant
 * @package   php-merchant
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2015-2018, HiQDev (http://hiqdev.com/)
 */

namespace hiqdev\php\merchant\merchants\robokassa;

use hiqdev\php\merchant\InvoiceInterface;
use hiqdev\php\merchant\merchants\AbstractMerchant;
use hiqdev\php\merchant\merchants\HostedPaymentPageMerchantInterface;
use hiqdev\php\merchant\response\CompletePurchaseResponse;
use hiqdev\php\merchant\response\RedirectPurchaseResponse;
use Money\Currency;
use Omnipay\RoboKassa\Gateway;

/**
 * Class RoboKassaMerchant.
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 */
class RoboKassaMerchant extends AbstractMerchant implements HostedPaymentPageMerchantInterface
{
    /**
     * @var Gateway
     */
    protected $gateway;

    protected function createGateway()
    {
        return $this->gatewayFactory->build('RoboKassa', [
            'purse' => $this->credentials->getPurse(),
            'secretKey' => $this->credentials->getKey1(),
            'secretKey2' => $this->credentials->getKey2(),
        ]);
    }

    /**
     * @param InvoiceInterface $invoice
     * @return RedirectPurchaseResponse
     */
    public function requestPurchase(InvoiceInterface $invoice)
    {
        /**
         * @var \Omnipay\RoboKassa\Message\PurchaseResponse
         */
        $response = $this->gateway->purchase([
            'amount' => $this->moneyFormatter->format($invoice->getAmount()),
            'transaction_id' => $invoice->getId(),
            'description' => $invoice->getDescription(),
            'currency' => $invoice->getCurrency()->getCode(),
            'client' => $invoice->getClient()->login(),
        ])->send();

        return new RedirectPurchaseResponse($response->getRedirectUrl(), $response->getRedirectData());
    }

    /**
     * @param array $data
     * @return CompletePurchaseResponse
     */
    public function completePurchase($data)
    {
        /** @var \Omnipay\RoboKassa\Message\CompletePurchaseResponse $response */
        $response = $this->gateway->completePurchase($data)->send();

        return (new CompletePurchaseResponse())
            ->setIsSuccessful($response->isSuccessful())
            ->setAmount($this->moneyParser->parse($response->getAmount(), new Currency($response->getCurrency())))
            ->setTransactionReference($response->getTransactionReference())
            ->setTransactionId($response->getTransactionId())
            ->setPayer($response->getPayer())
            ->setTime((new \DateTime())->setTimezone(new \DateTimeZone('UTC')));
    }
}
