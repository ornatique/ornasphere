<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Private local diagnostics: never accepts exception messages, traces or request payloads. */
class CompanyArchiveLog
{
    private static array $stack = [];

    public static function run(string $operation, int $companyId, callable $action, ?string $reference = null, ?string $archiveId = null): mixed
    {
        $key = count(self::$stack); $now = hrtime(true);
        $reference = $reference && Str::isUuid($reference) ? $reference : (string)Str::uuid();
        self::$stack[$key] = ['reference'=>$reference,'archive_id'=>$archiveId && Str::isUuid($archiveId) ? $archiveId : null,'company_id'=>$companyId,'operation'=>$operation,
            'stage'=>'starting','started'=>$now,'last_step'=>$now];
        $armed = true; $reserve = str_repeat('x',256 * 1024);
        register_shutdown_function(static function () use ($key,&$armed,&$reserve) {
            if (!$armed || !isset(self::$stack[$key])) return;
            $reserve = null; $error = error_get_last();
            if (!$error || !in_array($error['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR],true)) {
                if (connection_aborted()) self::write($key,'operation_interrupted',['error_code'=>'CLIENT_DISCONNECTED'],'warning');
                return;
            }
            $message = $error['message'] ?? '';
            $code = str_contains($message,'Allowed memory size') ? 'MEMORY_LIMIT' : (str_contains($message,'Maximum execution time') ? 'TIME_LIMIT' : 'PHP_FATAL');
            self::write($key,'operation_fatal',['error_code'=>$code],'error');
        });
        self::write($key,'operation_started',['php_version'=>PHP_VERSION,'memory_limit'=>(string)ini_get('memory_limit'),
            'max_execution_time'=>(int)ini_get('max_execution_time')]);
        try {
            $result = $action();
            if ($result instanceof StreamedResponse) {
                $callback = $result->getCallback(); $scope = self::$stack[$key];
                $result->setCallback(static function () use ($callback,$scope) {
                    return self::run($scope['operation'].'_stream',$scope['company_id'],static function () use ($callback) {
                        self::step('stream_started'); $value = $callback();
                        self::step(connection_aborted() ? 'stream_disconnected' : 'server_stream_finished');
                        return $value;
                    },$scope['reference'],$scope['archive_id']);
                });
                // A prepared HTTP response is not yet a completed download.
                self::write($key,'stream_ready');
                return $result;
            }
            self::write($key,connection_aborted() ? 'operation_interrupted' : 'operation_completed',
                connection_aborted() ? ['error_code'=>'CLIENT_DISCONNECTED'] : [],connection_aborted() ? 'warning' : 'info');
            return $result;
        } catch (\Throwable $e) {
            self::write($key,$e instanceof ValidationException ? 'operation_rejected' : 'operation_failed',
                ['exception_type'=>get_class($e),'error_code'=>self::errorCode($e),'error_file'=>basename($e->getFile()),'error_line'=>$e->getLine()],
                $e instanceof ValidationException ? 'warning' : 'error');
            throw $e;
        } finally { $armed = false; $reserve = null; unset(self::$stack[$key]); }
    }

    public static function reference(): ?string
    {
        $key = array_key_last(self::$stack);
        return $key === null ? null : self::$stack[$key]['reference'];
    }

    public static function archive(?string $id): void
    {
        $key = array_key_last(self::$stack);
        if ($key !== null && $id && Str::isUuid($id)) self::$stack[$key]['archive_id'] = $id;
    }

    private static function errorCode(\Throwable $error): string
    {
        if ($error instanceof ValidationException) return 'VALIDATION_REJECTED';
        if ($error instanceof \Illuminate\Database\QueryException) return 'DATABASE_ERROR';
        // Inspect only to select a fixed safe code; never persist the original message.
        $message = strtolower($error->getMessage());
        if (str_contains($message,'permission denied') || str_contains($message,'not writable')) return 'STORAGE_PERMISSION';
        if (str_contains($message,'no space left') || str_contains($message,'disk full')) return 'STORAGE_FULL';
        if (str_contains($message,'allowed memory size')) return 'MEMORY_LIMIT';
        if (str_contains($message,'maximum execution time')) return 'TIME_LIMIT';
        if (str_contains($message,'ziparchive') || str_contains($message,'temporary file')) return 'ZIP_OR_TEMPORARY_FILE';
        return 'UNEXPECTED_ERROR';
    }

    public static function step(string $stage, array $metadata = []): void
    {
        $key = array_key_last(self::$stack); if ($key === null) return;
        $now = hrtime(true); $previous = self::$stack[$key]['stage'];
        $duration = round(($now-self::$stack[$key]['last_step'])/1e6,2);
        self::$stack[$key]['stage'] = $stage; self::$stack[$key]['last_step'] = $now;
        // Whitelist prevents accidental logging of a future caller's credentials, rows or file URLs.
        $metadata = array_intersect_key($metadata,array_flip(['table','file_index','total_files','rows','bytes','error_code','storage_driver']));
        self::write($key,'step',['previous_stage'=>$previous,'previous_step_ms'=>$duration,...$metadata]);
    }

    private static function write(int $key, string $event, array $metadata = [], string $level = 'info'): void
    {
        $scope = self::$stack[$key] ?? null; if (!$scope) return;
        $context = array_intersect_key($scope,array_flip(['reference','archive_id','company_id','operation','stage']));
        $context += ['elapsed_ms'=>round((hrtime(true)-$scope['started'])/1e6,2),'memory_bytes'=>memory_get_usage(true),'peak_memory_bytes'=>memory_get_peak_usage(true)];
        try {
            // Independent of LOG_CHANNEL/LOG_LEVEL; never forwards to Slack or other remote channels.
            Log::build(['driver'=>'daily','path'=>storage_path('logs/company-archive.log'),'days'=>14,'level'=>'debug',
                'permission'=>0600,'locking'=>true])->log($level,'company_archive.'.$event,[...$context,...$metadata]);
        } catch (\Throwable) {
            // Logging failure must not interrupt an archive or roll back a successful restore.
            try { @error_log('company_archive.log_unavailable '.json_encode(['event'=>$event,...$context,...$metadata])); } catch (\Throwable) {}
        }
    }
}
