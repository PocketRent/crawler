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


interface Queryable {
    public function children($tag);
    public function descendants($tag);
    public function cls($cls);
    public function id($id);
}

class TagList implements Queryable, IteratorAggregate {

    private $list = null;

    public function __construct($tags) {
        $this->list = $tags;
    }

    public function cls($cls) {
        $filtered = array_filter($this->list, function ($var) use ($cls) {
            $classes = explode(' ', $var->getAttribute('class'));
            return in_array($cls, $classes);
        });

        return new TagList(array_values($filtered));
    }

    public function id($id) {
        $filtered = array_filter($this->list, function ($var) use ($id) {
            return $var->getAttribute('id') == $id;
        });

        return new TagList(array_values($filtered));
    }

    public function children($tag) {
        $children = array();

        foreach ($this->list as $elem) {
            foreach ($elem->childNodes as $child) {
                if ($child->nodeType == XML_ELEMENT_NODE && $child->tagName == $tag) {
                    $children[] = $child;
                }
            }
        }

        return new TagList($children);
    }

    public function descendants($tag) {
        $descendants = array();
        foreach ($this->list as $elem) {
            $dec = $elem->getElementsByTagName($tag);
            foreach ($dec as $e) {
                if ($e->nodeType == XML_ELEMENT_NODE) {
                    $descendants[] = $e;
                }
            }
        }

        return new TagList($descendants);
    }

    public function first() {
        if (count($this->list) > 0) {
            $tag = $this->list[0];
        } else {
            $tag = null;
        }
        return new SingleTag($tag);
    }

    public function last() {
        if (count($this->list) > 0) {
            $tag = $this->list[count($this->list)-1];
        } else {
            $tag = null;
        }
        return new SingleTag($tag);
    }

    public function getIterator() {
        return new ArrayIterator($this->list);
    }
}

class SingleTag implements Queryable {
    private $tag = null;
    public function __construct($tag) {
        $this->tag = $tag;
    }

    public function cls($cls) {
        if ($this->tag) {
            $classes = explode(' ', $this->tag->getAttribute('class'));
            if (in_array($cls, $classes)) {
                return $this;
            }
        }

        return new SingleTag(null);
    }

    public function id($id) {
        if ($this->tag) {
            if ($this->tag->getAttribute('id') == $id) {
                return $this;
            }
        }

        return new SingleTag(null);
    }

    public function children($tag) {
        $children = array();

        if ($this->tag !== null) {
            foreach ($this->tag->childNodes as $child) {
                if ($child->nodeType == XML_ELEMENT_NODE && $child->tagName == $tag) {
                    $children[] = $child;
                }
            }
        }

        return new TagList($children);
    }

    public function descendants($tag) {
        $descendants = array();

        if ($this->tag !== null) {
            $dec = $this->tag->getElementsByTagName($tag);
            foreach ($dec as $e) {
                if ($e->nodeType == XML_ELEMENT_NODE) {
                    $descendants[] = $e;
                }
            }
        }

        return new TagList($descendants);
    }

    public function isNull() {
        return $this->tag === null;
    }

    public function getTag() {
        return $this->tag;
    }
}
