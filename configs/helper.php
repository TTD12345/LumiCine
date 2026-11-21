<?php

if (!function_exists('lumicineUtf8Length')) {
    function lumicineUtf8Length(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        $length = preg_match_all('/./us', $value, $matches);
        return $length === false ? 0 : $length;
    }
}

if (!function_exists('lumicineUtf8Substring')) {
    function lumicineUtf8Substring(string $value, int $start, ?int $length = null): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, $start, $length, 'UTF-8');
        }

        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            return '';
        }

        $substring = array_slice($characters, $start, $length);
        return implode('', $substring);
    }
}

if (!function_exists('debug')) {
    function debug($data)
    {
        echo '<pre>';
        print_r($data);
        die;
    }
}

if (!function_exists('upload_file')) {
    function upload_file($folder, $file)
    {
        $targetFile = $folder . '/' . time() . '-' . $file["name"];

        if (move_uploaded_file($file["tmp_name"], PATH_ASSETS_UPLOADS . $targetFile)) {
            return $targetFile;
        }

        throw new Exception('Upload file không thành công!');
    }
}