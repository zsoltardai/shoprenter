<?php

function base64url_encode($data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}


function xml_encode(string $objectName, array $data) : string
{
    $xml ='<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= "<$objectName>";
    foreach ($data as $key => $value)
    {
        $xml .="<$key>$value</$key>";
    }
    $xml .= "</$objectName>";
    return $xml;
}

function encrypt($text, $key) : string
{
    $cipher = 'aes-256-cbc';
    $ivLength = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivLength);

    $encrypted = openssl_encrypt($text, $cipher, $key, 0, $iv);

    return $iv.$encrypted;
}

function decrypt($text, $key) : string
{
    $cipher = 'aes-256-cbc';
    $ivLength = openssl_cipher_iv_length($cipher);

    $iv = substr($text, 0, $ivLength);
    $text = substr($text, $ivLength, strlen($text));

    return openssl_decrypt($text, $cipher, $key, 0, $iv);
}