<?php

namespace Http\Discovery\Strategy;

class GeneratedDiscoveryStrategy implements DiscoveryStrategy
{
    public static function getCandidates($type)
    {
        switch ($type) {
            case 'Http\\Client\\HttpClient': return [['class' => '']];
            case 'Psr\\Http\\Client\\ClientInterface': return [['class' => 'GautamMKGarg\\PsrForWordPress\\Http\\Psr18Client']];

            default: return [];
        }
    }
}
