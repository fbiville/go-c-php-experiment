#define FFI_LIB "/Users/fbiville/workspace/go-c-php-experiment/libneo4j.dylib"

typedef struct {
	const char *desc;
} neo4j_error;

typedef struct {
	const char *uri; // TODO: auth info
} neo4j_driverconfig;

typedef struct {
	const char *bookmark;
	uint32_t   timeout_ms;
	bool       retry;
} neo4j_txconfig;

typedef struct {
	uint8_t    typ;
	int64_t    val; // bool, ints, floats
	void*      ref; // map, array, node, path, relationship, temporal, spatial
} neo4j_value;

typedef struct {
	const char  *name;
	uint8_t     typ;
	int64_t     val; // bool, ints, floats
	void*       ref; // map, array, node, path, relationship, temporal, spatial
} neo4j_param;

typedef struct {
	int         num;
	neo4j_param *params;
} neo4j_command;

typedef struct {
	const char *bookmark;
} neo4j_commit;

typedef uint32_t neo4j_handle;

typedef struct {
	int         num;
	char        **keys;
	neo4j_value *values;
} neo4j_map;

typedef struct {
	int         num;
	neo4j_value *values;
} neo4j_array;

typedef enum {
	NEO4J_UNDEF,
	NEO4J_NULL,
	NEO4J_INT64,
} cyphertypes;

extern int neo4j_driver_create(neo4j_driverconfig* config, neo4j_handle* handle_out, neo4j_error** err_out);
extern void neo4j_driver_destroy(neo4j_handle driver_handle);

//
// Requests a new transaction on the driver instance.
//
extern int neo4j_driver_tx(neo4j_handle driver_handle, neo4j_txconfig* config, neo4j_handle* tx_handle_out, neo4j_handle* retry_handle_inout, neo4j_error** err_out);
extern int neo4j_tx_commit(neo4j_handle tx_handle, neo4j_commit* commit_out, neo4j_error** err_out);
extern int neo4j_tx_rollback(neo4j_handle tx_handle, neo4j_error** err_out);
extern int neo4j_tx_stream(neo4j_handle tx_handle, char* cypher, int num_params, neo4j_param* cparams, neo4j_handle* handle_out, neo4j_error** err_out);
extern int neo4j_stream_next(neo4j_handle stream_handle, neo4j_error** err_out);

// Values can be resued between calls without freeing them, if the previous value is compatible with
// the new value, memory can be reused without allocation (reallocation can occur).
extern int neo4j_stream_value(neo4j_handle stream_handle, int cindex, neo4j_value* value_out, neo4j_error** err_out);
extern void neo4j_retry_free(neo4j_handle retry_handle);
extern int neo4j_retry(neo4j_handle retry_handle);
extern void neo4j_err_free(neo4j_error** err);
extern void neo4j_value_free(neo4j_value* value);