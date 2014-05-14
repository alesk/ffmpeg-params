<?php

class CeltraClient
{
    private $url;
    private $username;
    private $password;
    
    public function __construct($username=null, $password=null, $url='https://api.celtra.com/v1/')
    {
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
    }

    public function request($method, $path, $data='', $expectedCode='auto', &$respCode=null, &$respHeaders=array(), $contentType='application/json; charset=utf-8')
    {
        $method = strtoupper($method);

        $headers = array(
            'User-Agent'     => 'CeltraApiCliClient/0.1',
            'Content-Type'   => $contentType,
            'Content-Length' => strlen($data),
            'Connection'     => 'close',
        );
        
        if (isset($this->username))
            $headers['Authorization'] = 'Basic '.base64_encode($this->username.':'.$this->password);
        
        $headerStrings = array();
        foreach ($headers as $k=>$v)
            $headerStrings[] = "$k: $v";
        
        $context = stream_context_create(array('http' => array(
            'method' => $method,
            'header' => $headerStrings,
            'content' => $data,
            'ignore_errors' => 1,
            'timeout' => 3000,
        )));
        
        $fp = fopen(rtrim($this->url, '/') .'/'.ltrim($path, '/'), 'r', false, $context);
        $meta = stream_get_meta_data($fp);
        $respData = stream_get_contents($fp);
        fclose($fp);

        $responseLines = $meta['wrapper_data'];

        // Parse status line
        list($respProtocol, $respCode, $respReason) = explode(' ', array_shift($responseLines), 3);
        $respCode = (int)$respCode;

        // Parse headers
        $respHeaders = array();
        foreach ($responseLines as $line) {
            list($k, $v) = explode(':', $line, 2);
            $respHeaders[strtoupper($k)] = trim($v);
        }

        if ($expectedCode === 'auto') {
            switch ($method) {
                case 'GET':     $expectedCode = 200; break;
                case 'HEAD':    $expectedCode = 200; break;
                case 'POST':    $expectedCode = 201; break;
                case 'PUT':     $expectedCode = 204; break;
                case 'DELETE':  $expectedCode = 204; break;
            }
        }

        if (isset($expectedCode) && $respCode !== $expectedCode)
            throw new Exception("Invalid response code: $respCode $respReason\n--\n$respData");

        return $respData;
    }
}
