<?php

namespace Clockwork\Clients;

use Clockwork\Exceptions\InvalidArgumentException as InvalidArgumentException;
use Clockwork\Messages\SMS as SMSMessage;

class SMSClient {

    /**
     * All Clockwork API calls start with BASE_URL
     */
    const API_BASE_URL      = 'api.clockworksms.com/xml/';

    /**
     * string to append to API_BASE_URL to check authentication
     */
    const API_AUTH_METHOD   = 'authenticate';

    /**
     * string to append to API_BASE_URL for sending SMS
     */
    const API_SMS_METHOD    = 'sms';

    /**
     * string to append to API_BASE_URL for checking message credit
     */
    const API_CREDIT_METHOD = 'credit';

    /**
     * string to append to API_BASE_URL for checking account balance
     */
    const API_BALANCE_METHOD = 'balance';

    /**
     * Clockwork API Key
     * @var string
     */
    public $key;

    /**
     * Use SSL when making HTTP requests
     * If this is not set, SSL will be used where PHP supports it
     * @var bool
     */
    public $ssl;

    /**
     * Proxy server hostname (Optional)
     * @var string
     */
    public $proxy_host;

    /**
     * Proxy server port (Optional)
     * @var integer
     */
    public $proxy_port;

    /**
     * Send some text messages
     * @param array $sms
     * @return array
     * @throws InvalidArgumentException
     * @throws ClockworkException
     */
    public function send(array $smsMessages) {

        $req_doc = new DOMDocument('1.0', 'UTF-8');
        $root = $req_doc->createElement('Message');
        $req_doc->appendChild($root);

        $user_node = $req_doc->createElement('Key');
        $user_node->appendChild($req_doc->createTextNode($this->key));
        $root->appendChild($user_node);

        foreach ($smsMessages as $smsMessage) {

            if (!$smsMessage instanceof SMSMessage) {
                throw new InvalidArgumentException("Array smsMessage contains wrong data");
            }

            $sms_node = $req_doc->createElement('SMS');
            // Phone number
            $sms_node->appendChild($req_doc->createElement('To', $smsMessage->getToPhoneNumber()));
            // Message text
            $content_node = $req_doc->createElement('Content');
            $content_node->appendChild($req_doc->createTextNode($smsMessage->getMessage()));
            $sms_node->appendChild($content_node);


            if ($smsMessage->getHandler()->getFrom() !== "") {
                $from_node = $req_doc->createElement('From');
                $from_node->appendChild($req_doc->createTextNode($smsMessage->getHandler()->getFrom()));
                $sms_node->appendChild($from_node);
            }

            if ($smsMessage->getHandler()->getClientId() !== "") {
                $client_id_node = $req_doc->createElement('ClientID');
                $client_id_node->appendChild($req_doc->createTextNode($smsMessage->getHandler()->getClientId()));
                $sms_node->appendChild($client_id_node);
            }

            $long = $smsMessage->getHandler()->getLong();
            $long_node = $req_doc->createElement('Long');
            $long_node->appendChild($req_doc->createTextNode($long ? 1 : 0));
            $sms_node->appendChild($long_node);

            $truncate = $smsMessage->getHandler()->getTruncate();
            $trunc_node = $req_doc->createElement('Truncate');
            $trunc_node->appendChild($req_doc->createTextNode($truncate ? 1 : 0));
            $sms_node->appendChild($trunc_node);

            switch (strtolower($smsMessage->getHandler()->getInvalidCharAction())) {
                case 'error':
                    $sms_node->appendChild($req_doc->createElement('InvalidCharAction', 1));
                    break;
                case 'remove':
                    $sms_node->appendChild($req_doc->createElement('InvalidCharAction', 2));
                    break;
                case 'replace':
                    $sms_node->appendChild($req_doc->createElement('InvalidCharAction', 3));
                    break;
                default:
                    break;
            }

            $sms_node->appendChild($req_doc->createElement('WrapperID', key($smsMessages)));

            $root->appendChild($sms_node);
        }

        $req_xml = $req_doc->saveXML();

        $response = $this->postToClockwork(self::API_SMS_METHOD, $req_xml);
        $this->processResponse($response);

        return $response;
    }

    protected function processResponse($resp_xml)
    {
        $resp_doc = new DOMDocument();
        $resp_doc->loadXML($resp_xml);

        $response = array();
        $err_no   = null;
        $err_desc = null;

        foreach($resp_doc->documentElement->childNodes AS $doc_child) {
            switch(strtolower($doc_child->nodeName)) {
                case 'sms_resp':
                    $resp = array();
                    $wrapper_id = null;
                    foreach($doc_child->childNodes AS $resp_node) {
                        switch(strtolower($resp_node->nodeName)) {
                            case 'messageid':
                                $resp['id'] = $resp_node->nodeValue;
                                break;
                            case 'errno':
                                $resp['error_code'] = $resp_node->nodeValue;
                                break;
                            case 'errdesc':
                                $resp['error_message'] = $resp_node->nodeValue;
                                break;
                            case 'wrapperid':
                                $wrapper_id = $resp_node->nodeValue;
                                break;
                        }
                    }
                    if( array_key_exists('error_code', $resp ) )
                    {
                        $resp['success'] = 0;
                    } else {
                        $resp['success'] = 1;
                    }
                    $resp['sms'] = $sms[$wrapper_id];
                    array_push($response, $resp);
                    break;
                case 'errno':
                    $err_no = $doc_child->nodeValue;
                    break;
                case 'errdesc':
                    $err_desc = $doc_child->nodeValue;
                    break;
            }
        }

        if (isset($err_no)) {
            throw new ClockworkException($err_desc, $err_no);
        }
    }

    /**
     * Check how many SMS credits you have available
     *
     * @return  integer   SMS credits remaining
     * @deprecated Use checkBalance() instead
     * @author  Martin Steel
     */
    public function checkCredit() {
        // Create XML doc for request
        $req_doc = new DOMDocument('1.0', 'UTF-8');
        $root = $req_doc->createElement('Credit');
        $req_doc->appendChild($root);
        $root->appendChild($req_doc->createElement('Key', $this->key));
        $req_xml = $req_doc->saveXML();

        // POST XML to Clockwork
        $resp_xml = $this->postToClockwork(self::API_CREDIT_METHOD, $req_xml);

        // Create XML doc for response
        $resp_doc = new DOMDocument();
        $resp_doc->loadXML($resp_xml);

        // Parse the response to find credit value
        $credit;
        $err_no = null;
        $err_desc = null;

        foreach ($resp_doc->documentElement->childNodes AS $doc_child) {
            switch ($doc_child->nodeName) {
                case "Credit":
                    $credit = $doc_child->nodeValue;
                    break;
                case "ErrNo":
                    $err_no = $doc_child->nodeValue;
                    break;
                case "ErrDesc":
                    $err_desc = $doc_child->nodeValue;
                    break;
                default:
                    break;
            }
        }

        if (isset($err_no)) {
            throw new ClockworkException($err_desc, $err_no);
        }
        return $credit;
    }

    /**
     * Check your account balance
     *
     * @return  array   Array of account balance:
     * @author  Martin Steel
     */
    public function checkBalance() {
        // Create XML doc for request
        $req_doc = new DOMDocument('1.0', 'UTF-8');
        $root = $req_doc->createElement('Balance');
        $req_doc->appendChild($root);
        $root->appendChild($req_doc->createElement('Key', $this->key));
        $req_xml = $req_doc->saveXML();

        // POST XML to Clockwork
        $resp_xml = $this->postToClockwork(self::API_BALANCE_METHOD, $req_xml);

        // Create XML doc for response
        $resp_doc = new DOMDocument();
        $resp_doc->loadXML($resp_xml);

        // Parse the response to find balance value
        $balance = null;
        $err_no = null;
        $err_desc = null;

        foreach ($resp_doc->documentElement->childNodes as $doc_child) {
            switch ($doc_child->nodeName) {
                case "Balance":
                    $balance = number_format(floatval($doc_child->nodeValue), 2);
                    break;
                case "Currency":
                    foreach ($doc_child->childNodes as $resp_node) {
                        switch ($resp_node->tagName) {
                            case "Symbol":
                                $symbol = $resp_node->nodeValue;
                                break;
                            case "Code":
                                $code = $resp_node->nodeValue;
                                break;
                        }
                    }
                    break;
                case "ErrNo":
                    $err_no = $doc_child->nodeValue;
                    break;
                case "ErrDesc":
                    $err_desc = $doc_child->nodeValue;
                    break;
                default:
                    break;
            }
        }

        if (isset($err_no)) {
            throw new ClockworkException($err_desc, $err_no);
        }

        return array( 'symbol' => $symbol, 'balance' => $balance, 'code' => $code );
    }

    /**
     * Check whether the API Key is valid
     *
     * @return  bool    True indicates a valid key
     * @author  Martin Steel
     */
    public function checkKey() {
        // Create XML doc for request
        $req_doc = new DOMDocument('1.0', 'UTF-8');
        $root = $req_doc->createElement('Authenticate');
        $req_doc->appendChild($root);
        $root->appendChild($req_doc->createElement('Key', $this->key));
        $req_xml = $req_doc->saveXML();

        // POST XML to Clockwork
        $resp_xml = $this->postToClockwork(self::API_AUTH_METHOD, $req_xml);

        // Create XML doc for response
        $resp_doc = new DOMDocument();
        $resp_doc->loadXML($resp_xml);

        // Parse the response to see if authenticated
        $cust_id;
        $err_no = null;
        $err_desc = null;

        foreach ($resp_doc->documentElement->childNodes AS $doc_child) {
            switch ($doc_child->nodeName) {
                case "CustID":
                    $cust_id = $doc_child->nodeValue;
                    break;
                case "ErrNo":
                    $err_no = $doc_child->nodeValue;
                    break;
                case "ErrDesc":
                    $err_desc = $doc_child->nodeValue;
                    break;
                default:
                    break;
            }
        }

        if (isset($err_no)) {
            throw new ClockworkException($err_desc, $err_no);
        }
        return isset($cust_id);
    }

    /**
     * Make an HTTP POST to Clockwork
     *
     * @param   string   method Clockwork method to call (sms/credit)
     * @param   string   data   Content of HTTP POST
     *
     * @return  string          Response from Clockwork
     * @author  Martin Steel
     */
    protected function postToClockwork($method, $data) {
        if ($this->log) {
            $this->logXML("API $method Request XML", $data);
        }

        if( isset( $this->ssl ) ) {
            $ssl = $this->ssl;
        } else {
            $ssl = $this->sslSupport();
        }

        $url = $ssl ? 'https://' : 'http://';
        $url .= self::API_BASE_URL . $method;

        $response = $this->xmlPost($url, $data);

        if ($this->log) {
            $this->logXML("API $method Response XML", $response);
        }

        return $response;
    }

    /**
     * Make a HTTP POST
     *
     * cURL will be used if available, otherwise tries the PHP stream functions
     *
     * @param   string url      URL to send to
     * @param   string data     Data to POST
     * @return  string          Response returned by server
     * @author  Martin Steel
     */
    protected function xmlPost($url, $data) {
        if(extension_loaded('curl')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type: text/xml"));
            curl_setopt($ch, CURLOPT_USERAGENT, 'Clockwork PHP Wrapper/1.0' . self::VERSION);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            if (isset($this->proxy_host) && isset($this->proxy_port)) {
                curl_setopt($ch, CURLOPT_PROXY, $this->proxy_host);
                curl_setopt($ch, CURLOPT_PROXYPORT, $this->proxy_port);
            }

            $response = curl_exec($ch);
            $info = curl_getinfo($ch);

            if ($response === false || $info['http_code'] != 200) {
                throw new Exception('HTTP Error calling Clockwork API - HTTP Status: ' . $info['http_code'] . ' - cURL Erorr: ' . curl_error($ch));
            } elseif (curl_errno($ch) > 0) {
                throw new Exception('HTTP Error calling Clockwork API - cURL Error: ' . curl_error($ch));
            }

            curl_close($ch);

            return $response;
        } elseif (function_exists('stream_get_contents')) {
            // Enable error Track Errors
            $track = ini_get('track_errors');
            ini_set('track_errors',true);

            $params = array('http' => array(
                'method'  => 'POST',
                'header'  => "Content-Type: text/xml\r\nUser-Agent: mediaburst PHP Wrapper/" . self::VERSION . "\r\n",
                'content' => $data
            ));

            if (isset($this->proxy_host) && isset($this->proxy_port)) {
                $params['http']['proxy'] = 'tcp://'.$this->proxy_host . ':' . $this->proxy_port;
                $params['http']['request_fulluri'] = True;
            }

            $ctx = stream_context_create($params);
            $fp = @fopen($url, 'rb', false, $ctx);
            if (!$fp) {
                ini_set('track_errors',$track);
                throw new Exception("HTTP Error calling Clockwork API - fopen Error: $php_errormsg");
            }
            $response = @stream_get_contents($fp);
            if ($response === false) {
                ini_set('track_errors',$track);
                throw new Exception("HTTP Error calling Clockwork API - stream Error: $php_errormsg");
            }
            ini_set('track_errors',$track);
            return $response;
        } else {
            throw new Exception("Clockwork requires PHP5 with cURL or HTTP stream support");
        }
    }

    /**
     * Does the server/HTTP wrapper support SSL
     *
     * This is a best guess effort, some servers have weird setups where even
     * though cURL is compiled with SSL support is still fails to make
     * any requests.
     *
     * @return bool     True if SSL is supported
     * @author  Martin Steel
     */
    protected function sslSupport() {
        $ssl = false;
        // See if PHP is compiled with cURL
        if (extension_loaded('curl')) {
            $version = curl_version();
            $ssl = ($version['features'] & CURL_VERSION_SSL) ? true : false;
        } elseif (extension_loaded('openssl')) {
            $ssl = true;
        }
        return $ssl;
    }

    /**
     * Log some XML, tidily if possible, in the PHP error log
     *
     * @param   string  log_msg The log message to prepend to the XML
     * @param   string  xml     An XML formatted string
     *
     * @return  void
     * @author  Martin Steel
     */
    protected function logXML($log_msg, $xml) {
        // Tidy if possible
        if (class_exists('tidy')) {
            $tidy = new tidy;
            $config = array(
                'indent'     => true,
                'input-xml'  => true,
                'output-xml' => true,
                'wrap'       => 200
            );
            $tidy->parseString($xml, $config, 'utf8');
            $tidy->cleanRepair();
            $xml = $tidy;
        }
        // Output
        error_log("Clockwork $log_msg: $xml");
    }

    /**
     * Check if an array is associative
     *
     * @param   array $array Array to check
     * @return  bool
     * @author  Martin Steel
     */
    protected function is_assoc($array) {
        return (bool)count(array_filter(array_keys($array), 'is_string'));
    }

    /**
     * Check if a number is a valid MSISDN
     *
     * @param string $val Value to check
     * @return bool True if valid MSISDN
     * @author James Inman
     * @since 1.3.0
     * @todo Take an optional country code and check that the number starts with it
     */
    public static function is_valid_msisdn($val) {
        return preg_match( '/^[1-9][0-9]{7,12}$/', $val );
    }
} 