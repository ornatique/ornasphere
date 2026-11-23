<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CompanyArchiveInventory
{
    private array $columns = [];
    private array $fileOrigins = [];
    private ?array $metadata = null;
    private ?array $foreignKeyMetadata = null;
    private array $indexMetadata = [];
    private array $legacyForeignKeyMetadata = [];
    private array $referenceMetadata = [];

    public function refreshMetadata(): void
    {
        $this->columns = $this->fileOrigins = $this->indexMetadata = $this->legacyForeignKeyMetadata = $this->referenceMetadata = [];
        $this->metadata = $this->foreignKeyMetadata = null;
    }

    public function tables(): array
    {
        return array_values(array_map(fn ($t) => $t['name'], $this->tableMetadata()));
    }

    private function tableMetadata(): array
    {
        // Laravel's MySQL introspection can list every accessible schema unless explicitly scoped.
        return $this->metadata ??= in_array(DB::getDriverName(),['mysql','mariadb'],true) ? Schema::getTables(DB::getDatabaseName()) : Schema::getTables();
    }

    public function hasTable(string $table): bool
    {
        return in_array($table,$this->tables(),true);
    }

    public function foreignKeys(string $table, bool $legacy = false): array
    {
        if ($legacy || !in_array(DB::getDriverName(),['mysql','mariadb'],true)) {
            return $this->legacyForeignKeyMetadata[$table] ??= Schema::getForeignKeys($table);
        }
        if ($this->foreignKeyMetadata === null) {
            CompanyArchiveLog::step('reading_database_foreign_keys');
            // One read of this database only, instead of the expensive shared-host join per table.
            $rows = DB::connection()->selectFromWriteConnection(
                'select kc.table_name as local_table, kc.constraint_name as name, '
                .'group_concat(kc.column_name order by kc.ordinal_position) as `columns`, '
                .'kc.referenced_table_schema as foreign_schema, kc.referenced_table_name as foreign_table, '
                .'group_concat(kc.referenced_column_name order by kc.ordinal_position) as foreign_columns, '
                .'rc.update_rule as on_update, rc.delete_rule as on_delete '
                .'from information_schema.key_column_usage kc join information_schema.referential_constraints rc '
                .'on kc.constraint_schema = rc.constraint_schema and kc.constraint_name = rc.constraint_name and kc.table_name = rc.table_name '
                .'where kc.table_schema = ? and kc.referenced_table_name is not null '
                .'group by kc.table_name, kc.constraint_name, kc.referenced_table_schema, kc.referenced_table_name, rc.update_rule, rc.delete_rule '
                .'order by kc.table_name, kc.constraint_name, kc.referenced_table_schema, kc.referenced_table_name, rc.update_rule, rc.delete_rule',
                [DB::getDatabaseName()]
            );
            $groups = [];
            foreach ($rows as $row) $groups[$row->local_table][] = $row;
            $this->foreignKeyMetadata = array_fill_keys($this->tables(),[]);
            foreach ($groups as $name=>$keys) $this->foreignKeyMetadata[$name] = DB::connection()->getPostProcessor()->processForeignKeys($keys);
            CompanyArchiveLog::step('database_foreign_keys_loaded',['rows'=>count($rows)]);
        }
        return $this->foreignKeyMetadata[$table] ?? [];
    }

    private function indexes(string $table): array
    {
        return $this->indexMetadata[$table] ??= Schema::getIndexes($table);
    }

    public function columns(string $table): array
    {
        return $this->columns[$table] ??= Schema::getColumnListing($table);
    }

    public function coverage(): array
    {
        $tables = $this->tables();
        // Runtime-only controls are excluded without changing the signed archive policy.
        $known = array_merge(config('company_archive.business'), config('company_archive.retained'), config('company_archive.system'),[CompanyArchiveRequestLeases::TABLE]);
        $unknown = array_values(array_diff($tables, $known));
        CompanyArchiveGate::check(!$unknown, 'Unreviewed database tables: '.implode(', ', $unknown).'. Add an ownership/file policy before archiving.');
        foreach (array_intersect($tables, config('company_archive.business')) as $table) {
            CompanyArchiveLog::step('reviewing_table_policy',['table'=>$table]);
            CompanyArchiveGate::check(in_array('company_id', $this->columns($table), true) || isset(config('company_archive.parents')[$table]),
                'No company ownership rule for '.$table.'.');
            $reviewed = array_column(config('company_archive.files')[$table] ?? [], 0);
            $reviewed = array_merge($reviewed, match ($table) {
                'items' => ['glb_url', 'usdz_url', 'thumbnail_url'],
                'marketing_qrs' => ['web_url', 'android_url', 'ios_url'],
                'worker_access_logs' => ['path'], default => [],
            });
            foreach ($this->columns($table) as $column) {
                if (preg_match('/(?:_path|_image|_file|_url|attachments)$|^path$/', $column)) {
                    CompanyArchiveGate::check(in_array($column, $reviewed, true), 'Unreviewed file/reference column: '.$table.'.'.$column);
                }
            }
        }
        return $tables;
    }

    public function assertTransactional(int|array $companyId): void
    {
        if (DB::getDriverName() !== 'mysql') return;
        foreach ($this->tableMetadata() as $table) if (in_array($table['name'], config('company_archive.business'), true)) {
            CompanyArchiveGate::check(strtolower($table['engine'] ?? '') === 'innodb' || !$this->query($table['name'], $companyId)->exists(),
                'This company has data in non-transactional table '.$table['name'].'. A reviewed InnoDB conversion is required before moving its data.');
        }
    }

    public function query(string $table, int|array $companyId)
    {
        $q = DB::table($table);
        if ($table === 'companies') return $q->whereIn('id', (array)$companyId);
        if (in_array('company_id', $this->columns($table), true)) return $q->whereIn('company_id', (array)$companyId);
        if ($table === 'roles') {
            return $q->whereIn('id', $this->query('model_has_roles', $companyId)->select('role_id'));
        }
        if ($table === 'permissions') {
            return $q->where(function ($scope) use ($companyId) {
                $scope->whereRaw('1 = 0');
                if ($this->hasTable('role_has_permissions')) $scope->orWhereIn('id', $this->query('role_has_permissions', $companyId)->select('permission_id'));
                if ($this->hasTable('model_has_permissions')) $scope->orWhereIn('id', $this->query('model_has_permissions', $companyId)->select('permission_id'));
            });
        }
        $special = [
            'model_has_roles' => ['model_id', 'users'], 'model_has_permissions' => ['model_id', 'users'],
            'role_has_permissions' => ['role_id', 'roles'],
        ];
        [$column, $parent] = config('company_archive.parents')[$table] ?? $special[$table] ?? [null, null];
        CompanyArchiveGate::check($column && $parent, 'No ownership rule for '.$table.'.');
        if (!in_array($column, $this->columns($table), true)) {
            if (in_array($table, config('company_archive.shared_without_owner', []), true)) return $q->whereRaw('1 = 0');
            CompanyArchiveGate::check(!DB::table($table)->exists(), 'Legacy table '.$table.' has records without a company ownership column. Review ownership before archiving.');
            return $q->whereRaw('1 = 0');
        }
        if ($table === 'vacuum_voucher_items' && $parent === 'vacuum_vouchers'
            && in_array('vacuum_buch_id', $this->columns($table), true) && $this->hasTable('vacuum_buchs')) {
            // Legacy voucher deletion left some items behind. Keep the original missing
            // voucher ID; an explicitly owned buch can scope these orphan rows without
            // assigning them to a different existing voucher or changing business data.
            return $q->where(function ($scope) use ($column, $parent, $companyId) {
                $scope->whereIn($column, $this->query($parent, $companyId)->select('id'))
                    ->orWhere(function ($orphan) use ($column, $parent, $companyId) {
                        $orphan->whereNotNull($column)
                            ->whereNotIn($column, DB::table($parent)->select('id'))
                            ->whereIn('vacuum_buch_id', $this->query('vacuum_buchs', $companyId)->select('id'));
                    });
            });
        }
        $q->whereIn($column, $this->query($parent, $companyId)->select('id'));
        if (in_array($table, ['model_has_roles', 'model_has_permissions'], true)) $q->where('model_type', \App\Models\User::class);
        return $q;
    }

    public function schema(bool $legacyForeignKeys = false): array
    {
        $result = [];
        $engines = [];
        foreach ($this->tableMetadata() as $metadata) $engines[$metadata['name']] = $metadata['engine'] ?? null;
        foreach ($this->coverage() as $table) {
            if ($table === CompanyArchiveRequestLeases::TABLE || in_array($table, config('company_archive.system'), true)) continue;
            CompanyArchiveLog::step('reading_table_schema',['table'=>$table]);
            $columns = Schema::getColumns($table);
            $result[$table] = hash('sha256', json_encode([$columns, $this->foreignKeys($table,$legacyForeignKeys), $this->indexes($table), $engines[$table] ?? null], JSON_THROW_ON_ERROR));
        }
        ksort($result);
        return $result;
    }

    public function order(): array
    {
        $pending = array_values(array_intersect(config('company_archive.business'), $this->tables()));
        $dependencies = [];
        foreach ($pending as $table) {
            CompanyArchiveLog::step('ordering_table_dependencies',['table'=>$table]);
            $parents = array_column($this->references($table),'foreign_table');
            if (isset(config('company_archive.parents')[$table])) $parents[] = config('company_archive.parents')[$table][1];
            $dependencies[$table] = array_diff($parents,[$table]);
        }
        $order = [];
        while ($pending) {
            $moved = false;
            foreach ($pending as $key => $table) {
                if (array_intersect($dependencies[$table], $pending)) continue;
                $order[] = $table; unset($pending[$key]); $moved = true;
            }
            CompanyArchiveGate::check($moved, 'Cyclic table dependencies need a reviewed restore rule: '.implode(', ', $pending));
        }
        return $order;
    }

    public function ordered(string $table, int|array $companyId)
    {
        $query = $this->query($table, $companyId);
        $indexes = $this->indexes($table);
        $primary = collect($indexes)->firstWhere('primary', true);
        $keys = $primary['columns'] ?? $this->columns($table);
        foreach ($keys as $column) $query->orderBy($column);
        return $query;
    }

    public static function encodeRow(object|array $row): array
    {
        $out = [];
        foreach ((array) $row as $key => $value) $out[$key] = $value === null ? null : base64_encode((string) $value);
        return $out;
    }

    public static function decodeRow(array $row): array
    {
        $out = [];
        foreach ($row as $key => $value) {
            $decoded = $value === null ? null : base64_decode($value, true);
            CompanyArchiveGate::check($decoded !== false, 'Invalid row encoding.'); $out[$key] = $decoded;
        }
        return $out;
    }

    public function digest(string $table, int|array $companyId): array
    {
        $hash = hash_init('sha256'); $count = 0;
        foreach ($this->ordered($table, $companyId)->cursor() as $row) {
            hash_update($hash, json_encode(self::encodeRow($row), JSON_THROW_ON_ERROR)."\n"); $count++;
        }
        return ['count' => $count, 'sha256' => hash_final($hash)];
    }

    public function assertNoForeignDependants(int|array $id, array $order): void
    {
        foreach ($this->coverage() as $child) {
            CompanyArchiveLog::step('checking_table_dependencies',['table'=>$child]);
            foreach ($this->references($child) as $fk) {
            $parent = $fk['foreign_table'];
            if (!in_array($parent, $order, true)) continue;
            CompanyArchiveGate::check(count($fk['columns']) === 1 && count($fk['foreign_columns']) === 1, 'Composite foreign key needs review: '.$child);
            if (in_array($child, $order, true)) {
                $outside = $this->query($child, $id)->whereNotNull($fk['columns'][0])
                    ->whereNotIn($fk['columns'][0], $this->query($parent, $id)->select($fk['foreign_columns'][0]));
                // Legacy logical links can already be orphaned. Preserve them verbatim, but never
                // package a live cross-tenant dependency. Declared FK orphans cannot restore safely.
                if (!($fk['declared'] ?? false)) $outside->whereIn($fk['columns'][0], DB::table($parent)->select($fk['foreign_columns'][0]));
                CompanyArchiveGate::check(!$outside->exists(), 'Company records depend on another company or missing required business record: '.$child.'.'.$fk['columns'][0].'. Review ownership before archiving.');
            }
            $linked = DB::table($child)->whereIn($fk['columns'][0], $this->query($parent, $id)->select($fk['foreign_columns'][0]));
            // Compare the actual linked subset, not the entire child table.
            if (in_array($child, $order, true)) {
                $mine = $this->query($child, $id)->whereIn($fk['columns'][0], $this->query($parent, $id)->select($fk['foreign_columns'][0]))->count();
            } else $mine = 0;
            CompanyArchiveGate::check($linked->count() === $mine, 'Other-company or retained records reference '.$parent.'. Removal is blocked.');
            }
        }
    }

    /** Copy-only diagnostics: record link problems, never repair or import other tenants. */
    public function dependencyWarnings(array $ids, array $order): array
    {
        $warnings=[]; $seen=[];
        foreach ($this->coverage() as $child) foreach ($this->references($child) as $fk) {
            $parent=$fk['foreign_table'];
            if (!in_array($parent,$order,true) || count($fk['columns'])!==1 || count($fk['foreign_columns'])!==1) continue;
            $column=$fk['columns'][0];$parentColumn=$fk['foreign_columns'][0];$key=$child.'.'.$column.'->'.$parent.'.'.$parentColumn;
            if (isset($seen[$key])) continue;$seen[$key]=true;
            if (in_array($child,$order,true)) {
                $outside=$this->query($child,$ids)->whereNotNull($column)->whereNotIn($column,$this->query($parent,$ids)->select($parentColumn));
                $count=(clone $outside)->count();
                if ($count) $warnings[]=['type'=>'outside_or_missing_parent','table'=>$child,'column'=>$column,'parent_table'=>$parent,
                    'count'=>$count,'record_ids'=>in_array('id',$this->columns($child),true) ? (clone $outside)->orderBy('id')->limit(10)->pluck('id')->all() : []];
            }
            $linked=DB::table($child)->whereIn($column,$this->query($parent,$ids)->select($parentColumn))->count();
            $owned=in_array($child,$order,true) ? $this->query($child,$ids)->whereIn($column,$this->query($parent,$ids)->select($parentColumn))->count() : 0;
            if ($linked!==$owned) $warnings[]=['type'=>'external_dependants','table'=>$child,'column'=>$column,'parent_table'=>$parent,'count'=>$linked-$owned];
        }
        return $warnings;
    }

    private function references(string $table): array
    {
        if (isset($this->referenceMetadata[$table])) return $this->referenceMetadata[$table];
        $references = array_map(fn ($fk) => [...$fk, 'declared'=>true], $this->foreignKeys($table));
        foreach (config('company_archive.references') as $column => $parent) {
            if (in_array($column, $this->columns($table), true) && $this->hasTable($parent)) {
                $references[] = ['columns'=>[$column], 'foreign_table'=>$parent, 'foreign_columns'=>['id']];
            }
        }
        if (isset(config('company_archive.parents')[$table])) {
            [$column,$parent] = config('company_archive.parents')[$table];
            if (in_array($column, $this->columns($table), true)) $references[] = ['columns'=>[$column], 'foreign_table'=>$parent, 'foreign_columns'=>['id']];
        }
        return $this->referenceMetadata[$table] = $references;
    }

    public function fileReferences(int|array|null $companyId, ?array $movingUsers = null): array
    {
        // Keep source hints separate from the signed file metadata for compatibility with existing ZIPs.
        if ($companyId !== null) $this->fileOrigins = [];
        $out = [];
        foreach (config('company_archive.files') as $table => $rules) {
            if (!$this->hasTable($table)) continue;
            $query = $companyId === null ? DB::table($table) : $this->query($table, $companyId);
            foreach ($query->cursor() as $row) foreach ($rules as [$column, $diskRule]) {
                if ($table === 'users' && $movingUsers !== null && !in_array((int)$row->id, $movingUsers, true)) continue;
                $raw = $row->$column ?? null;
                if (!$raw) continue;
                $paths = json_decode((string) $raw, true);
                $paths = is_array($paths) ? $paths : [$raw];
                foreach ($paths as $path) {
                    CompanyArchiveGate::check(is_string($path), 'Unsupported file structure in '.$table.'.');
                    if ($companyId === null && preg_match('~^https?://~i', $path)) continue;
                    $disk = str_starts_with($diskRule, '@') ? ($row->{substr($diskRule, 1)} ?: config('filesystems.item_image_disk', 'public')) : $diskRule;
                    if ($disk === 'profile-asset') {
                        if (preg_match('~^https?://~i', $path) || str_starts_with($path, 'celestial/')) continue;
                        if (str_starts_with($path, 'public/')) $path = substr($path, 7);
                        if (str_starts_with($path, 'storage/')) { $path = substr($path, 8); $disk = 'public'; }
                        else $disk = is_file(public_path($path)) ? 'public-root' : 'public';
                    }
                    if ($disk === 'public-root' && str_starts_with($path, 'public/')) $path = substr($path, 7);
                    self::safePath($path);
                    if ($disk === 'public-root') CompanyArchiveGate::check(str_starts_with($path, 'uploads/'), 'Unreviewed public upload location.');
                    CompanyArchiveGate::check(!preg_match('/\.(php[0-9]?|phtml|phar|exe|dll|py|sh|key)$/i', $path), 'Executable/key files cannot be catalogue attachments.');
                    $key = $this->fileKey($disk, $path);
                    if ($companyId !== null && !isset($this->fileOrigins[$key])) {
                        $this->fileOrigins[$key] = ['table'=>$table,'record'=>(int)($row->id ?? 0),'field'=>$column,'disk'=>$disk,'optional_image'=>true];
                    }
                    if ($companyId !== null) {
                        // A shared reference to a required production document always wins.
                        $optionalImage = in_array($table,['companies','users','item_sets','stock_gallery_media','visiting_cards','marketing_qrs','product_background_removes'],true);
                        $this->fileOrigins[$key]['optional_image'] = $this->fileOrigins[$key]['optional_image'] && $optionalImage;
                    }
                    $count = ($out[$key]['references'] ?? 0) + 1;
                    $out[$key] = ['disk' => $disk, 'path' => $path, 'references' => $count,
                        'retained' => ($out[$key]['retained'] ?? false) || (in_array($table, config('company_archive.retained'), true) && !($table === 'users' && $movingUsers !== null))];
                }
            }
        }
        if ($companyId !== null && $this->hasTable('items')) {
            foreach (['glb_url', 'usdz_url', 'thumbnail_url'] as $column) if (in_array($column, $this->columns('items'), true)) {
                CompanyArchiveGate::check(!$this->query('items', $companyId)->whereNotNull($column)->where($column, '!=', '')->exists(),
                    'AR/external media references require a reviewed file policy before removal.');
            }
        }
        return $out;
    }

    public function attachmentSourceHint(array $file): string
    {
        $source = $this->fileOrigins[$this->fileKey($file['disk'],$file['path'])] ?? null;
        if (!$source) return 'local attachment';
        // Admin-facing record locator, without paths, signed URLs, filenames or personal data.
        return $source['table'].' record #'.$source['record'].', field '.$source['field'].', storage '.$source['disk'];
    }

    public function isOptionalImageAttachment(array $file): bool
    {
        return ($this->fileOrigins[$this->fileKey($file['disk'],$file['path'])]['optional_image'] ?? false) === true;
    }

    public function sharedLegacy(): array
    {
        $shared = [];
        foreach (config('company_archive.shared_without_owner', []) as $table) if ($this->hasTable($table)) {
            $parentColumn = config('company_archive.parents')[$table][0] ?? 'company_id';
            if (!in_array('company_id', $this->columns($table), true) && !in_array($parentColumn, $this->columns($table), true)) $shared[] = $table;
        }
        return $shared;
    }

    private function fileKey(string $disk, string $path): string
    {
        $config = $disk === 'public-root' ? ['driver' => 'local', 'root' => public_path()] : config('filesystems.disks.'.$disk);
        CompanyArchiveGate::check(is_array($config), 'Unknown file disk.');
        if (($config['driver'] ?? '') === 'local') {
            $absolute = self::disk($disk)->path($path);
            return hash('sha256', 'local|'.(realpath($absolute) ?: $absolute));
        }
        return hash('sha256', json_encode([$config['driver'], $config['endpoint'] ?? '', $config['bucket'] ?? '', $config['root'] ?? '', $path]));
    }

    public static function safePath(string $path): void
    {
        CompanyArchiveGate::check($path !== '' && !str_contains($path, "\0") && !str_contains($path, '\\')
            && !str_contains($path, ':') && !str_starts_with($path, '/') && !in_array('..', explode('/', $path), true), 'Unsafe archive file path.');
        CompanyArchiveGate::check(!str_starts_with($path, 'company-archives/'), 'Archive storage cannot be a catalogue file.');
    }

    public static function disk(string $disk)
    {
        if ($disk === 'public-root') return Storage::build(['driver' => 'local', 'root' => public_path(), 'throw' => true]);
        CompanyArchiveGate::check(array_key_exists($disk, config('filesystems.disks')), 'Unknown image storage disk.');
        return Storage::disk($disk);
    }
}
