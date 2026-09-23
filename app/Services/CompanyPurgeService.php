<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CompanyPurgeService
{
    public function purge(Company $company): array
    {
        $companyId = (int) $company->id;
        $files = $this->companyFiles($company);
        $stats = [];

        DB::transaction(function () use ($companyId, &$stats) {
            $relatedIds = $this->collectRelatedIds($companyId);

            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            try {
                $this->deleteSpecialRelations($relatedIds, $stats);
                $this->deleteForeignKeyPivotRows($relatedIds, $stats);
                $this->deleteRelatedIdTables($relatedIds, $stats);
                $this->deleteCompanyIdTables($companyId, $stats);

                $stats['companies'] = DB::table('companies')
                    ->where('id', $companyId)
                    ->delete();
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        });

        $deletedFiles = $this->deleteFiles($files);

        return [
            'tables' => array_filter($stats, fn ($count) => (int) $count > 0),
            'files_deleted' => $deletedFiles,
        ];
    }

    private function collectRelatedIds(int $companyId): array
    {
        $ids = ['companies' => [$companyId]];

        foreach ($this->tablesWithColumn('company_id') as $table) {
            if ($table === 'companies' || !Schema::hasColumn($table, 'id')) {
                continue;
            }

            $ids[$table] = $this->pluckIds($table, 'company_id', [$companyId]);
        }

        $foreignKeys = $this->foreignKeys();
        $changed = true;
        $passes = 0;

        while ($changed && $passes < 20) {
            $changed = false;
            $passes++;

            foreach ($foreignKeys as $foreignKey) {
                $parentTable = $foreignKey->referenced_table_name;
                $childTable = $foreignKey->table_name;
                $childColumn = $foreignKey->column_name;

                if (empty($ids[$parentTable]) || !Schema::hasTable($childTable) || !Schema::hasColumn($childTable, 'id')) {
                    continue;
                }

                $childIds = $this->pluckIds($childTable, $childColumn, $ids[$parentTable]);
                if (empty($childIds)) {
                    continue;
                }

                $existing = $ids[$childTable] ?? [];
                $merged = array_values(array_unique(array_merge($existing, $childIds)));

                if (count($merged) !== count($existing)) {
                    $ids[$childTable] = $merged;
                    $changed = true;
                }
            }
        }

        return $ids;
    }

    private function deleteSpecialRelations(array $relatedIds, array &$stats): void
    {
        $userIds = $relatedIds['users'] ?? [];
        $roleIds = $relatedIds['roles'] ?? [];
        $permissionIds = $relatedIds['permissions'] ?? [];

        $this->deleteWhereIn('password_set_tokens', 'user_id', $userIds, $stats);

        if ($this->tableHasColumns('personal_access_tokens', ['tokenable_type', 'tokenable_id']) && !empty($userIds)) {
            $deleted = DB::table('personal_access_tokens')
                ->whereIn('tokenable_type', [User::class, 'App\\Models\\User'])
                ->whereIn('tokenable_id', $userIds)
                ->delete();
            $this->addStat($stats, 'personal_access_tokens', $deleted);
        }

        if ($this->tableHasColumns('model_has_roles', ['model_type', 'model_id'])) {
            if (!empty($userIds)) {
                $deleted = DB::table('model_has_roles')
                    ->whereIn('model_type', [User::class, 'App\\Models\\User'])
                    ->whereIn('model_id', $userIds)
                    ->delete();
                $this->addStat($stats, 'model_has_roles', $deleted);
            }

            $this->deleteWhereIn('model_has_roles', 'role_id', $roleIds, $stats);
        }

        if ($this->tableHasColumns('model_has_permissions', ['model_type', 'model_id'])) {
            if (!empty($userIds)) {
                $deleted = DB::table('model_has_permissions')
                    ->whereIn('model_type', [User::class, 'App\\Models\\User'])
                    ->whereIn('model_id', $userIds)
                    ->delete();
                $this->addStat($stats, 'model_has_permissions', $deleted);
            }

            $this->deleteWhereIn('model_has_permissions', 'permission_id', $permissionIds, $stats);
        }

        $this->deleteWhereIn('role_has_permissions', 'role_id', $roleIds, $stats);
        $this->deleteWhereIn('role_has_permissions', 'permission_id', $permissionIds, $stats);
        $this->deleteWhereIn('production_step_user', 'user_id', $userIds, $stats);
        $this->deleteWhereIn('production_step_user', 'production_step_id', $relatedIds['production_steps'] ?? [], $stats);
    }

    private function deleteRelatedIdTables(array $relatedIds, array &$stats): void
    {
        $tables = array_keys($relatedIds);
        usort($tables, fn ($a, $b) => count($relatedIds[$b]) <=> count($relatedIds[$a]));

        foreach ($tables as $table) {
            if ($table === 'companies' || $this->hasColumn($table, 'company_id')) {
                continue;
            }

            $this->deleteWhereIn($table, 'id', $relatedIds[$table], $stats);
        }
    }

    private function deleteForeignKeyPivotRows(array $relatedIds, array &$stats): void
    {
        foreach ($this->foreignKeys() as $foreignKey) {
            $parentTable = $foreignKey->referenced_table_name;
            $childTable = $foreignKey->table_name;
            $childColumn = $foreignKey->column_name;

            if (empty($relatedIds[$parentTable]) || !Schema::hasTable($childTable) || Schema::hasColumn($childTable, 'id')) {
                continue;
            }

            $this->deleteWhereIn($childTable, $childColumn, $relatedIds[$parentTable], $stats);
        }
    }

    private function deleteCompanyIdTables(int $companyId, array &$stats): void
    {
        foreach ($this->tablesWithColumn('company_id') as $table) {
            if ($table === 'companies') {
                continue;
            }

            $deleted = DB::table($table)
                ->where('company_id', $companyId)
                ->delete();
            $this->addStat($stats, $table, $deleted);
        }
    }

    private function companyFiles(Company $company): array
    {
        $files = [
            ['disk' => null, 'path' => $company->company_logo],
        ];

        if ($this->tableHasColumns('item_sets', ['company_id', 'image_path'])) {
            DB::table('item_sets')
                ->where('company_id', $company->id)
                ->whereNotNull('image_path')
                ->select('image_disk', 'image_path')
                ->orderBy('id')
                ->chunk(500, function ($items) use (&$files) {
                    foreach ($items as $item) {
                        $files[] = [
                            'disk' => $item->image_disk ?: config('filesystems.item_image_disk', 'public'),
                            'path' => $item->image_path,
                        ];
                    }
                });
        }

        return $files;
    }

    private function deleteFiles(array $files): int
    {
        $deleted = 0;

        foreach ($files as $file) {
            $path = trim((string) ($file['path'] ?? ''));
            if ($path === '') {
                continue;
            }

            if (!empty($file['disk'])) {
                if (Storage::disk($file['disk'])->exists($path)) {
                    Storage::disk($file['disk'])->delete($path);
                    $deleted++;
                }
                continue;
            }

            $absolutePath = public_path(ltrim($path, '/'));
            if (File::exists($absolutePath)) {
                File::delete($absolutePath);
                $deleted++;
            }
        }

        return $deleted;
    }

    private function tablesWithColumn(string $column): array
    {
        return array_values(array_filter($this->tableNames(), fn ($table) => $this->hasColumn($table, $column)));
    }

    private function tableNames(): array
    {
        $database = DB::getDatabaseName();
        $rows = DB::select('SHOW TABLES');
        $key = 'Tables_in_' . $database;

        return array_map(fn ($row) => $row->{$key}, $rows);
    }

    private function foreignKeys(): array
    {
        return DB::select(
            "SELECT TABLE_NAME as table_name,
                    COLUMN_NAME as column_name,
                    REFERENCED_TABLE_NAME as referenced_table_name,
                    REFERENCED_COLUMN_NAME as referenced_column_name
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL",
            [DB::getDatabaseName()]
        );
    }

    private function pluckIds(string $table, string $column, array $values): array
    {
        if (empty($values) || !Schema::hasTable($table) || !Schema::hasColumn($table, $column) || !Schema::hasColumn($table, 'id')) {
            return [];
        }

        return DB::table($table)
            ->whereIn($column, $values)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function deleteWhereIn(string $table, string $column, array $values, array &$stats): void
    {
        if (empty($values) || !Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        foreach (array_chunk(array_values(array_unique($values)), 1000) as $chunk) {
            $deleted = DB::table($table)
                ->whereIn($column, $chunk)
                ->delete();
            $this->addStat($stats, $table, $deleted);
        }
    }

    private function addStat(array &$stats, string $table, int $deleted): void
    {
        if ($deleted <= 0) {
            return;
        }

        $stats[$table] = ($stats[$table] ?? 0) + $deleted;
    }

    private function tableHasColumns(string $table, array $columns): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    private function hasColumn(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }
}
