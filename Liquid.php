<?php
/**
 * BoxBilling
 *
 * LICENSE
 *
 * This source file is subject to the license that is bundled
 * with this package in the file LICENSE.txt
 * It is also available through the world-wide-web at this URL:
 * http://www.boxbilling.com/LICENSE.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@boxbilling.com so we can send you a copy immediately.
 *
 * @copyright Copyright (c) 2010-2012 BoxBilling (http://www.boxbilling.com)
 * @license   http://www.boxbilling.com/LICENSE.txt
 * @version   $Id$
 */
/**
 * HTTP API documentation http://liquid-docs.readthedocs.org/en/latest/restapi.html
 */
class Registrar_Adapter_Liquid extends Registrar_AdapterAbstract
{
    public $config = array(
        'userid'   => null,
        'password' => null,
        'api-key'  => null,
    );
    public function isKeyValueNotEmpty($array, $key)
    {
        $value = isset ($array[$key]) ? $array[$key] : '';
        if (strlen(trim($value)) == 0){
            return false;
        }
        return true;
    }
    public function __construct($options)
    {
        if (!extension_loaded('curl')) {
            throw new Registrar_Exception('CURL extension is not enabled');
        }
        if(isset($options['userid']) && !empty($options['userid'])) {
            $this->config['userid'] = $options['userid'];
            unset($options['userid']);
        } else {
            throw new Registrar_Exception('Domain registrar "Liquid" is not configured properly. Please update configuration parameter "Liquid Reseller ID" at "Configuration -> Domain registration".');
        }
        if(isset($options['api-key']) && !empty($options['api-key'])) {
            $this->config['api-key'] = $options['api-key'];
            unset($options['api-key']);
        } else {
            throw new Registrar_Exception('Domain registrar "Liquid" is not configured properly. Please update configuration parameter "Liquid API Key" at "Configuration -> Domain registration".');
        }
    }
    
    public static function getConfig()
    {
        return array(
            'label'     =>  'Manages domains on Liquid via API. Liquid requires your server IP in order to work. Login to the Liquid control panel (the url will be in the email you received when you signed up with them) and then go to Settings -> API and enter the IP address of the server where BoxBilling is installed to authorize it for API access.',
            'form'  => array(
                'userid' => array('text', array(
                            'label' => 'Reseller ID. You can get this at Liquid control panel Profile -> Reseller ID',
                            'description'=> 'Liquid Reseller ID'
                        ),
                     ),
                'api-key' => array('password', array(
                            'label' => 'Liquid API Key',
                            'description'=> 'You can get this at Liquid control panel, go to Settings -> API',
                            'required' => false,
                        ),
                     ),
            ),
        );
    }

    /**
     * Tells what TLDs can be registered via this adapter
     * @return string[]
     */
    public function getTlds()
    {
        return array(
            '.com', '.net', '.biz', '.org', '.info', '.name', '.co',
        );
    }
    
    public function isDomainCanBeTransfered(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Checking if domain can be transferred: ' . $domain->getName());
        return true;
    }
    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Checking domain availability: ' . $domain->getName());
        if($this->config['use_whois']) {
            $w = new Whois($domain->getName());
            return $w->isAvailable();
        }
        return true;
    }
    public function modifyNs(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Modifying nameservers: ' . $domain->getName());
        $this->getLog()->debug('Ns1: ' . $domain->getNs1());
        $this->getLog()->debug('Ns2: ' . $domain->getNs2());
        $this->getLog()->debug('Ns3: ' . $domain->getNs3());
        $this->getLog()->debug('Ns4: ' . $domain->getNs4());
        return true;
    }
    public function transferDomain(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Transfering domain: ' . $domain->getName());
        $this->getLog()->debug('Epp code: ' . $domain->getEpp());
        return true;
    }
    public function getDomainDetails(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Getting whois: ' . $domain->getName());
        if(!$domain->getRegistrationTime()) {
            $domain->setRegistrationTime(time());
        }
        if(!$domain->getExpirationTime()) {
            $years = $domain->getRegistrationPeriod();
            $domain->setExpirationTime(strtotime("+$years year"));
        }
        return $domain;
    }
    public function deleteDomain(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Removing domain: ' . $domain->getName());
        return true;
    }
    public function registerDomain(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Registering domain: ' . $domain->getName(). ' for '.$domain->getRegistrationPeriod(). ' years');
        return true;
    }
    public function renewDomain(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Renewing domain: ' . $domain->getName());
        return true;
    }
    public function modifyContact(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Updating contact info: ' . $domain->getName());
        return true;
    }
    
    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Enabling Privacy protection: ' . $domain->getName());
        return true;
    }
    
    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Disabling Privacy protection: ' . $domain->getName());
        return true;
    }
    public function getEpp(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Retrieving domain transfer code: ' . $domain->getName());
        return true;
    }
    public function lock(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Locking domain: ' . $domain->getName());
        return true;
    }
    public function unlock(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Unlocking: ' . $domain->getName());
        return true;
    }
    
    public function isTestEnv()
    {
        return $this->_testMode;
    }

    /**
     * Api URL
     * @return string
     */
    private function _getApiUrl()
    {
        if($this->isTestEnv()) {
            return 'https://api.domainsas.com/';
        }
        return 'https://api.liqu.id/';
    }

    /**
     * @param array $params
     * @return array
     */
    public function includeAuthorizationParams(array $params)
    {
        return array_merge($params, array(
            'reseller_id' => $this->config['userid'],
            'key' => $this->config['api-key'],
        ));
    }

    /**
     * Perform call to Api
     * @param string $url
     * @param array $params
     * @param string $method
     * @return string
     * @throws Registrar_Exception
     */
    protected function _makeRequest($url ,$params = array(), $method = 'GET', $type = 'json')
    {
        $params = $this->includeAuthorizationParams($params);
        $opts = array(
            CURLOPT_CONNECTTIMEOUT  => 30,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_TIMEOUT         => 60,
            CURLOPT_URL             => $this->_getApiUrl().$url.'.'.$type,
            CURLOPT_SSL_VERIFYHOST  =>  0,
            CURLOPT_SSL_VERIFYPEER  =>  0,
        );
        if($method == 'POST') {
            $opts[CURLOPT_POST]         = 1;
            $opts[CURLOPT_POSTFIELDS]   = $this->_formatParams($params);
            $this->getLog()->debug('API REQUEST: '.$opts[CURLOPT_URL].'?'.$opts[CURLOPT_POSTFIELDS]);
        } else {
            $opts[CURLOPT_URL]  = $opts[CURLOPT_URL].'?'.$this->_formatParams($params);
            $this->getLog()->debug('API REQUEST: '.$opts[CURLOPT_URL]);
        }
        $ch = curl_init();
        curl_setopt_array($ch, $opts);
        $result = curl_exec($ch);
        if ($result === false) {
            $e = new Registrar_Exception(sprintf('CurlException: "%s"', curl_error($ch)));
            $this->getLog()->err($e);
            curl_close($ch);
            throw $e;
        }
        curl_close($ch);
        $this->getLog()->info('API RESULT: '.$result);
        
        // response checker
        $json = json_decode($result, true);
        if(!is_array($json)) {
            return $result;
        }
        if(isset($json['status']) && $json['status'] == 'ERROR') {
            throw new Registrar_Exception($json['message'], 101);
        }
        if(isset($json['status']) && $json['status'] == 'error') {
            throw new Registrar_Exception($json['error'], 102);
        }
        
        if(isset($json['status']) && $json['status'] == 'Failed') {
            throw new Registrar_Exception($json['actionstatusdesc'], 103);
        }
        return $json;
    }

    /**
     * Convert params to Liquid format
     * @param array $params
     * @return string
     */
    private function _formatParams($params)
    {
        foreach($params as $key => &$param) {
            if(is_bool($param)) {
                $param = ($param) ? 'true' : 'false';
            }
        }
        $params = http_build_query($params, null, '&');
        $params = preg_replace('~%5B(\d+)%5D~', '', $params);
        return $params;
    }

    /**
     * Check if all required params are present, if not add default values
     * @param array $required_params - list of required params with default values
     * @param array $params - given params
     * @return array
     * @throws Registrar_Exception
     */
    private function _checkRequiredParams($required_params, $params)
    {
        foreach($required_params as $param => $value) {
            if(!isset($params[$param])) {
                $params[$param] = $value;
            }
        }
        return $params;
    }
}