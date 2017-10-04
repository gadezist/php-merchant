<?php

namespace hiqdev\php\merchant\merchants\generic;

use hiqdev\php\merchant\credentials\CredentialsInterface;
use hiqdev\php\merchant\factories\GatewayFactoryInterface;
use hiqdev\php\merchant\InvoiceInterface;
use hiqdev\php\merchant\merchants\MerchantInterface;
use hiqdev\php\merchant\response\CompletePurchaseResponse;
use hiqdev\php\merchant\response\RedirectPurchaseResponse;
use Money\Currency;

final class GenericMerchant implements MerchantInterface
{
    /**
     * @var \Omnipay\Common\GatewayInterface
     */
    protected $gateway;
    /**
     * @var CredentialsInterface
     */
    private $credentials;
    /**
     * @var string
     */
    private $gatewayName;
    /**
     * @var GatewayFactoryInterface
     */
    private $gatewayFactory;

    public function __construct($gatewayName, CredentialsInterface $credentials, GatewayFactoryInterface $gatewayFactory)
    {
        $this->credentials = $credentials;
        $this->gatewayName = $gatewayName;
        $this->gatewayFactory = $gatewayFactory;
        $this->gateway = $this->gatewayFactory->build($gatewayName, [
            'purse' => $this->credentials->getPurse(),
            'secret'  => $this->credentials->getKey1(),
            'secret2' => $this->credentials->getKey2(),
        ]);
    }


    /**
     * @param InvoiceInterface $invoice
     * @return RedirectPurchaseResponse
     */
    public function requestPurchase(InvoiceInterface $invoice)
    {
        /**
         * @var \Omnipay\BitPay\Message\PurchaseResponse $response
         */
        $response = $this->gateway->purchase([
            'transactionReference' => $invoice->getId(),
            'description' => $invoice->getDescription(),
            'amount' => $invoice->getAmount(),
            'currency' => $invoice->getCurrency()->getCode(),
            'returnUrl' => $invoice->getReturnUrl(),
            'notifyUrl' => $invoice->getNotifyUrl(),
            'cancelUrl' => $invoice->getCancelUrl(),
        ])->send();

        return new RedirectPurchaseResponse($response->getRedirectUrl(), $response->getRedirectData());
    }

    /**
     * @param array $data
     * @return CompletePurchaseResponse
     */
    public function completePurchase($data)
    {
        /** @var \Omnipay\WebMoney\Message\CompletePurchaseResponse $response */
        $response = $this->gateway->completePurchase($data)->send();

        return (new CompletePurchaseResponse())
            ->setIsSuccessful($response->isSuccessful())
            ->setCurrency(new Currency($response->getCurrency()))
            ->setAmount($response->getAmount())
            ->setFee($response->getFee())
            ->setTransactionReference($response->getTransactionReference())
            ->setTransactionId($response->getTransactionId())
            ->setPayer($response->getPayer())
            ->setTime(new \DateTime($response->getTime()));
    }

    /**
     * @return CredentialsInterface
     */
    public function getCredentials(): CredentialsInterface
    {
        return $this->credentials;
    }
}