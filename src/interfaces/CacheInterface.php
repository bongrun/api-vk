<?php

namespace bongrun\interfaces;

interface CacheInterface
{
    public function get($key);

    public function save($key, $data, $ttl): string;
}