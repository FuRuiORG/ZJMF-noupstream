<?php

if (!defined('CMF_ROOT')) {
    return;
}

@ini_set('zlib.output_compression', 'Off');
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}

$upstreamHideWhitelist = [];

$dbConfigPath = CMF_ROOT . 'app/config/database.php';
if (file_exists($dbConfigPath)) {
    $dbConfig = include $dbConfigPath;
    if (is_array($dbConfig) && !empty($dbConfig['admin_application'])) {
        $upstreamHideWhitelist[] = '/' . $dbConfig['admin_application'] . '/';
        $upstreamHideWhitelist[] = '/' . $dbConfig['admin_application'];
    }
}

$upstreamHideFields = [
    'api_type',
    'upstream_product_shopping_url',
    'upstream_pid',
    'upstream_version',
    'upstream_price_type',
    'upstream_price_value',
    'upstream_qty',
    'upstream_stock_control',
    'upstream_ontrial_status',
    'upstream_price',
    'upstream_cycle',
    'zjmf_api_id',
    'upstream_auto_setup',
    'location_version',
];

$upstreamReplaceValues = [
    'api_type'                => 'normal',
    'zjmf_api_id'             => 0,
    'upstream_pid'            => 0,
    'upstream_version'        => 0,
    'upstream_auto_setup'     => '',
    'upstream_ontrial_status' => 0,
    'upstream_stock_control'  => 0,
    'upstream_qty'            => 0,
    'upstream_price'          => '0.00',
    'upstream_cycle'          => '',
    'upstream_price_type'     => null,
    'upstream_price_value'    => null,
    'location_version'        => 0,
];

function upstreamHideCleanArray(&$data, $hideFields, $replaceValues)
{
    if (!is_array($data)) {
        return;
    }
    foreach ($data as $key => &$value) {
        if (is_array($value)) {
            upstreamHideCleanArray($value, $hideFields, $replaceValues);
        }
        $strKey = (string)$key;
        if (in_array($strKey, $hideFields, true)) {
            if ($strKey === 'upstream_product_shopping_url') {
                $data[$key] = null;
            } elseif (isset($replaceValues[$strKey])) {
                $data[$key] = $replaceValues[$strKey];
            } else {
                unset($data[$key]);
            }
        }
        if ($strKey === 'upstream_id' && is_numeric($value) && $value != 0) {
            $data[$key] = 0;
        }
        if ($strKey === 'upper_reaches_id' && is_numeric($value) && $value != 0) {
            $data[$key] = 0;
        }
    }
    unset($value);
}

function upstreamHideTryDecompress($buffer)
{
    if (strlen($buffer) < 2) {
        return $buffer;
    }
    $b0 = ord($buffer[0]);
    $b1 = isset($buffer[1]) ? ord($buffer[1]) : 0;
    if ($b0 === 0x1f && $b1 === 0x8b) {
        $decoded = @gzdecode($buffer);
        if ($decoded !== false) {
            return $decoded;
        }
    }
    if ($b0 === 0x78 && ($b1 === 0x01 || $b1 === 0x5e || $b1 === 0x9c)) {
        $decoded = @gzuncompress($buffer);
        if ($decoded !== false) {
            return $decoded;
        }
    }
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        if (function_exists('inflate_init') && function_exists('inflate_add')) {
            $context = @inflate_init(ZLIB_ENCODING_DEFLATE);
            if ($context !== false) {
                $decoded = @inflate_add($context, $buffer);
                if ($decoded !== false) {
                    return $decoded;
                }
            }
        }
    } else {
        $decoded = @gzinflate($buffer);
        if ($decoded !== false) {
            return $decoded;
        }
    }
    return $buffer;
}

function upstreamHideFilter($buffer)
{
    global $upstreamHideFields, $upstreamReplaceValues, $upstreamHideWhitelist;

    if (!empty($upstreamHideWhitelist)) {
        $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        if (!empty($requestUri)) {
            foreach ($upstreamHideWhitelist as $whitelistPath) {
                if (stripos($requestUri, $whitelistPath) !== false) {
                    return $buffer;
                }
            }
        }
    }

    if (empty($buffer)) {
        return $buffer;
    }
    $rawBuffer = $buffer;
    $trimmed = trim($buffer);
    if (strlen($trimmed) < 2) {
        return $buffer;
    }
    $firstChar = $trimmed[0];
    if ($firstChar !== '{' && $firstChar !== '[') {
        $buffer = upstreamHideTryDecompress($rawBuffer);
        $trimmed = trim($buffer);
        if (strlen($trimmed) < 2 || ($trimmed[0] !== '{' && $trimmed[0] !== '[')) {
            if (stripos($rawBuffer, 'application/json') === false &&
                stripos($rawBuffer, 'text/json') === false &&
                stripos($rawBuffer, 'text/javascript') === false) {
                return $rawBuffer;
            }
            return $rawBuffer;
        }
    }
    $json = json_decode($trimmed, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $rawBuffer;
    }
    if (!is_array($json)) {
        return $rawBuffer;
    }
    upstreamHideCleanArray($json, $upstreamHideFields, $upstreamReplaceValues);
    $newBuffer = json_encode(
        $json,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
    );
    if ($newBuffer !== false) {
        return $newBuffer;
    }
    return $rawBuffer;
}

ob_start('upstreamHideFilter');
