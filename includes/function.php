<?php

// Format currency in Indonesian Rupiah
function formatCurrency($amount) {
    return number_format($amount, 0, ',', '.') . ' IDR';
}

// Generate unique ID with prefix
function generateId($prefix) {
    $timestamp = substr(time(), -6);
    $random = str_pad(mt_rand(0, 99), 2, '0', STR_PAD_LEFT);
    return $prefix . $timestamp . $random;
}

?>