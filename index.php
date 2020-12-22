<?php

use FFI\CData;

$ffi = FFI::load(__DIR__ . "/libneo4j.h");
$driverConfig = driverConfig($ffi, "bolt://localhost");
$driverHandle = neo4jHandle($ffi);
$driverHandle->cdata = 0;
$error = ampersand(neo4jError($ffi));

try {
    $result = $ffi->neo4j_driver_create(
        ampersand($driverConfig),
        ampersand($driverHandle),
        ampersand($error)
    );
    if (!$result) {
        $ffi->neo4j_err_free(ampersand($error));
        die('could not create driver');
    }

    if (!runTransaction($ffi, $driverHandle)) {
        die('could not run transaction');
    }

} finally {
    $ffi->neo4j_driver_destroy($driverHandle);
}

function runTransaction(FFI $ffi, CData $driverHandle): bool
{
    $transactionConfig = neo4jTransactionConfig($ffi);
    $error = ampersand(neo4jError($ffi));
    $transactionHandle = neo4jHandle($ffi);
    $transactionHandle->cdata = 0;

    $result = $ffi->neo4j_driver_tx(
        $driverHandle,
        ampersand($transactionConfig),
        ampersand($transactionHandle),
        null,
        ampersand($error)
    );
    if (!$result) {
        $ffi->neo4j_err_free(ampersand($error));
        return false;
    }

    $streamHandle = neo4jHandle($ffi);
    $parameters = $ffi->new("neo4j_param[1]");
    $parameters[0] = neo4jQueryParam($ffi, "param1", 42);
    $parameterCount = $ffi->new("int");
    $parameterCount->cdata = count($parameters);
    
    $result = $ffi->neo4j_tx_stream(
        $transactionHandle->cdata,
        cString($ffi, 'RETURN $param1 AS x'),
        $parameterCount,
        $parameters,
        ampersand($streamHandle),
        ampersand($error)
    );
    if (!$result) {
        $ffi->neo4j_err_free(ampersand($error));
        $ffi->neo4j_tx_rollback($transactionHandle, null);
        return false;
    }

    $value = neo4jValue($ffi);
    while ($ffi->neo4j_stream_next($streamHandle->cdata, ampersand($error))) {
        $index = $ffi->new("int");
        $index->cdata = 0;
        $result = $ffi->neo4j_stream_value(
            $streamHandle,
            $index,
            ampersand($value),
            ampersand($error)
        );
        if (!$result) {
            break;
        }

        var_dump($value);
    }

    $ffi->neo4j_value_free(ampersand($value));
    var_dump(ampersand($error));
    return true;
}

/**
 * @param FFI $ffi
 * @param $name
 * @param $value
 * @return CData
 */
function neo4jQueryParam(FFI $ffi, string $name, int $value): CData
{
    $param = $ffi->new("neo4j_param");
    $param->name = cString($ffi, $name);
    $param->typ = $ffi->NEO4J_INT64;
    $param->val = $value;
    return $param;
}


function driverConfig(FFI $ffi, $address): CData
{
    $driverConfig = $ffi->new("neo4j_driverconfig");
    $driverConfig->uri = cString($ffi, $address);
    return $driverConfig;
}

function neo4jHandle(FFI $ffi): CData
{
    return $ffi->new("neo4j_handle");
}

function neo4jError(FFI $ffi): CData
{
    return $ffi->new("neo4j_error");
}

function neo4jTransactionConfig(FFI $ffi): CData
{
    return $ffi->new('neo4j_txconfig');
}

function neo4jValue(FFI $ffi): CData
{
    return $ffi->new("neo4j_value");
}

function neo4jCommit(FFI $ffi): CData
{
    return $ffi->new('neo4j_commit');
}

function cString(FFI $ffi, string $str): CData
{
    $size = strlen($str);
    $capacity = $size + 1;
    $result = $ffi->new("char[$capacity]", 0);
    FFI::memcpy($result, $str, $size);
    return $result;
}

function ampersand(CData $resource): CData
{
    return FFI::addr($resource);
}
