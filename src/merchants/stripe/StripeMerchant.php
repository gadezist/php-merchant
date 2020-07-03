<?php
/**
 * Generalization over Omnipay and Payum
 *
 * @link      https://github.com/hiqdev/php-merchant
 * @package   php-merchant
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2015-2018, HiQDev (http://hiqdev.com/)
 */

namespace hiqdev\php\merchant\merchants\stripe;

use DateTime;
use DateTimeImmutable;
use Exception;
use hiqdev\php\merchant\card\CardInformation;
use hiqdev\php\merchant\exceptions\MerchantException;
use hiqdev\php\merchant\InvoiceInterface;
use hiqdev\php\merchant\merchants\AbstractMerchant;
use hiqdev\php\merchant\merchants\HostedPaymentPageMerchantInterface;
use hiqdev\php\merchant\merchants\PaymentCardMerchantInterface;
use hiqdev\php\merchant\merchants\RemoteCustomerAwareMerchant;
use hiqdev\php\merchant\response\CompletePurchaseResponse;
use hiqdev\php\merchant\response\RedirectPurchaseResponse;
use Money\Money;
use Omnipay\Common\Exception\RuntimeException;
use Omnipay\Stripe\PaymentIntentsGateway;

/**
 * Class StripeMerchant
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 */
class StripeMerchant extends AbstractMerchant implements HostedPaymentPageMerchantInterface, PaymentCardMerchantInterface, RemoteCustomerAwareMerchant
{
    /**
     * @var PaymentIntentsGateway
     */
    protected $gateway;

    protected function createGateway()
    {
        return $this->gatewayFactory->build('Stripe\PaymentIntents', [
            'apiKey' => $this->credentials->getKey1(),
        ]);
    }

    public function requestPurchase(InvoiceInterface $invoice)
    {
        $clientSecret = $this->fetchClientSecret($invoice->getClient()->remoteId());

        $response = new RedirectPurchaseResponse('', [
            'clientSecret' => $clientSecret,
            'publicKey' => $this->credentials->getKey2()
        ]);
        $response->setMethod('GET');

        return $response;
    }

    public function chargeCard(InvoiceInterface $invoice)
    {
        try {
            /** @var \Omnipay\Stripe\Message\Response $response */
            $response = $this->gateway->purchase([
                'amount' => $this->moneyFormatter->format($invoice->getAmount()),
                'currency' => $invoice->getCurrency()->getCode(),
                'description' => $invoice->getDescription(),
                'customerReference' => $invoice->getClient()->remoteId(),
                'paymentMethod' => $invoice->getPreferredPaymentMethod(),
                'returnUrl' => $invoice->getReturnUrl(),
                'confirm' => true,
            ])->send();
        } catch (Exception $exception) {
            throw new MerchantException('Failed to charge a card', $exception->getCode(), $exception);
        }

        if ($response->isRedirect()) {
            return new RedirectPurchaseResponse($response->getRedirectUrl(), $response->getRedirectData());
        }

        if ($response->isSuccessful()) {
            return (new CompletePurchaseResponse())
                ->setIsSuccessful(true)
                ->setAmount($invoice->getAmount())
                ->setFee(new Money(0, $invoice->getAmount()->getCurrency()))
                ->setTransactionReference($response->getTransactionReference())
                ->setTransactionId($response->getTransactionId())
                ->setPayer($response->getCustomerReference())
                ->setTime(new DateTime());
        }

        if (isset($response->getData()['error']['message'])) {
            throw new MerchantException($response->getData()['error']['message']);
        }

        throw new MerchantException('Failed to charge card');
    }

    public function completePurchase($data)
    {
        throw new Exception('Not implemented');
    }

    private function fetchClientSecret(string $customerReference): string
    {
        $response = $this->gateway->createSetupIntent(compact('customerReference'))->send();
        if ($response->isSuccessful()) {
            return $response->getData()['client_secret'];
        }

        throw new RuntimeException($response->getMessage());
    }

    public function createCustomer(string $email): string
    {
        $response = $this->gateway->createCustomer(compact('email'))->send();
        if (!$response->isSuccessful()) {
            throw new RuntimeException($response->getMessage());
        }

        return $response->getCustomerReference();
    }

    public function fetchCardInformation(string $clientId, string $token): CardInformation
    {
        $response = $this->gateway->fetchCard([
            'paymentMethod' => $token
        ])->send();

        $card = $response->getData()['card'];
        $result = new CardInformation();
        $result->brand = $card['brand'] ?? null;
        $result->last4 = $card['last4'];
        $result->expirationTime = DateTimeImmutable::createFromFormat('m/Y', "{$card['exp_month']}/{$card['exp_year']}");

        return $result;
    }
}
