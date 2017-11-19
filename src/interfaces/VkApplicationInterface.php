<?php

namespace bongrun\interfaces;

interface VkApplicationInterface
{
    public function getClientId(): int;

    public function getClientSecret(): string;
}