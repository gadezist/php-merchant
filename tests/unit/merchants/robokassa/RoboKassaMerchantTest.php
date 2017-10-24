<?php

namespace hiqdev\php\merchant\tests\unit\merchants\robokassa;

use hiqdev\php\merchant\merchants\okpay\OkpayMerchant;
use hiqdev\php\merchant\merchants\robokassa\RoboKassaMerchant;
use hiqdev\php\merchant\response\RedirectPurchaseResponse;
use hiqdev\php\merchant\tests\unit\merchants\AbstractMerchantTest;
use Money\Currency;
use Money\Money;
use Omnipay\OKPAY\Gateway;
use Omnipay\OKPAY\Message\CompletePurchaseRequest;

class RoboKassaMerchantTest extends AbstractMerchantTest
{
    /** @var RoboKassaMerchant */
    protected $merchant;

    protected function buildMerchant()
    {
        return new RoboKassaMerchant(
            $this->getCredentials(),
            $this->getGatewayFactory(),
            $this->getMoneyFormatter(),
            $this->getMoneyParser()
        );
    }

    public function testCredentialsWereMappedCorrectly()
    {
        $gatewayPropertyReflection = (new \ReflectionObject($this->merchant))->getProperty('gateway');
        $gatewayPropertyReflection->setAccessible(true);
        $gateway = $gatewayPropertyReflection->getValue($this->merchant);

        $this->assertSame($this->getCredentials()->getPurse(), $gateway->getPurse());
        $this->assertSame($this->getCredentials()->getKey1(), $gateway->getSecretKey());
        $this->assertSame($this->getCredentials()->getKey2(), $gateway->getSecretKey2());
    }

    public function testRequestPurchase()
    {
        $invoice = $this->buildInvoice();

        $purchaseResponse = $this->merchant->requestPurchase($invoice);
        $this->assertInstanceOf(RedirectPurchaseResponse::class, $purchaseResponse);
        $this->assertSame('https://merchant.roboxchange.com/Index.aspx', $purchaseResponse->getRedirectUrl());

        $this->assertArraySubset([
            'MrchLogin' => $this->getCredentials()->getPurse(),
            'OutSum' => $this->getMoneyFormatter()->format($invoice->getAmount()),
            'Desc' => $invoice->getDescription(),
            'IncCurrLabel' => $invoice->getCurrency()->getCode(),
            'Shp_Client' => $invoice->getClient(),
            'Shp_Currency' => $invoice->getCurrency()->getCode(),
            'Shp_TransactionId' => $invoice->getId()
        ], $purchaseResponse->getRedirectData());
    }

    public function testCompletePurchase()
    {
        $_POST = [
            'out_summ' => '139.530000',
            'OutSum' => '139.530000',
            'inv_id' => '1010566870',
            'InvId' => '1010566870',
            'crc' => '318F51FE557110F39C755CF36B94BBE2',
            'SignatureValue' => '318F51FE557110F39C755CF36B94BBE2',
            'PaymentMethod' => 'BankCard',
            'IncSum' => '139.530000',
            'IncCurrLabel' => 'QCardR',
            'Shp_Client' => 'silverfire',
            'Shp_TransactionId' => '123',
            'Shp_Currency' => 'RUB',
        ];

        $this->merchant = $this->buildMerchant();

        $completePurchaseResponse = $this->merchant->completePurchase([]);

        $this->assertInstanceOf(\hiqdev\php\merchant\response\CompletePurchaseResponse::class, $completePurchaseResponse);
        $this->assertTrue($completePurchaseResponse->getIsSuccessful());
        $this->assertSame('123', $completePurchaseResponse->getTransactionId());
        $this->assertSame('', $completePurchaseResponse->getTransactionReference());
        $this->assertTrue((new Money(13953, new Currency('RUB')))->equals($completePurchaseResponse->getAmount()));
        $this->assertTrue((new Money(0, new Currency('RUB')))->equals($completePurchaseResponse->getFee()));
        $this->assertSame('RUB', $completePurchaseResponse->getCurrency()->getCode());
        $this->assertInstanceOf(\DateTime::class, $completePurchaseResponse->getTime());
    }
}