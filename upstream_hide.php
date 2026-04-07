<?php

/**
 * ZJMF-noupstream 上游信息隐藏补丁
 *
 * 本文件是一个补丁(Patch)，不是完整的软件。
 * 本补丁基于魔方财务系统(ZJMF)运行，不包含魔方财务系统的任何源代码。
 * 魔方财务系统版权归顺戴网络科技有限公司所有。
 *
 * 魔方财务系统软件使用协议（来自于魔方财务安装时的协议文件）
 * 版权所有 © 2019-2021, 财务系统开源社区
 *
 * 感谢您选择财务系统内容管理框架, 希望我们的产品能够帮您把网站发展的更快、更好、更强！
 * 财务系统遵循Apache License 2.0开源协议发布，并提供免费使用。
 * 财务系统建站系统由顺戴网络科技有限公司(以下简称顺戴网络，官网 https://www.idcsmart.com)发起并开源发布。
 *
 * 顺戴网络包含以下网站：
 * 顺戴网络官网：https://www.idcsmart.com
 *
 * 财务系统免责声明
 * 1、使用财务系统构建的网站的任何信息内容以及导致的任何版权纠纷和法律争议及后果，财务系统官方不承担任何责任。
 * 2、您一旦安装使用财务系统，即被视为完全理解并接受本协议的各项条款，在享有上述条款授予的权力的同时，受到相关的约束和限制。
 */

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
