<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace CryptoChannel;

/**
 * Description of ChannelOption
 *
 * @author ermanno
 */

class ChannelOption 
{
    private $option;
    private $cookie;
    private $callType='';
    private $responseCryption='';
    private $status = '';
    
    public function __construct($option = array(), $cookie = array(), $key = false)
    {
        
        if (!is_array($option)) {
            $option = array();
        }
        if (!is_array($cookie)) {
            $cookie = array();
        }
        $this->key = $key;
        
        $option['method'] = array_get($option, 'method', 'POST');
        $option['crypting'] = (!isset($option['crypting']) or $option['crypting']);
        $option['type'] = array_get($option, 'type', 'json');
        
        if (array_get($option, 'cookie', false)) {
            foreach($option['cookie'] as $name=>$value) {
                $cookie[$name] = $value;
            }
        }
        $this->option = $option;
        $this->cookie = $cookie;
    }
    
    public function setCallType($type)
    {
        $this->callType = $type;
    }
    
    public function getMethod()
    {
        return $this->option['method'];
    }
    
    public function getHeader()
    {
        return "Accept-language: en\r\n".
            "CryptoChannel-Type: {$this->callType}\r\n".
            $this->getHeaderOption().
            $this->getHeaderContentType().
            $this->getHeaderCryptionType().
            $this->getHeaderCookie();
    }
    public function isCrypting()
    {
        return $this->option['crypting'];
    }
    public function getType()
    {
        return $this->option['type'];
    }
    
    /**
     * 
     * @return string
     */
    private function getHeaderOption()
    {
        $header = '';
        if (isset($this->option['headers'])) {
            if (is_array($this->option['headers'])) {
                $header .= implode('\r\n',$this->option['headers']);
            } else {
                $header .= trim($this->option['headers']);
            }
            $header .= "\r\n";
        }
        return $header;
    }
    private function getHeaderContentType()
    {
        $header = '';
        switch($this->option['type']) {
            case 'json':
                $header .= "Content-Type: application/json; charset=UTF-8\r\n";
                break;
            case 'html':
                $header .= "Content-Type: application/x-www-form-urlencoded; charset=UTF-8\r\n";
                break;
            case 'xml':
            case 'plain':
                $header .= "Content-Type: text/{$this->option['type']}; charset=UTF-8\r\n";
                break;
            default:
                $header .= "Content-Type: {$this->option['type']}\r\n";
        }
        return $header;
    }
    private function getHeaderCryptionType()
    {
        $header = '';
        if ($this->option['crypting'] and $this->key) {
            $header .= "Cryption-Type: CryptoChannel\r\n";
            $header .= "CryptoChannel-Token: {$this->key->getToken()}\r\n";
        }
        return $header;
    }
    private function getHeaderCookie()
    {
        $header = '';
        $str_cookies = '';
        foreach($this->cookie as $k=>$v) {
            $str_cookies .= $k.'='.$v.';';
        }
        $header .= "Cookie: {$str_cookies}\r\n";
        return $header;
    }
    
    private function encodeData($data)
    {
        switch($this->getType()) {
            case 'json' : 
                if (is_array($data)) {
                    $data = \json_encode($data);
                }
                break;
            case 'html':
            case 'xml':
            case 'plain':
                if (is_array($data)) {
                    $data = http_build_query($data);
                }
                break;
        }
        return $data;
    }
    
    public function sendData($data)
    {
        $data = $this->encodeData($data);
        // i dati vengono crittati se richiesto
        if ($this->isCrypting() and $this->key) {
            $data = $this->key->encrypt($data);
        }
        return $data;
    }
    
    /**
     * Analizza la risposta decidendo come filtrare i dati in funzione degli header pervenuti
     * @param type $headers
     * @param type $data
     * @param type $func
     * @return type
     */
    public function parseResponse($headers, $data, $func = false)
    {
        $cryption = '';
        foreach($headers as $s) {
            $parts = array();
            if(preg_match('|^Set-Cookie:\s*([^=]+)=([^;]+);(.+)$|', $s, $parts)) {
                $this->cookie[$parts[1]] = $parts[2];
            }
            if(preg_match('|^Cryption-Type:\s*(.+)$|', $s, $parts)) {
                $cryption = strtolower($parts[1]);
            }
            if(preg_match('|^CryptoChannel-Token:\s*(.+)$|', $s, $parts)) {
                $this->key->setServerToken(strtolower($parts[1]));
            }
            if(preg_match('|^CryptoChannel-Status:\s*(.+)$|', $s, $parts)) {
                $this->status = (strtoupper($parts[1]));
            }
            if ($func) {
                call_user_func_array($func, array($s));
            }
        }
        // il debug visualizza solo i primi 100 caratteri
        $maxLen = 100;
        $debug = array(substr($data, 0, $maxLen).(strlen($data)>$maxLen ? '...('.(strlen($data)).')' : ''));
        if ($cryption == 'cryptochannel' and $this->key) {
            $data = $this->key->decrypt($data);
            $debug[] = substr($data, 0, $maxLen).(strlen($data)>$maxLen ? '...('.(strlen($data)).')' : '');
            $debug[] = $this->key->getSimmetric();
        }
        // dati ricevuti dal server
        Util::log('Content from Server', $debug);
        
        return $data;
    }
    
    public function getCookie()
    {
        $arg = func_get_args();
        if (!count($arg)) {
            return $this->cookie;
        } 
        return @$this->cookie[$arg[0]];
    }
    
    public function getStatus()
    {
        return $this->status;
    }
}