<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Keep a branch group in one archive without exposing it to a branch administrator. */
class CompanyArchiveScope
{
    public static function family(Company $root): array
    {
        $ids = [(int)$root->id];
        if (!$root->parent_company_id && Schema::hasColumn('companies', 'parent_company_id')) {
            $frontier = $ids;
            while ($frontier) {
                $children = DB::table('companies')->whereIn('parent_company_id', $frontier)->pluck('id')->map(fn ($id) => (int)$id)->all();
                CompanyArchiveGate::check(!array_intersect($ids, $children), 'Company hierarchy contains a cycle. Review branches before archiving.');
                $ids = array_merge($ids, $children); $frontier = $children;
            }
        }
        sort($ids);
        return array_map(fn ($company) => self::describe($company), DB::table('companies')->whereIn('id', $ids)->orderBy('id')->get()->all());
    }

    private static function describe(object $company): array
    {
        return ['id'=>(int)$company->id, 'slug'=>(string)$company->slug, 'name'=>(string)$company->name,
            'parent_company_id'=>isset($company->parent_company_id) ? (int)$company->parent_company_id : null];
    }

    public static function ids(array $members): array
    {
        $ids = array_values(array_unique(array_map(fn ($member) => (int)$member['id'], $members))); sort($ids);
        return $ids;
    }

    public static function stateMembers(int $rootId, ?object $lockedState=null): array
    {
        $state = $lockedState ?? CompanyArchiveGate::state($rootId);
        $summary = json_decode($state->summary ?? '{}', true) ?: [];
        CompanyArchiveGate::check(!isset($summary['group_root_id']) || (int)$summary['group_root_id'] === $rootId,
            'This branch belongs to a main-company archive. Use Archive / Restore from the main company profile.');
        return $summary['companies'] ?? [['id'=>$rootId]];
    }

    public static function manifestIds(int $rootId, array $manifest): array
    {
        return isset($manifest['companies']) ? self::ids($manifest['companies']) : [$rootId];
    }

    public static function assertMembership(Company $root, array $manifest): void
    {
        $expected = self::manifestIds((int)$root->id, $manifest);
        CompanyArchiveGate::check($expected === self::ids(self::stateMembers((int)$root->id)), 'Archive company/branch scope does not match the current operation.');
        if (!isset($manifest['companies'])) return; // Legacy single-company archives stay single-company.
        $current = self::family($root);
        $identity = fn ($members) => array_map(fn ($m) => [$m['id'], $m['slug'], $m['parent_company_id']], $members);
        CompanyArchiveGate::check($identity($current) === $identity($manifest['companies']), 'Company branches or their ownership changed after export. Review the hierarchy before proceeding.');
    }

    public static function freeze(Company $root, array $members): void
    {
        $ids = self::ids($members);
        DB::transaction(function () use ($root, $members, $ids) {
            foreach ($ids as $id) CompanyArchiveGate::ensure($id);
            $states = DB::table('company_archive_states')->whereIn('company_id', $ids)->orderBy('company_id')->lockForUpdate()->get();
            foreach ($states as $state) {
                $summary = json_decode($state->summary ?? '{}', true) ?: [];
                CompanyArchiveGate::check(in_array($state->status, ['active','preparing'], true), 'A company/branch already has an archive or another action is running. Restore or cancel it first.');
                CompanyArchiveGate::check(!isset($summary['group_root_id']) || (int)$summary['group_root_id'] === (int)$root->id, 'A branch is already managed by a different archive.');
                if ($state->status === 'preparing' && isset($summary['companies'])) CompanyArchiveGate::check(self::ids($summary['companies']) === $ids, 'Branch scope changed while preparation was paused. Cancel the old operation first.');
            }
            foreach ($ids as $id) {
                $summary = ['group_root_id'=>(int)$root->id];
                if ($id === (int)$root->id) $summary['companies'] = $members;
                DB::table('company_archive_states')->where('company_id',$id)->update(['status'=>'preparing', 'summary'=>json_encode($summary), 'updated_at'=>now()]);
            }
        });
    }

    public static function transition(int $rootId, array $from, string $to): object
    {
        return DB::transaction(function () use ($rootId, $from, $to) {
            CompanyArchiveGate::ensure($rootId);
            $members = self::stateMembers($rootId); $ids = self::ids($members);
            $states = DB::table('company_archive_states')->whereIn('company_id',$ids)->orderBy('company_id')->lockForUpdate()->get();
            $root=$states->firstWhere('company_id',$rootId);
            CompanyArchiveGate::check($root && $states->count() === count($ids), 'Archive branch controls are incomplete.');
            CompanyArchiveGate::check(self::ids(self::stateMembers($rootId,$root))===$ids,'Archive branch scope changed. Refresh and retry.');
            foreach ($states as $state) CompanyArchiveGate::reconcile($state);
            // Log every blocking member, including branches after the first failing check.
            foreach ($states as $state) {
                if ((int)$state->active_requests !== 0) CompanyArchiveLog::lease('requests_not_drained', (int)$state->company_id, [
                    'root_company_id'=>$rootId, 'active_requests'=>(int)$state->active_requests,
                    'state'=>$state->status, 'state_updated_at'=>$state->updated_at, 'target_state'=>$to,
                ]);
            }
            foreach ($states as $state) {
                CompanyArchiveGate::check(in_array($state->status,$from,true) && $state->archive_id === $root->archive_id, 'Another archive operation is running, or this action is not available. Company/branch states must match.');
                $summary=json_decode($state->summary??'{}',true)?:[];
                CompanyArchiveGate::check(!isset($summary['group_root_id']) || (int)$summary['group_root_id']===$rootId, 'Branch archive ownership does not match.');
                CompanyArchiveGate::check((int)$state->active_requests===0, 'Company/branch requests are still finishing. Wait and retry; do not clear the request counter manually.');
            }
            self::update($rootId, ['status'=>$to]);
            return $root;
        });
    }

    public static function update(int $rootId, array $values): void
    {
        DB::transaction(function () use ($rootId,$values) {
            $members=self::stateMembers($rootId); $ids=self::ids($members);
            // Child rows receive only coordination fields, never a path/hash for the group's private ZIP.
            $child=array_intersect_key($values,array_flip(['status','archive_id']));
            if (array_key_exists('summary',$values) && $values['summary']===null) {
                $child += ['summary'=>null,'archive_path'=>null,'archive_sha256'=>null,'verified_at'=>null,'downloaded_at'=>null];
            }
            foreach ($ids as $id) if ($id!==$rootId) DB::table('company_archive_states')->where('company_id',$id)->update([...$child,'updated_at'=>now()]);
            DB::table('company_archive_states')->where('company_id',$rootId)->update([...$values,'updated_at'=>now()]);
        });
    }
}
