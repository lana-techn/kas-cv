<?php

/**
 * Format angka menjadi format mata uang Rupiah (IDR).
 * Fungsi ini sudah diperbarui untuk menangani input yang mungkin sudah terformat,
 * sehingga mencegah error "double formatting".
 *
 * @param mixed $num Angka yang akan diformat.
 * @return string Angka dalam format mata uang (e.g., "Rp 100.000").
 */
function formatCurrency($num)
{
    // 1. Cek jika input valid. Jika tidak, kembalikan nilai default.
    if (!is_numeric($num) && !is_string($num)) {
        return 'Rp 0';
    }

    // 2. Bersihkan input: Hapus semua karakter kecuali angka.
    // Ini akan mengubah "Rp 100.000" menjadi "100000".
    $cleaned_num = preg_replace('/[^0-9]/', '', $num);

    // 3. Konversi string angka yang sudah bersih menjadi float/integer.
    $numeric_val = (float) $cleaned_num;

    // 4. Format angka yang sudah bersih menggunakan number_format().
    return 'Rp ' . number_format($numeric_val, 0, ',', '.');
}

/**
 * Generate ID unik dengan prefix.
 *
 * @param string $prefix Prefix untuk ID (e.g., 'BL', 'JUL').
 * @return string ID unik yang dihasilkan.
 */
function generateId($prefix)
{
    // Pastikan prefix adalah string
    $prefix = (string) $prefix;
    // Ambil 6 digit terakhir dari timestamp untuk variasi
    $timestamp = substr(time(), -6);
    // Hasilkan angka acak 2 digit
    $random = str_pad(mt_rand(0, 99), 2, '0', STR_PAD_LEFT);

    return strtoupper($prefix) . $timestamp . $random;
}
