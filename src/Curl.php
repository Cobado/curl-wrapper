<?php

namespace CurlWrapper;

use CurlWrapper\CurlHttpResponse as CurlHttpResponse;

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

    /**
     * Set curl option
     *
     * @param string $name
     * @param string $value
     */
    public function addCurlOption($name, $value) {
        if (stripos($name, 'CURLOPT_') === false) {
            $name = strtoupper('CURLOPT_' . $name);
        }
        $this->curlOptions[$name] = $value;
    }

    /**
     * Set curl options
     *
     * @param array $curlOptions 
     */
    public function addCurlOptions($curlOptions = array()) {
        if (is_array($curlOptions)) {
            foreach($curlOptions as $name => $val){
                $this->addCurlOption($name, $val);
            }
        }
    }

    /**
     * Reset http method
     *
     */
    public function resetCurlOptions() {
        unset($this->curlOptions['CURLOPT_HTTPGET']);
        unset($this->curlOptions['CURLOPT_POST']);
        unset($this->curlOptions['CURLOPT_POSTFIELDS']);
        unset($this->curlOptions['CURLOPT_PUT']);
        unset($this->curlOptions['CURLOPT_INFILE']);
        unset($this->curlOptions['CURLOPT_INFILESIZE']);
        unset($this->curlOptions['CURLOPT_CUSTOMREQUEST']);
    }

    /**
     * Resets the CURL options that have already been set
     */
    private function initializeCurlOptions() {
        $this->curlOptions = [
            'CURLOPT_USERAGENT' => 'cURL',
            // True to include the header in the output
            'CURLOPT_HEADER' => 0,
            // True to Exclude the body from the output
            'CURLOPT_NOBODY' => 0,
            // TRUE to follow any "Location: " header that the server
            // sends as part of the HTTP header (note this is recursive,
            // PHP will follow as many "Location: " headers that it is sent,
            // unless CURLOPT_MAXREDIRS is set).
            //$this->curlOptions['CURLOPT_FOLLOWLOCATION'] = 1;
            'CURLOPT_MAXREDIRS' => 10,
            'CURLOPT_ENCODING' => '',
            // TRUE to return the transfer as a string of the return
            // value of curl_exec() instead of outputting it out directly.
            'CURLOPT_RETURNTRANSFER' => 1,
            'CURLOPT_BINARYTRANSFER' => 0,
            'CURLOPT_SSL_VERIFYPEER' => 0,
            'CURLOPT_SSL_VERIFYHOST' => 2,
            'CURLOPT_CONNECTTIMEOUT' => 30,
        ];
    }

    /**
     * Single HTTP Request
     *
     * @param string $url The URL to request
     * @param array $options
     * @return bool
     */
    protected function request($url, $curlOptions = array()) {
        // create curl instance
        $curl = curl_init($url);
        $curlOptions['url'] = $url;
        $this->resetCurlOptions();
        $this->prepareRequest($curl, $curlOptions);
        if ($this->cacheInstance && $httpbody = $this->cacheInstance->get($this->curlOptions)) {
            return $httpbody;
        } else {
            $httpbody = curl_exec($curl);
            if ($this->cacheInstance) {
                $this->cacheInstance->set($this->curlOptions, $httpbody);
            }
        }

        $this->info  = curl_getinfo($curl);
        $this->error = curl_error($curl);

        if ($this->debug){
            var_dump($this->info);
            var_dump($this->error);
        }

        curl_close($curl);

        $response = new CurlHttpResponse($this->info['http_code'], $this->responseHeaders, $httpbody);

        if (!empty($this->error)) {
            throw new \Exception($this->error);
        }
        return $response;
    }

    /**
     * Set options for individual curl instance
     *
     * @param object $curl A curl handle
     * @param array $options
     * @return object The curl handle
     */
    private function prepareRequest($curl, $curlOptions) {
        // set cookie
        if (!empty($this->cookiePath) || !empty($curlOptions['cookie'])) {
            $this->addCurlOption('cookiejar', $this->cookiePath);
            $this->addCurlOption('cookiefile', $this->cookiePath);
        }

        // set proxy
        if (!empty($this->proxy) || !empty($curlOptions['proxy'])) {
            $this->addCurlOptions($this->proxy);
        }

        $this->addCurlOptions($curlOptions);
        // set headers
        if (empty($this->requestHeaders)){
            $this->appendRequestHeaders(array(
                ['User-Agent', $this->curlOptions['CURLOPT_USERAGENT']],
                ['Accept-Charset', 'UTF-8']
            ));
        }

        self::applyCurlOption($curl, $this->curlOptions);
        //curl_setopt($curl, CURLOPT_HEADERFUNCTION, array(&$this, 'handleResponseHeaders'));
        curl_setopt($curl, CURLOPT_HTTPHEADER, self::prepareRequestHeaders($this->requestHeaders));

        if ($this->debug){
            var_dump($this->curlOptions);
            var_dump($this->requestHeaders);
        }
        return $curl;
    }

    /**
     * Set HTTP Request Header
     *
     * @param array $headers
     */
    public function appendRequestHeaders(array $headers) {
        foreach ($headers as $header) {
            $this->appendRequestHeader($header[0], $header[1]);
        }
    }

    public function appendRequestHeader($key, $value) {
        $this->requestHeaders[] = ["$key", "$value"];
    }

    private static function applyCurlOption($curl, $curlOptions) {
        // Apply curl options
        foreach($curlOptions as $name => $value) {
            if (is_string($name)) {
                curl_setopt($curl, constant(strtoupper($name)), $value);
            }
        }
    }

    private static function prepareRequestHeaders($headers) {
        $processedHeaders = array();
        foreach ($headers as $header) {
            $processedHeaders[] = urlencode($header[0]) . ': ' . urlencode($header[1]);
        }
        return $processedHeaders;
    }
}
