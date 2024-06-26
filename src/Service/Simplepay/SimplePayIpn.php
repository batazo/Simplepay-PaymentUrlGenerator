<?php

namespace App\Service\Simplepay;

/**
  * IPN
  *
  * @category SDK
  * @package  SimplePayV2_SDK
  * @author   SimplePay IT Support <itsupport@otpmobil.com>
  * @license  http://www.gnu.org/licenses/gpl-3.0.html  GNU GENERAL PUBLIC LICENSE (GPL V3.0)
  * @link     http://simplepartner.hu/online_fizetesi_szolgaltatas.html
  */
class SimplePayIpn extends Base
{
    protected $currentInterface = 'ipn';
    public $returnData = [];
    protected $receiveDate = '';
    protected $ipnContent = [];
    protected $responseContent = '';
    protected $confirmContent = [];
    protected $ipnReturnData = [];
    protected $signature = '';
    public $validationResult = false;
    protected $isGAHExists = true;

    /**
     * IPN validation
     *
     * @param string $content IPN content
     *
     * @return boolean
     */
    public function isIpnSignatureCheck($content = '')
    {
        $signature = $this->getIpnSignature();

        foreach (json_decode($this->checkOrSetToJson($content)) as $key => $value) {
            $this->ipnContent[$key] = $value;
        }

        if (isset($this->ipnContent['merchant'])) {
            $this->addConfigData('merchantAccount', $this->ipnContent['merchant']);
        }
        $this->setConfig();

        $this->validationResult = false;
        if ($this->isCheckSignature($content, $signature)) {
            $this->validationResult = true;
        }
        $this->logContent['ipnBodyToValidation'] = $content;

        $this->logTransactionId = $this->ipnContent['transactionId'];
        $this->logOrderRef = $this->ipnContent['orderRef'];

        foreach ($this->ipnContent as $contentKey => $contentValue) {
            $this->logContent[$contentKey] = $contentValue;
        }
        $this->logContent['validationResult'] = $this->validationResult;

        if (!$this->validationResult) {
            $this->logContent['validationResultMessage'] = 'UNSUCCESSFUL VALIDATION, NO CONFIRMATION';
        }
        $this->writeLog($this->logContent);

        //confirm setup
        if (!$this->validationResult) {
            $this->confirmContent = 'UNSUCCESSFUL VALIDATION';
            $this->signature = 'UNSUCCESSFUL VALIDATION';
        } elseif ($this->validationResult) {
            $this->ipnContent['receiveDate'] = date("c", time());
            $this->confirmContent = json_encode($this->ipnContent);
            $this->signature = $this->getSignature($this->config['merchantKey'], $this->confirmContent);
        }
        $this->ipnReturnData['signature'] = $this->signature;
        $this->ipnReturnData['confirmContent'] = $this->confirmContent;
        $this->writeLog(['confirmSignature' => $this->signature, 'confirmContent' => $this->confirmContent]);

        return $this->validationResult;
    }

    /**
     * Signature for IPN
     *
     * @return string
     */
    protected function getIpnSignature() {
        $this->isGAHExists = function_exists('getallheaders');
        if ($this->isGAHExists) {
            $signature = $this->getSignatureFromHeader(getallheaders());
        } elseif (!$this->isGAHExists) {
            //Getallheaders fon Nginx
            $headers = [];
            foreach ($this->config['SERVER_DATAE'] as $name => $value) {
                if (substr($name, 0, 5) === 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
            $signature = $this->getSignatureFromHeader($headers);
        }
        return $signature;
    }

    /**
     * Immediate IPN confirmation
     *
     * @return boolean
     */
    public function runIpnConfirm()
    {
        try {
            header('Accept-language: EN');
            header('Content-type: application/json');
            header('Signature: ' . $this->ipnReturnData['signature']);
            print $this->ipnReturnData['confirmContent'];
        } catch (Exception $e) {
            $this->writeLog(['ipnConfirm' => $e->getMessage()]);
            return false;
        }
        $this->writeLog(['ipnConfirm' => 'Confirmed directly by runIpnConfirm']);
        return true;
    }

    /**
     * IPN confirmation data
     *
     * @return array $this->ipnReturnData Content and signature for mercaht system
     */
    public function getIpnConfirmContent()
    {
        $this->writeLog(['ipnConfirm' => 'ipnReturnData provided as content by getIpnConfirmContent']);
        return $this->ipnReturnData;
    }
}