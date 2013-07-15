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

class Crawler extends Object {

    private $scheduler = null;

    private $num_requests = 10;

    public function start($url) {
        $this->scheduler = new SplPriorityQueue;
        $this->schedulePage(new CrawlPage($url, "Root"));
        $this->startCrawling();
    }

    public function schedulePage($page) {
        $priority = $this->getPriority($page->getType(), $page->getPriority());
        $this->scheduler->insert($page, $priority);
    }

    public function getPriority($type, $priority) {
        return $priority;
    }

    public function setNumRequests($num) {
        $this->num_requests = $num;
    }

    public function startCrawling() {
        while (!$this->scheduler->isEmpty()) {
            $page = $this->scheduler->extract();

            $page->load();
            $this->processPage($page);
        }
    }

    public function processPage($page) {
        $fn = "process".$page->getType();
        $this->$fn($page);
    }
}

class CrawlPage {

    private $url = null;
    private $type = null;
    private $priority = null;

    private $loaded = false;

    private $page_doc;

    public function __construct($url, $type, $priority=0) {
        $this->url = $url;
        $this->type = $type;
        $this->priority = $priority;

        $this->page_doc = new DOMDocument;
    }

    public function getUrl() {
        return $this->url;
    }

    public function getType() {
        return $this->type;
    }

    public function getPriority() {
        return $this->priority;
    }

    public function load() {
        $req = new CurlRequest($this->url);
        $req->follow_location = true;
        $req->timeout = 2 /*seconds*/;

        $res = $req->exec();

        $content = $res->getContent();

        if ($content) {
            $this->page_doc->loadHTML($content);
        }
    }

    public function tags($tag) {
        $tags = $this->page_doc->getElementsByTagName($tag);
        $list = array();

        foreach ($tags as $tag) {
            if ($tag->nodeType == XML_ELEMENT_NODE) {
                $list[] = $tag;
            }
        }

        return new TagList($list);
    }
}
