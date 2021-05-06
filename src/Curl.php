<?php

class Curl {
    const DEFAULT_USERPWD = 'test: test@domain.com';
    
    /** @var bool */
    public $proxy = false;
    
    /** @var object */
    public $cacheInstance = null;
    
    /** @var array */
    public $responseHeaders = array();
    public $requestHeaders  = array();
    
    /** @var string */
    public $info;
    public $error;
    
    /** @var array */
    private $curlOptions;
    private $curlInstance;
    
    /** @var bool */
    private $debug = false;
    
    /** @var string */
    
    private $cookiePath = null;

    /**
     * @param array $options
     */
    public function __construct($options = array()) {
        if (!function_exists('curl_init')) {
            throw new \Exception('cURL module must be enabled!');
        }
        // the options of curl should be init here.
        $this->initializeCurlOptions();
        if (!empty($options['debug'])) {
            $this->debug = true;
        }
        if(!empty($options['cookie'])) {
            if($options['cookie'] === true) {
                $this->cookiePath = 'curl_cookie.txt';
            } else {
                $this->cookiePath = $options['cookie'];
            }
        }
        if (!empty($options['cache'])) {
            if (class_exists('curl_cache')) {
                $this->cacheInstance = new curl_cache();
            }
        }
    }

    /**
     * HTTP GET method
     *
     * @param string $url
     * @param array $params
     * @param array $curlOptions
     * @return bool
     */
    public function get($url, $params = array(), $curlOptions = array()) {
        $curlOptions['CURLOPT_HTTPGET'] = 1;

        if (!empty($params)){
            $url .= (stripos($url, '?') !== false) ? '&' : '?';
            $url .= http_build_query($params, '', '&');
        }
        return $this->request($url, $curlOptions);
    }

    /**
     * HTTP POST method
     *
     * @param string $url
     * @param array|string $params
     * @param array $curlOptions
     * @return bool
     */
    public function post($url, $params = '', $curlOptions = array()) {
        if (is_array($params)) {
            $params = $this->makePostFields($params);
        }
        $curlOptions['CURLOPT_POST'] = 1;
        $curlOptions['CURLOPT_POSTFIELDS'] = $params;
        return $this->request($url, $curlOptions);
    }

    /**
     * HTTP PUT method
     *
     * @param string $url
     * @param array $params
     * @param array $curlOptions
     * @return bool
     */
    public function put($url, $params = array(), $curlOptions = array()) {
        $file = $params['file'];
        
        if (!is_file($file)){
            return null;
        }
        
        $fp   = fopen($file, 'r');
        $size = filesize($file);
        
        $curlOptions['CURLOPT_PUT']        = 1;
        $curlOptions['CURLOPT_INFILESIZE'] = $size;
        $curlOptions['CURLOPT_INFILE']     = $fp;
        
        if (!isset($this->curlOptions['CURLOPT_USERPWD'])) {
            $curlOptions['CURLOPT_USERPWD'] = self::DEFAULT_USERPWD;
        }
        
        $ret = $this->request($url, $curlOptions);        
        fclose($fp);
        
        return $ret;
    }

    /**
     * HTTP DELETE method
     *
     * @param string $url
     * @param array $params
     * @param array $curlOptions
     * @return bool
     */
    public function delete($url, $param = array(), $curlOptions = array()) {        
        $curlOptions['CURLOPT_CUSTOMREQUEST'] = 'DELETE';
        
        if (!isset($curlOptions['CURLOPT_USERPWD'])) {
            $curlOptions['CURLOPT_USERPWD'] = self::DEFAULT_USERPWD;
        }
        
        $ret = $this->request($url, $curlOptions);
        
        return $ret;
    }
}

