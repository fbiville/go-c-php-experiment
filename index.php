<?php

use FFI\CData;


main();

function main() {
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

        $results = runTransaction(
            $ffi,
            $driverHandle,
            'RETURN $param1 AS x',
            new QueryParam("param1", 42));

        if ($results === null) {
            die('could not run transaction');
        }
        echo 'Printing results: ';
        var_dump($results);

    } finally {
        $ffi->neo4j_driver_destroy($driverHandle->cdata);
    }
}

class QueryParam {
    private string $name;
    // TODO: evolve C lib to support more data types
    private int $value;

    public function __construct(string $name, int $value)
    {
        $this->name = $name;
        $this->value = $value;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): int
    {
        return $this->value;
    }


}

function runTransaction(FFI $ffi, CData $driverHandle, string $cypher, QueryParam ...$params): ?array
{
    $transactionConfig = neo4jTransactionConfig($ffi);
    $error = ampersand(neo4jError($ffi));
    $transactionHandle = neo4jHandle($ffi);
    $transactionHandle->cdata = 0;

    $result = $ffi->neo4j_driver_tx(
        $driverHandle->cdata,
        ampersand($transactionConfig),
        ampersand($transactionHandle),
        null,
        ampersand($error)
    );
    if (!$result) {
        $ffi->neo4j_err_free(ampersand($error));
        return null;
    }

    $streamHandle = neo4jHandle($ffi);
    // TODO: handle 0 param case
    $paramCount = count($params);
    $parameters = $ffi->new("neo4j_param[". $paramCount ."]");
    foreach ($params as $i => $param) {
        $parameters[$i] = neo4jQueryParam($ffi, $param->getName(), $param->getValue());
    }
    $parameterCount = $ffi->new("int");
    $parameterCount->cdata = $paramCount;
    
    $result = $ffi->neo4j_tx_stream(
        $transactionHandle->cdata,
        cString($ffi, $cypher),
        $parameterCount->cdata,
        $parameters,
        ampersand($streamHandle),
        ampersand($error)
    );
    if (!$result) {
        $ffi->neo4j_err_free(ampersand($error));
        $ffi->neo4j_tx_rollback($transactionHandle, null);
        return null;
    }

    $results = array();
    $value = neo4jValue($ffi);
    while ($ffi->neo4j_stream_next($streamHandle->cdata, ampersand($error))) {
        $index = $ffi->new("int");
        $index->cdata = 0;
        $result = $ffi->neo4j_stream_value(
            $streamHandle->cdata,
            $index->cdata,
            ampersand($value),
            ampersand($error)
        );
        if (!$result) {
            break;
        }
        array_push($results, $value->val);
    }

    $ffi->neo4j_value_free(ampersand($value));
    return $results;
}


function neo4jQueryParam(FFI $ffi, string $name, int $value): CData
{
    $param = $ffi->new("neo4j_param");
    $param->name = cString($ffi, $name);
    // TODO: evolve C lib to support more data types
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
