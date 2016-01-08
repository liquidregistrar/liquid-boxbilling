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
 * HTTP API documentation http://cp.onlyfordemo.net/kb/answer/744
 */
class Registrar_Adapter_Liquid extends Registrar_AdapterAbstract
{
    /**
     * config
     * @return boolean
     */
    public $config = array(
        'userid'   => null,
        'password' => null,
        'api-key' => null,
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
            'label'     =>  'Manages domains on Liquid via API. Liquid requires your server IP in order to work. Login to the Liquid control panel (the url will be in the email you received when you signed up with them) and then go to Settings > API and enter the IP address of the server where BoxBilling is installed to authorize it for API access.',
            'form'  => array(
                'userid' => array('text', array(
                            'label' => 'Reseller ID. You can get this at Liquid control panel Settings > Personal information > Primary profile > Reseller ID',
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
            '.asia', '.tv', '.in', '.us',
            // '.ru', '.com.ru', '.net.ru', '.org.ru',
            // '.de', '.es', '.xxx', '.ca', '.au', '.com.au',
            // '.net.au', '.co.uk', '.org.uk', '.me.uk',
            // '.eu', '.co.in', '.net.in', '.org.in',
            // '.gen.in', '.firm.in', '.ind.in', '.cn.com',
            // '.com.co', '.net.co', '.nom.co', '.me', '.mobi',
            // '.tel', '.cc', '.ws', '.bz', '.mn', '.co.nz',
            // '.net.nz', '.org.nz', '.eu.com', '.gb.com', '.ae.org',
            // '.kr.com', '.us.com', '.qc.com', '.gr.com',
            // '.de.com', '.gb.net', '.no.com', '.hu.com',
            // '.jpn.com', '.uy.com', '.za.com', '.br.com',
            // '.sa.com', '.se.com', '.se.net', '.uk.com',
            // '.uk.net', '.ru.com', '.com.cn', '.net.cn',
            // '.org.cn', '.nl', '.com.co', '.pw',
        );
    }

    /**
     * Cek availability domain
     * @return boolean
     */
    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $result = $this->_makeRequest('domains/availability?domain='.$domain->getName(), array(), 'get');

        foreach ($result as $val) {
            $check = $val[$domain->getName()];
            if($check && $check['status'] == 'available') {
                return true;
            }
        }

        return false;
    }

    /**
     * Cek transfer domain
     * @return boolean
     */
    public function isDomainCanBeTransfered(Registrar_Domain $domain)
    {
        $params = array(
            'domain_name'       =>  $domain->getName(),
        );
        $result = $this->_makeRequest('domains/transfer/validity', $params, 'post');
        return ($result == true);
    }

    /**
     * Ubah NS domain
     * @return boolean
     */
    public function modifyNs(Registrar_Domain $domain)
    {
        $ns = array();
        $ns[] = $domain->getNs1();
        $ns[] = $domain->getNs2();
        if($domain->getNs3())  {
            $ns[] = $domain->getNs3();
        }
        if($domain->getNs4())  {
            $ns[] = $domain->getNs4();
        }

        $domain_id = $this->_getDomainOrderId($domain);

        $params['ns'] = implode(',', $ns);
        $result = $this->_makeRequest('domains/' . $domain_id . '/ns', $params, 'put');

        if (is_array($result)) {
            $result['status'] = 'Success';
        }

        return ($result['status'] == 'Success');
    }

    /**
     * Ubah kontak domain
     * @return boolean
     */
    public function modifyContact(Registrar_Domain $domain)
    {
        $cust = $domain->getContactRegistrar();
        $cust_email = $cust->getEmail();

        // mendapatkan customer di LQ
        $customer = $this->_getCustomerDetails($domain, $cust_email);

        if (is_array($customer)) {
            foreach ($customer as $cus) {
                $cus['email'] = trim(strtolower($cus['email']));
                if ($cus['email'] == $cust_email) {
                    $customer_id = $cus['customer_id'];
                }
            }
        }

        $cdetails = $this->_getDefaultContactDetails($customer_id);
        $contact_id = $cdetails['registrant_contact']['contact_id'];
        $c = $domain->getContactRegistrar();
        
        $required_params = array(
            'name'              =>  $c->getName(),
            'company'           =>  $c->getCompany(),
            'email'             =>  $c->getEmail(),
            'address_line_1'    =>  $c->getAddress1(),
            'city'              =>  $c->getCity(),
            'zipcode'           =>  $c->getZip(),
            'tel_cc_no'         =>  $c->getTelCc(),
            'tel_no'            =>  $c->getTel(),
            'country_code'      =>  $cdetails['registrant_contact']['country_code'],
        );
        $optional_params = array(
            'address_line_2'    =>  $c->getAddress2(),
            'address_line_3'    =>  $c->getAddress3(),
            'state'             =>  $c->getState(),
        );
        $params = array_merge($optional_params, $required_params);
        $result = $this->_makeRequest('customers/' . $customer_id . '/contacts/' . $contact_id, $params, 'put');
        return (!isset($result['message']) AND is_array($result));
    }

    /**
     * transfer domain
     * @return boolean
     */
    public function transferDomain(Registrar_Domain $domain)
    {
        $tld = str_replace('.', '', $domain->getTld());

        $cust = $domain->getContactRegistrar();
        $cust_email = $cust->getEmail();
        $customer = $this->_getCustomerDetails($domain, $cust_email);

        if (is_array($customer)) {
            foreach ($customer as $cus) {
                $cus['email'] = trim(strtolower($cus['email']));
                if ($cus['email'] == $cust_email) {
                    $customer_id = $cus['customer_id'];
                }
            }
        }

        $get_defaultContact = $this->_getDefaultContactDetails($customer_id, $tld, $cust);

        $reg_contact_id     = $get_defaultContact['registrant_contact']['contact_id'];
        $admin_contact_id   = $get_defaultContact['admin_contact']['contact_id'];
        $tech_contact_id    = $get_defaultContact['tech_contact']['contact_id'];
        $billing_contact_id = $get_defaultContact['billing_contact']['contact_id'];

        $ns_ = array();
        $ns_[] = $domain->getNs1();
        $ns_[] = $domain->getNs2();
        if($domain->getNs3())  {
            $ns_[] = $domain->getNs3();
        }
        if($domain->getNs4())  {
            $ns_[] = $domain->getNs4();
        }

        $ns = implode(',', $ns_);

        // cek default ns customer LQ
        $def_ns = $this->_makeRequest('customers/'.$customer_id.'/ns/default');
        $lq_defaultns = array();

        if (!empty($def_ns["body"]["ns1"])) { // ambil defaultnya ns1
            $lq_defaultns[] = $def_ns["body"]["ns1"];
        }
        if (!empty($def_ns["body"]["ns2"])) { // ambil defaultnya ns2
            $lq_defaultns[] = $def_ns["body"]["ns2"];
        }
        if (!empty($def_ns["body"]["ns3"])) { // ambil defaultnya ns3
            $lq_defaultns[] = $def_ns["body"]["ns3"];
        }
        if (!empty($def_ns["body"]["ns4"])) { // ambil defaultnya ns4
            $lq_defaultns[] = $def_ns["body"]["ns4"];
        }

        // simpan sementara
        $default_ns = implode(",", $lq_defaultns);
        if ($this->isTestEnv()) { // khusus testmode di bikin spt berikut
            $default_ns = 'ns1.liqu.id,ns2.liqu.id';
        }

        // cek kalau ns nya kosong ambil dari default nya customer
        if (empty($ns)) {
            $ns = $default_ns;
        }

        $required_params = array(
            'domain_name'           =>  $domain->getName(),
            'auth_code'             =>  $domain->getEpp(),
            'ns'                    =>  $ns,
            'customer_id'           =>  $customer_id,
            'registrant_contact_id' =>  $reg_contact_id,
            'admin_contact_id'      =>  $admin_contact_id,
            'tech_contact_id'       =>  $tech_contact_id,
            'billing_contact_id'    =>  $billing_contact_id,
            'years'                 =>  $domain->getRegistrationPeriod(),
            'invoice_option'        =>  'no_invoice',
            'protect_privacy'       =>  false,
        );
        if($tld == 'asia') {
            $required_params['extra'] = 'asia_contact_id='.$reg_contact_id;
        }
        if($tld == 'us') {
            $required_params['extra'] = 'us_contact_id='.$reg_contact_id;
        }

        try {
            $result = $this->_makeRequest('domains/transfer', $required_params, 'post');
        } catch(Registrar_Exception $e) {
            // jika gagal karena NS, set ns ke $default_ns
            // kemudian di register lagi domainnya
            if (strpos($e->getMessage(), "is not valid NameServer")) {
                $required_params['ns'] = $default_ns;
                $result = $this->_makeRequest('domains/transfer', $required_params, 'post');
            }
        }

        if (!empty($result['domain_id'])) {
            $result['status'] = 'Success';
        } else {
            $result['status'] = 'Failed';
        }

        return ($result['status'] == 'Success');
    }

    /**
     * Cari domain_id
     * @return boolean
     */
    private function _getDomainOrderId(Registrar_Domain $d)
    {
        $domain_name  = str_replace(" ", "", strtolower($d->getName()));
        $param_search = http_build_query(array(
            'limit'             => '100',
            'page_no'           => '1',
            'domain_name'       => $domain_name,
            'exact_domain_name' => '1'
        ));

        $result_search = $this->_makeRequest('domains?'.$param_search);

        if (!empty($result_search) AND is_array($result_search)) {
            foreach ($result_search as $res) {
                if (trim(strtolower($res['domain_name'])) == $domain_name) {
                    return $res['domain_id'];
                }
            }
        }

        throw new Registrar_Exception("Registrar Error<br/>Website doesn't exist for " . $domain_name);
    }

    /**
     * Cek detail domain dan simpan
     * @return boolean
     */
    public function getDomainDetails(Registrar_Domain $d)
    {
        $domain_id = $this->_getDomainOrderId($d);
        $data = $this->_makeRequest('domains/'.$domain_id.'?fields=all');
        
        $d->setRegistrationTime(strtotime($data['creation_date']));
        $d->setExpirationTime(strtotime($data['end_date']));
        $d->setEpp($data['auth_code']);
        $d->setPrivacyEnabled(($data['privacy_protection_enabled'] == 'true'));
        
        /* Contact details */
        $wc = $data['adm_contact'];
        $c = new Registrar_Domain_Contact();
        $c->setId($wc['contact_id'])
            ->setName($wc['name'])
            ->setEmail($wc['email'])
            ->setCompany($wc['company'])
            ->setTel($wc['tel_no'])
            ->setTelCc($wc['tel_cc_no'])
            ->setAddress1($wc['address_line_1'])
            ->setCity($wc['city'])
            ->setCountry($wc['country_code'])
            ->setState($wc['state'])
            ->setZip($wc['zipcode']);
        
        if(isset($wc['address_line_2'])) {
            $c->setAddress2($wc['address_line_2']);
        }
        if(isset($wc['address_line_3'])) {
            $c->setAddress3($wc['address_line_3']);
        }
        $d->setContactRegistrar($c);

        if(isset($data['ns1'])) {
            $d->setNs1($data['ns1']);
        }
        if(isset($data['ns2'])) {
            $d->setNs2($data['ns2']);
        }
        if(isset($data['ns3'])) {
            $d->setNs3($data['ns3']);
        }
        if(isset($data['ns4'])) {
            $d->setNs4($data['ns4']);
        }
        
        return $d;
    }

    /**
     * Hapus domain
     * @return boolean
     */
    public function deleteDomain(Registrar_Domain $domain)
    {
        $domain_id = $this->_getDomainOrderId($domain);

        $result = $this->_makeRequest('domains/'.$domain_id, array(), 'delete');
        return ($result['deleted'] == true);
    }

    /**
     * Register domain
     * @return boolean
     */
    public function registerDomain(Registrar_Domain $domain)
    {
        if($this->_hasCompletedOrder($domain)) {
            return true;
        }
        
        $tld_ = $domain->getTld();
        $tld = str_replace('.', '', $tld_);

        $cust = $domain->getContactRegistrar();
        $cust_email = $cust->getEmail();

        // mendapatkan customer di LQ
        $customer = $this->_getCustomerDetails($domain, $cust_email);

        if (is_array($customer)) {
            foreach ($customer as $cus) {
                $cus['email'] = trim(strtolower($cus['email']));
                if ($cus['email'] == $cust_email) {
                    $customer_id = $cus['customer_id'];
                }
            }
        }

        $get_defaultContact = $this->_getDefaultContactDetails($customer_id, $tld, $cust);

        $reg_contact_id     = $get_defaultContact['registrant_contact']['contact_id'];
        $admin_contact_id   = $get_defaultContact['admin_contact']['contact_id'];
        $tech_contact_id    = $get_defaultContact['tech_contact']['contact_id'];
        $billing_contact_id = $get_defaultContact['billing_contact']['contact_id'];

        $ns_ = array();
        $ns_[] = $domain->getNs1();
        $ns_[] = $domain->getNs2();
        if($domain->getNs3())  {
            $ns_[] = $domain->getNs3();
        }
        if($domain->getNs4())  {
            $ns_[] = $domain->getNs4();
        }

        $ns = implode(',', $ns_);

        // cek default ns customer LQ
        $def_ns = $this->_makeRequest('customers/'.$customer_id.'/ns/default');
        $lq_defaultns = array();

        if (!empty($def_ns["body"]["ns1"])) { // ambil defaultnya ns1
            $lq_defaultns[] = $def_ns["body"]["ns1"];
        }
        if (!empty($def_ns["body"]["ns2"])) { // ambil defaultnya ns2
            $lq_defaultns[] = $def_ns["body"]["ns2"];
        }
        if (!empty($def_ns["body"]["ns3"])) { // ambil defaultnya ns3
            $lq_defaultns[] = $def_ns["body"]["ns3"];
        }
        if (!empty($def_ns["body"]["ns4"])) { // ambil defaultnya ns4
            $lq_defaultns[] = $def_ns["body"]["ns4"];
        }

        // simpan sementara
        $default_ns = implode(",", $lq_defaultns);
        if ($this->isTestEnv()) { // khusus testmode di bikin spt berikut
            $default_ns = 'ns1.liqu.id,ns2.liqu.id';
        }

        // cek kalau ns nya kosong ambil dari default nya customer
        if (empty($ns)) {
            $ns = $default_ns;
        }

        $params = array(
            'domain_name'       =>  $domain->getName(),
            'customer_id'       =>  $customer_id,
            'registrant_contact_id'    =>  $reg_contact_id,
            'billing_contact_id'=>  $billing_contact_id,
            'admin_contact_id'  =>  $admin_contact_id,
            'tech_contact_id'   =>  $tech_contact_id,
            'years'             =>  $domain->getRegistrationPeriod(),
            'ns'                =>  $ns,
            'purchase_privacy_protection'   => false,
            'privacy_protection_enabled'    => false,
            'invoice_option'    =>  'no_invoice',
        );
        if($tld == 'asia') {
            $params['extra'] = 'asia_contact_id='.$reg_contact_id;
        }
        if($tld == 'us') {
            $params['extra'] = 'us_contact_id='.$reg_contact_id;
        }

        try {
            $result = $this->_makeRequest('domains', $params, 'post');
        } catch(Registrar_Exception $e) {
            // jika gagal karena NS, set ns ke $default_ns
            // kemudian di register lagi domainnya
            if (strpos($e->getMessage(), "is not valid NameServer")) {
                $params['ns'] = $default_ns;
                $result = $this->_makeRequest('domains', $params, 'post');
            }
        }

        if (!empty($result['domain_id'])) {
            $result['status'] = 'Success';
        } else {
            $result['status'] = 'Failed';
        }

        return ($result['status'] == 'Success');
    }

    /**
     * Renew domain
     * @return boolean
     */
    public function renewDomain(Registrar_Domain $domain)
    {
        $domain_id = $this->_getDomainOrderId($domain);
        $params = array(
            'years'                       => $domain->getRegistrationPeriod(),
            'current_date'                => date('Y-m-d H:i:s', $domain->getExpirationTime()),
            'purchase_privacy_protection' => 'false',
            'invoice_option'              => 'no_invoice'
        );

        $result = $this->_makeRequest('domains/'.$domain_id.'/renew', $params, 'post');
        return (is_array($result) AND isset($result['transaction_id']));
    }

    /**
     * Enable PP domain
     * @return boolean
     */
    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        $domain_id = $this->_getDomainOrderId($domain);

        $result = $this->_makeRequest('domains/'.$domain_id.'/privacy_protection', $params, 'put');

        return (strtolower($result['privacy_protection_enabled']) == 'true');
    }

    /**
     * Disable PP domain
     * @return boolean
     */
    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        $domain_id = $this->_getDomainOrderId($domain);

        $result = $this->_makeRequest('domains/'.$domain_id.'/privacy_protection', $params, 'delete');

        return (strtolower($result['privacy_protection_enabled']) == 'false');
    }

    /**
     * EPP domain
     * @return boolean
     */
    public function getEpp(Registrar_Domain $domain)
    {
        $domain_id = $this->_getDomainOrderId($domain);
        $auth_code = $this->_makeRequest('domains/'.$domain_id.'/auth_code');

        if(empty($auth_code)) {
            throw new Registrar_Exception('Domain EPP code can be retrieved from domain registrar');
        }

        return $auth_code;
    }

    /**
     * Lock Theft Protection domain
     * @return boolean
     */
    public function lock(Registrar_Domain $domain)
    {
        $domain_id = $this->_getDomainOrderId($domain);

        $result = $this->_makeRequest('domains/'.$domain_id.'/theft_protection', array(), 'put');
        return (strtolower($result['theft_protection']) == 'true');
    }

    /**
     * Unlock Theft Protection domain
     * @return boolean
     */
    public function unlock(Registrar_Domain $domain)
    {
        $domain_id = $this->_getDomainOrderId($domain);

        $result = $this->_makeRequest('domains/'.$domain_id.'/theft_protection', array(), 'delete');
        return (strtolower($result['theft_protection']) == 'false');
    }

    /**
     * Cek customer_id
     * @return boolean
     */
    private function _getCustomerDetails(Registrar_Domain $domain, $cust_email)
    {
        $param_search = http_build_query(array(
            'limit'     => 100,
            'page_no'   => 1,
            'status'    => 'Active',
            'email'     => $cust_email
        ));

        $result = $this->_makeRequest('customers?'.$param_search);

        // jika customer tidak ditemukan, buat customer baru
        if(empty($result)) {
            try {
                $result = $this->_createCustomer($domain);
            } catch(Registrar_Exception $e) {
                $result = $this->_makeRequest('customers?'.$param_search);
            }
        }

        return $result;
    }

    /**
     * Buat customer baru
     * @return boolean
     */
    private function _createCustomer(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();
        $company = $c->getCompany();

        if (!isset($company) || strlen(trim($company)) == 0 ){
            $company = 'N/A';
        }
        $phoneNum = $c->getTel();
        $phoneNum = preg_replace( "/[^0-9]/", "", $phoneNum);
        $phoneNum = substr($phoneNum, 0, 12);
        $params = array(
            'email'              =>  $c->getEmail(),
            'password'           =>  $c->getPassword(),
            'name'               =>  $c->getName(),
            'company'            =>  $company,
            'address_line_1'     =>  $c->getAddress1(),
            'address_line_2'     =>  $c->getAddress2(),
            'city'               =>  $c->getCity(),
            'state'              =>  $c->getState(),
            'country_code'       =>  $c->getCountry(),
            'zipcode'            =>  $c->getZip(),
            'tel_cc_no'          =>  $c->getTelCc(),
            'tel_no'             =>  $phoneNum,
        );
        $optional_params = array(
            'address_line_3'     =>  '',
            'alt_tel_cc_no'      =>  '',
            'alt_tel_no'         =>  '',
            'fax_cc_no'          =>  '',
            'fax_no'             =>  '',
            'mobile_cc_no'       =>  '',
            'mobile_no'          =>  '',
        );
        $params = array_merge($optional_params, $params);

        $customer = $this->_makeRequest('customers', $params, 'post');
        return array($customer);
    }

    /**
     * Cek default contact
     * @return boolean
     */
    private function _getDefaultContactDetails($customer_id, $tld = '', $cust)
    {
        $result = $this->_makeRequest('customers/'.$customer_id.'/contacts/default?eligibility_criteria='.$tld);

        // jika tidak ada default kontak untuk tld tsb.
        if (is_array($result) AND empty($result['registrant_contact'])) {

            try {
                // cek dulu di contact customernya
                $result = $this->_makeRequest('customers/'.$customer_id.'/contacts?limit=10&page_no=1&eligibility_criteria='.$tld);

                // jika ada gunakan contactnya
                $result = array(
                        'registrant_contact' => $result[0],
                        'admin_contact'      => $result[0],
                        'tech_contact'       => $result[0],
                        'billing_contact'    => $result[0],
                );
            } catch(Registrar_Exception $e) {
                // jika tidak ada, buat contactnya dulu
                $company = $cust->getCompany();
                if (!isset($company) || strlen(trim($company)) == 0 ){
                    $company = 'N/A';
                }
                $phoneNum = $cust->getTel();
                $phoneNum = preg_replace( "/[^0-9]/", "", $phoneNum);
                $phoneNum = substr($phoneNum, 0, 12);
                $params = array(
                    'email'              =>  $cust->getEmail(),
                    'name'               =>  $cust->getName(),
                    'company'            =>  $company,
                    'address_line_1'     =>  $cust->getAddress1(),
                    'address_line_2'     =>  $cust->getAddress2(),
                    'city'               =>  $cust->getCity(),
                    'state'              =>  $cust->getState(),
                    'country_code'       =>  $cust->getCountry(),
                    'zipcode'            =>  $cust->getZip(),
                    'tel_cc_no'          =>  $cust->getTelCc(),
                    'tel_no'             =>  $phoneNum,
                    'eligibility_criteria' => $tld,
                );
                if ($tld == 'asia') {
                    $extra['asia_country']                   = $cust->getCountry();
                    $extra['asia_entity_type']               = 'other';
                    $extra['asia_other_entity_type']         = 'passport';
                    $extra['asia_identification_type']       = 'other';
                    $extra['asia_other_identification_type'] = 'naturalPerson';
                    $extra['asia_identification_number']     = $cust->getDocumentNr();
                    $params['extra'] = http_build_query($extra);
                } elseif ($tld == 'us') {
                    $extra['us_category'] = 'citizen';
                    $extra['us_purpose']  = 'personal';
                    $params['extra'] = http_build_query($extra);
                } else {
                    throw new Registrar_Exception('TLD Not Support.');
                }
                $optional_params = array(
                    'address_line_3'     =>  '',
                    'alt_tel_cc_no'      =>  '',
                    'alt_tel_no'         =>  '',
                    'fax_cc_no'          =>  '',
                    'fax_no'             =>  '',
                    'mobile_cc_no'       =>  '',
                    'mobile_no'          =>  '',
                );
                $params = array_merge($optional_params, $params);

                try {
                    $contact = $this->_makeRequest('customers/'.$customer_id.'/contacts', $params, 'post');
                } catch(Registrar_Exception $e) {
                    throw new Registrar_Exception('Error Creating .'.$tld.' Contact.');
                }

                // jika berhasil gunakan contactnya
                $result = array(
                        'registrant_contact' => $contact,
                        'admin_contact'      => $contact,
                        'tech_contact'       => $contact,
                        'billing_contact'    => $contact,
                );
            }
        }

        // cek emailnya sama atau tidak, jika tidak buat contact dengan email tersebut.
        if ($cust->getEmail() != $result['registrant_contact']['email']) {
            throw new Registrar_Exception('Email tidak sama, buat contact baru');
        }

        return $result;
    }
    
    /**
     * Cek domain sudah jadi belum
     * @return boolean
     */
    private function _hasCompletedOrder(Registrar_Domain $domain)
    {
        $domain_name  = str_replace(" ", "", strtolower($domain->getName()));
        $param_search = http_build_query(array(
            'limit'             => '100',
            'page_no'           => '1',
            'domain_name'       => $domain_name,
            'exact_domain_name' => '1'
        ));

        $result_search = $this->_makeRequest('domains?'.$param_search);

        if (!empty($result_search) AND is_array($result_search)) {
            foreach ($result_search as $res) {
                if (trim(strtolower($res['domain_name'])) == $domain_name) {
                    $domain_id = $res['domain_id'];
                    try {
                        $data = $this->_makeRequest('domains/'.$domain_id.'?fields=all');
                        return (strtolower($data['order_status']) == 'live');
                    } catch(Registrar_Exception $e) {
                        return false;
                    }
                }
            }
        }

        return false;
    }
    
    /**
     * Cek TestMode
     * @return boolean
     */
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
            // kalau ada testmode berarti pake domainsas
            return 'https://api.domainsas.com/';
        }
        // kalau testMode kosong berarti pake live
        return 'https://api.liqu.id/';
    }

    /**
     * @param array $params
     * @return string
     */
    public function includeAuthorization()
    {
        return $this->config['userid'].':'.$this->config['api-key'];
    }

    /**
     * Perform call to Api
     * @param string $url
     * @param array $params
     * @param string $method
     * @return string
     * @throws Registrar_Exception
     */
    protected function _makeRequest($url, $params = array(), $method = 'get')
    {
        # cek aktif g extensi curl nya
        if (!extension_loaded("curl")) {
            throw new Registrar_Exception("PHP extension curl must be loaded.");
        }

        $api_url = $this->_getApiUrl();

        $API_VERSION = "v1";
        $request_url = $api_url . $API_VERSION . '/' . $url;

        # cek init error tidak
        if (($ch = curl_init($request_url)) === false) {
            throw new Registrar_Exception("PHP extension curl must be loaded.");
        }

        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (!$this->isTestEnv()) { // kalau ke domainsas di false aja verify nya
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        switch ($method) {
            case 'get':
                curl_setopt($ch, CURLOPT_HTTPGET, 1);
                break;
            case 'post':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
                break;
            case 'put':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
                break;
            case 'delete':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
                break;
        }
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->includeAuthorization());
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);

        $response   = curl_exec($ch);
        $curl_error = curl_error($ch);

        # langsung cek respon
        if (!$response) {
            throw new Registrar_Exception($curl_error ? $curl_error : "Unable to request data from liquid server (".$request_url.")");
        }

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header      = substr($response, 0, $header_size);
        $body        = substr($response, $header_size);
        $code        = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (strpos($header, "404 Not Found")) {
            throw new Registrar_Exception("Unable to request data from liquid server, URL is not valid (".$request_url.")");
        }

        curl_close($ch);
        $return = array(
            'header' => $header,
            'body'   => json_decode($body, true),
            'code'   => $code,
        );

        if(isset($return['body']['message']) && $return['code'] != 200) {
            throw new Registrar_Exception($return['body']['message'], $return['code']);
        }

        # langsung return body nya saja
        return $return['body'];
    }
}