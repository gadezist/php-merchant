<?php
/**
 * Generalization over Omnipay and Payum
 *
 * @link      https://github.com/hiqdev/php-merchant
 * @package   php-merchant
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2015-2017, HiQDev (http://hiqdev.com/)
 */

namespace hiqdev\php\merchant\yandex;

/**
 * Yandex Omnipay Merchant class.
 */
class OmnipayMerchant extends \hiqdev\php\merchant\OmnipayMerchant
{
    public $requestClass = OmnipayRequest::class;

    public function getWorker()
    {
        if ($this->_worker === null) {
            $this->_worker = (new \Omnipay\YandexMoney\P2pGateway())->initialize($this->prepareData($this->data));
        }

        return $this->_worker;
    }
}