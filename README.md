# PHP experimental Bolt connector

This is just a _disposable_ **experiment**.

The implementation is based on the generated C bindings from a forked [Neo4j Go Driver](https://github.com/2hdddg/neo4j-go-driver/tree/cspike).

## Prereqs

 1. Install PHP 8+
 1. Install [Composer](https://getcomposer.org/download/)
 1. If not on Mac OS, generate the `.so` file based on the instructions of the aforementioned forked Go driver 
 1. Change [the `FFI_LIB` line](./libneo4j.h) to point to your local shared library path
 1. Make sure to start a local Neo4j instance with the configured `"neo4j"`/`"pass"` credentials

## Run

As simple as:
```shell
./run.sh
```

Or:
```shell
composer run main
```