<?php
/*
Copyright (c) 2013, PocketRent Ltd.
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of the PocketRent nor the
      names of its contributors may be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

class CurlRequest {

    private static $curl_opts = array(
        // Boolean options
        "autoreferer"       => CURLOPT_AUTOREFERER,
        "cookie_session"    => CURLOPT_COOKIESESSION,
        "cert_info"         => CURLOPT_CERTINFO,
        "crlf"              => CURLOPT_CRLF,
        "filetime"          => CURLOPT_FILETIME,
        "follow_location"   => CURLOPT_FOLLOWLOCATION,
        "forbid_reuse"      => CURLOPT_FORBID_REUSE,
        "fresh_connect"     => CURLOPT_FRESH_CONNECT,
        "use_http_proxy"    => CURLOPT_HTTPPROXYTUNNEL,
        "netrc"             => CURLOPT_NETRC,
        "no_body"           => CURLOPT_NOBODY,
        "ssl_verify_peer"   => CURLOPT_SSL_VERIFYPEER,
        // Integer options
        "buffer_size"       => CURLOPT_BUFFERSIZE,
        "dns_cache_timeout" => CURLOPT_DNS_CACHE_TIMEOUT,
        "low_speed_limit"   => CURLOPT_LOW_SPEED_LIMIT,
        "low_speed_time"    => CURLOPT_LOW_SPEED_TIME,
        "max_connects"      => CURLOPT_MAXCONNECTS,
        "port"              => CURLOPT_PORT,
        "resume_from"       => CURLOPT_RESUME_FROM,
        "max_recv_speed_large" => CURLOPT_MAX_RECV_SPEED_LARGE,
        "max_send_speed_large" => CURLOPT_MAX_SEND_SPEED_LARGE,
        // String options
        "encoding"          => CURLOPT_ENCODING,
        "url"               => CURLOPT_URL,
        "user_agent"        => CURLOPT_USERAGENT,
    );

    private $handle = null;
    private $follow_redirects = false;
    private $max_redirs = 10;

    public function __construct($url=null) {
        $this->handle = curl_init($url);
        $this->setCurlOption(CURLOPT_RETURNTRANSFER, true);
        $this->setCurlOption(CURLOPT_HEADER, true);
    }


    public function setCurlOption($option, $val) {
        return curl_setopt($this->handle, $option, $val);
    }

    public function setProxy($location, $port=null) {
        $this->use_http_proxy = true;
        $this->setCurlOption(CURLOPT_PROXY, $location);
        if ($port !== null) {
            $this->setCurlOption(CURLOPT_PROXYPORT, $port);
        }
    }

    public function __set($opt, $val) {
        if ($opt == "connect_timeout") {
            $val = (int)($val*1000);
            $this->setCurlOption(CURLOPT_CONNECTTIMEOUT_MS, $val);
        } elseif ($opt == "timeout") {
            $val = (int)($val*1000);
            $this->setCurlOption(CURLOPT_TIMEOUT_MS, $val);
        } elseif ($opt == "follow_location") {
            $this->follow_redirects = (bool)$val;
            $this->setCurlOption(CURLOPT_HEADER, $val);
        } elseif ($opt == "max_redirs") {
            $this->max_redirs = (int)$val;
        } else {
            $curl_opt = CurlRequest::$curl_opts[$opt];
            $this->setCurlOption($curl_opt, $val);
        }
    }

    public function exec() {
        // Following redirects doesn't work properly
        // if open_basedir is set
        $redirs = 0;
        if ($this->follow_redirects) {
            while ($redirs < $this->max_redirs) {
                $content = curl_exec($this->handle);
                $status = (int)curl_getinfo($this->handle, CURLINFO_HTTP_CODE);
                // Redirect code, follow it if we can
                if ($status == 301 || $status == 302) {
                    $redirs += 1;
                    $matches = array();
                    $res = preg_match(
                        '#Location:\s*(https?://[\d\w\-._~:/?\#\[\]@!$&\'()*+,;=]+)#',
                        $content, $matches);
                    if ($res === 1) {
                        $new_loc = $matches[1];

                        curl_setopt($this->handle, CURLOPT_URL, $new_loc);
                    } else {
                        break;
                    }

                } else { break; }
            }
        } else {
            $content = curl_exec($this->handle);
        }
        return new CurlResponse($content, $redirs, $this->handle);
    }

    public function getHandle() {
        return $this->handle;
    }
}

class CurlResponse {

    private $content = "";
    private $info = array();
    private $redirect_count = 0;

    public function __construct($content, $redirs, $handle) {
        $this->content = $content;
        $this->info = curl_getinfo($handle); // Without an opt, it returns all data as an array
        $this->redirect_count = $redirs;
    }

    public function success() {
        return (bool)$content;
    }

    public function getContent() {
        return $this->content;
    }

    public function getHeader() {
        return substr($this->content,0,$this->getHeaderSize());
    }

    public function getBody() {
        return substr($this->content,$this->getHeaderSize());
    }

    private function getInfo($idx) {
        if (array_key_exists($idx, $this->info)) {
            return $this->info[$idx];
        } else {
            return null;
        }
    }

    public function getUrl() {
        return $this->getInfo("url");
    }

    public function getContentType() {
        return $this->getInfo("content_type");
    }

    public function getHTTPCode() {
        return $this->getInfo("http_code");
    }

    public function getHeaderSize() {
        return $this->getInfo("header_size");
    }

    public function getRequestSize() {
        return $this->getInfo("request_size");
    }

    public function getFiletime() {
        return $this->getInfo("filetime");
    }

    public function getRedirectCount() {
        return $this->redirect_count;
    }

    public function getTotalTime() {
        return $this->getInfo("total_time");
    }

    public function getLookupTime() {
        return $this->getInfo("namelookup_time");
    }

    public function getConnectTime() {
        return $this->getInfo("connect_time");
    }

    public function getPreTransferTime() {
        return $this->getInfo("pretransfer_time");
    }

    public function getUploadSize() {
        return $this->getInfo("size_upload");
    }

    public function getDownloadSize() {
        return $this->getInfo("size_download");
    }

    public function getUploadSpeed() {
        return $this->getInfo("speed_upload");
    }

    public function getDownloadSpeed() {
        return $this->getInfo("speed_download");
    }

    public function getDownloadContentLength() {
        return $this->getInfo("download_content_length");
    }

    public function getUploadContentLength() {
        return $this->getInfo("upload_content_length");
    }

    public function getStartTransferTime() {
        return $this->getInfo("starttransfer_time");
    }

    public function getRedirectTime() {
        return $this->getInfo("redirect_time");
    }

    public function getCertInfo() {
        return $this->getInfo("certinfo");
    }

    public function getRequestHeader() {
        return $this->getRequestHeader();
    }
}
