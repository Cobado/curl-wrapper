<?php

namespace CurlWrapper;

class CurlHttpResponse {
    public $headers = array();
    public $statusCode;
    public $text = '';

    public function __construct($statusCode, $headers, $text) {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->text = $text;
    }

    public function __toString() {
        return $this->text;
    }

    public function text() {
        return $this->text;
    }

    public function json() {
        return json_decode($this->text);
    }
}
