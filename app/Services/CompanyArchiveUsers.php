<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Versioned staff-account scope. Shared role definitions and company administrators stay live. */
class CompanyArchiveUsers
{
    public const TABLES = ['users', 'model_has_roles', 'model_has_permissions', 'worker_allowed_devices'];

    public function ids(array $companies): array
    {
        $query = DB::table('users')->whereIn('company_id', $companies);
        if (Schema::hasColumn('users', 'role')) $query->where(fn ($q) => $q->whereNull('role')->orWhereNotIn('role', ['company_admin', 'Company Admin']));
        if (Schema::hasTable('roles') && Schema::hasTable('model_has_roles')) {
            $query->whereNotIn('id', DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', 'company_admin')->where('model_type', User::class)->select('model_id'));
        }
        return $query->orderBy('id')->pluck('id')->map(fn ($id) => (int)$id)->all();
    }

    public function review(array $companies, array $ids): void
    {
        foreach (self::TABLES as $table) if (Schema::hasTable($table)) {
            foreach (Schema::getColumnListing($table) as $column) if (preg_match('/(?:_path|_image|_file|_url|attachments)$|^path$/', $column)) {
                CompanyArchiveGate::check($table === 'users' && in_array($column, ['profile_image', 'password_set_url'], true), 'Unreviewed staff attachment column: '.$table.'.'.$column);
            }
        }
        if (Schema::hasTable('worker_allowed_devices')) {
            CompanyArchiveGate::check(!DB::table('worker_allowed_devices')->join('users', 'users.id', '=', 'worker_allowed_devices.user_id')
                ->whereIn('users.id', $ids)->whereColumn('users.company_id', '!=', 'worker_allowed_devices.company_id')->exists(), 'Another company has device records for these staff accounts.');
        }
        foreach ($this->access($ids)['roles'] ?? [] as $encoded) {
            $role = CompanyArchiveInventory::decodeRow($encoded);
            CompanyArchiveGate::check(!isset($role['company_id']) || in_array((int)$role['company_id'], $companies, true), 'A staff account has another company\'s role. Review access before archiving.');
        }
    }

    public function query(string $table, array $ids)
    {
        CompanyArchiveGate::check(in_array($table, self::TABLES, true), 'Unreviewed staff archive table.');
        $q = DB::table($table);
        return match ($table) {
            'users' => $q->whereIn('id', $ids),
            'worker_allowed_devices' => $q->whereIn('user_id', $ids),
            default => $q->where('model_type', User::class)->whereIn('model_id', $ids),
        };
    }

    public function ordered(string $table, array $ids)
    {
        $query = $this->query($table, $ids);
        foreach (Schema::getColumnListing($table) as $column) $query->orderBy($column);
        return $query;
    }

    public function row(string $table, object|array $row): array
    {
        $row = (array)$row;
        if ($table === 'users') {
            // Never reactivate an old browser session, API token or password-reset link on restore.
            foreach (['remember_token', 'active_api_token_id', 'active_device_id', 'active_device_name', 'active_platform', 'active_login_at', 'password_set_url'] as $column) {
                if (array_key_exists($column, $row)) $row[$column] = null;
            }
            if (array_key_exists('active_web_session_id', $row)) $row['active_web_session_id'] = 'archive-relogin-required';
        }
        return $row;
    }

    public function digest(string $table, array $ids): array
    {
        $hash = hash_init('sha256'); $count = 0;
        foreach ($this->ordered($table, $ids)->cursor() as $row) {
            hash_update($hash, json_encode(CompanyArchiveInventory::encodeRow($this->row($table, $row)), JSON_THROW_ON_ERROR)."\n"); $count++;
        }
        return ['count'=>$count, 'sha256'=>hash_final($hash)];
    }

    public function access(array $ids): array
    {
        $out = []; $roles = []; $permissions = [];
        if (Schema::hasTable('model_has_roles')) $roles = $this->query('model_has_roles', $ids)->pluck('role_id')->all();
        if (Schema::hasTable('model_has_permissions')) $permissions = $this->query('model_has_permissions', $ids)->pluck('permission_id')->all();
        $queries = [];
        if (Schema::hasTable('roles')) $queries['roles'] = DB::table('roles')->whereIn('id', $roles);
        if (Schema::hasTable('role_has_permissions')) {
            $queries['role_has_permissions'] = DB::table('role_has_permissions')->whereIn('role_id', $roles);
            $permissions = array_merge($permissions, (clone $queries['role_has_permissions'])->pluck('permission_id')->all());
        }
        if (Schema::hasTable('permissions')) $queries['permissions'] = DB::table('permissions')->whereIn('id', $permissions);
        foreach ($queries as $table => $query) {
            foreach (Schema::getColumnListing($table) as $column) $query->orderBy($column);
            $out[$table] = $query->get()->map(fn ($row) => CompanyArchiveInventory::encodeRow($row))->all();
        }
        return $out;
    }

    public function assertAccess(array $access): void
    {
        foreach ($access as $table => $rows) {
            CompanyArchiveGate::check(in_array($table, ['roles', 'permissions', 'role_has_permissions'], true) && Schema::hasTable($table), 'Staff role definitions require compatibility review.');
            foreach ($rows as $encoded) {
                $row = CompanyArchiveInventory::decodeRow($encoded); $query = DB::table($table);
                foreach ($row as $column => $value) $query->where($column, $value);
                CompanyArchiveGate::check($query->exists(), 'A staff role or permission changed. Restore stopped without changing access rights.');
            }
        }
        // Also detect permissions added to shared roles while the staff accounts were archived.
        if (isset($access['roles']) && Schema::hasTable('role_has_permissions')) {
            $roleIds = array_map(fn ($row) => CompanyArchiveInventory::decodeRow($row)['id'], $access['roles']);
            CompanyArchiveGate::check(DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->count() === count($access['role_has_permissions'] ?? []), 'Staff role permissions changed. A reviewed access conversion is required.');
        }
    }

    public function links(array $companies, array $ids, array $business): array
    {
        $links = []; $inventory = app(CompanyArchiveInventory::class);
        foreach ($inventory->tables() as $table) {
            foreach ($inventory->foreignKeys($table) as $fk) {
                if ($fk['foreign_table'] !== 'users') continue;
                CompanyArchiveGate::check(count($fk['columns']) === 1 && $fk['foreign_columns'] === ['id'], 'Composite staff reference requires review.');
                $column = $fk['columns'][0]; $linked = DB::table($table)->whereIn($column, $ids);
                if (in_array($table, $business, true)) {
                    $mine = $inventory->query($table, $companies)->whereIn($column, $ids)->count();
                    CompanyArchiveGate::check($linked->count() === $mine, 'Another company references these staff accounts: '.$table.'.');
                    continue;
                }
                if (in_array($table, self::TABLES, true)) $linked->whereNotIn('id', $this->query($table, $ids)->select('id'));
                if (in_array($table, ['sessions', 'password_set_tokens'], true)) continue;
                if (!$linked->exists()) continue;
                $metadata = collect(Schema::getColumns($table))->firstWhere('name', $column);
                CompanyArchiveGate::check(in_array($table, config('company_archive.retained'), true)
                    && Schema::hasColumn($table, 'company_id') && Schema::hasColumn($table, 'id') && ($metadata['nullable'] ?? false),
                    'A retained/system record references a staff account: '.$table.'. Review it before archiving.');
                foreach ($linked->orderBy('id')->get() as $row) {
                    CompanyArchiveGate::check(in_array((int)$row->company_id, $companies, true), 'Another company references these staff accounts: '.$table.'.');
                    $links[] = ['table'=>$table, 'id'=>(int)$row->id, 'column'=>$column, 'user_id'=>(int)$row->$column];
                }
            }
        }
        return $links;
    }

    public function detach(array $links): void
    {
        foreach ($links as $link) CompanyArchiveGate::check(DB::table($link['table'])->where('id', $link['id'])->where($link['column'], $link['user_id'])->update([$link['column']=>null]) === 1, 'Retained staff reference changed after export.');
    }

    public function restoreLinks(array $links): void
    {
        foreach ($links as $link) CompanyArchiveGate::check(DB::table($link['table'])->where('id', $link['id'])->whereNull($link['column'])->update([$link['column']=>$link['user_id']]) === 1, 'Retained staff reference changed; restore cannot overwrite it.');
    }

    public function revoke(array $ids): void
    {
        foreach (['sessions', 'password_set_tokens'] as $table) if (Schema::hasTable($table)) DB::table($table)->whereIn('user_id', $ids)->delete();
        if (Schema::hasTable('personal_access_tokens')) DB::table('personal_access_tokens')->where('tokenable_type', User::class)->whereIn('tokenable_id', $ids)->delete();
        if (Schema::hasTable('password_reset_tokens')) DB::table('password_reset_tokens')->whereIn('email', DB::table('users')->whereIn('id', $ids)->select('email'))->delete();
    }
}
