<?php

declare(strict_types=1);

namespace Pest\ArchPresets;

/**
 * @internal
 */
final class Php extends AbstractPreset
{
    /**
     * Executes the arch preset.
     */
    public function execute(): void
    {
        $this->expectations[] = expect([
            'debug_zval_dump',
            'debug_backtrace',
            'debug_print_backtrace',
            'dump',
            'ray',
            'ds',
            'die',
            'goto',
            'global',
            'var_dump',
            'phpinfo',
            'echo',
            'ereg',
            'eregi',
            'mysql_connect',
            'mysql_pconnect',
            'mysql_query',
            'mysql_select_db',
            'mysql_fetch_array',
            'mysql_fetch_assoc',
            'mysql_fetch_object',
            'mysql_fetch_row',
            'mysql_num_rows',
            'mysql_affected_rows',
            'mysql_free_result',
            'mysql_insert_id',
            'mysql_error',
            'mysql_real_escape_string',
            'print',
            'print_r',
            'var_export',
            'xdebug_break',
            'xdebug_call_class',
            'xdebug_call_file',
            'xdebug_call_int',
            'xdebug_call_line',
            'xdebug_code_coverage_started',
            'xdebug_connect_to_client',
            'xdebug_debug_zval',
            'xdebug_debug_zval_stdout',
            'xdebug_dump_superglobals',
            'xdebug_get_code_coverage',
            'xdebug_get_collected_errors',
            'xdebug_get_function_count',
            'xdebug_get_function_stack',
            'xdebug_get_gc_run_count',
            'xdebug_get_gc_total_collected_roots',
            'xdebug_get_gcstats_filename',
            'xdebug_get_headers',
            'xdebug_get_monitored_functions',
            'xdebug_get_profiler_filename',
            'xdebug_get_stack_depth',
            'xdebug_get_tracefile_name',
            'xdebug_info',
            'xdebug_is_debugger_active',
            'xdebug_memory_usage',
            'xdebug_notify',
            'xdebug_peak_memory_usage',
            'xdebug_print_function_stack',
            'xdebug_set_filter',
            'xdebug_start_code_coverage',
            'xdebug_start_error_collection',
            'xdebug_start_function_monitor',
            'xdebug_start_gcstats',
            'xdebug_start_trace',
            'xdebug_stop_code_coverage',
            'xdebug_stop_error_collection',
            'xdebug_stop_function_monitor',
            'xdebug_stop_gcstats',
            'xdebug_stop_trace',
            'xdebug_time_index',
            'xdebug_var_dump',
            'trap',
        ])->not->toBeUsed();
    }
}
