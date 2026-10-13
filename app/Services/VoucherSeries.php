<?php
namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VoucherSeries
{
    public const MODULES = [
        'SL'=>'Sales Estimate / Sale','APP'=>'Sale on Approval','AR'=>'Approval Return',
        'SR'=>'Sales Return','RP'=>'Receive / Purchase','JI'=>'Jobwork Issue',
        'JWR'=>'Direct Jobwork Receive','VV'=>'Vacuum Voucher',
    ];
    private const TABLES = [
        'SL'=>['sales','voucher_no'],'APP'=>['approval_headers','approval_no'],
        'AR'=>['sale_returns','return_voucher_no'],'SR'=>['sale_returns','return_voucher_no'],
        'RP'=>['customer_advance_vouchers','voucher_no'],'JI'=>['jobwork_issues','voucher_no'],
        'JWR'=>['jobwork_receives','receive_no'],'VV'=>['vacuum_vouchers','voucher_no'],
    ];

    public static function defaultPrefix(Company $company): string
    {
        $words=preg_split('/[^A-Za-z0-9]+/', Str::ascii($company->name ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        return substr(strtoupper(implode('',array_map(fn($w)=>$w[0],$words))),0,20) ?: 'CO'.$company->id;
    }

    public static function prefix(Company $company): string
    {
        return $company->voucher_prefix ?: self::defaultPrefix($company);
    }

    public static function next(int $companyId, string $module): string
    {
        if (!isset(self::MODULES[$module])) throw new \InvalidArgumentException('Unknown voucher module.');
        return DB::transaction(function() use($companyId,$module) {
            // Serialize allocation and configuration changes for this company, including first use.
            $company=Company::whereKey($companyId)->lockForUpdate()->firstOrFail();
            $last=(int)DB::table('company_voucher_counters')->where('company_id',$companyId)->where('module',$module)->value('last_number');
            $number=self::unusedNumber($company,$module,$last+1);
            DB::table('company_voucher_counters')->updateOrInsert(
                ['company_id'=>$companyId,'module'=>$module],['last_number'=>$number]
            );
            return self::format($company,$module,$number);
        });
    }

    private static function format(Company $company,string $module,int $number): string
    {
        return self::prefix($company).'-'.$module.'-'.str_pad((string)$number,3,'0',STR_PAD_LEFT);
    }

    private static function unusedNumber(Company $company,string $module,int $number): int
    {
        [$table,$column]=self::TABLES[$module];
        while (DB::table($table)->where('company_id',$company->id)->where($column,self::format($company,$module,$number))->exists()) $number++;
        return $number;
    }

    public static function configuration(Company $company): array
    {
        $counters=DB::table('company_voucher_counters')->where('company_id',$company->id)->pluck('last_number','module');
        $series=[];
        foreach(self::MODULES as $module=>$label) {
            $next=self::unusedNumber($company,$module,1+(int)($counters[$module] ?? 0));
            $series[]=['module'=>$module,'label'=>$label,'next_number'=>$next,'preview'=>self::format($company,$module,$next)];
        }
        return ['prefix'=>self::prefix($company),'custom_prefix'=>$company->voucher_prefix,
            'default_prefix'=>self::defaultPrefix($company),'series'=>$series];
    }
}
