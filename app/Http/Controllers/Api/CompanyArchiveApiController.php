<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\{CompanyArchiveGate, CompanyArchiveScope, CompanyArchiveService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash, Log, Storage};
use Illuminate\Validation\ValidationException;

class CompanyArchiveApiController extends Controller
{
    private function company(Request $request, bool $password = true): Company
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('company_admin'), 403);
        // Never take the target company from untrusted request fields.
        $company = Company::findOrFail($user->company_id);
        if ($password) {
            CompanyArchiveGate::check(CompanyArchiveGate::ready(), 'Archive setup is not complete. Contact the administrator.');
            CompanyArchiveScope::stateMembers((int)$company->id);
            $request->validate(['current_password'=>'required|string|max:1024']);
            CompanyArchiveGate::check(Hash::check($request->input('current_password'), $user->password), 'Your current account password is incorrect.');
        }
        return $company;
    }

    private function payload(Company $company): array
    {
        $ready = CompanyArchiveGate::ready();
        $state = $ready ? CompanyArchiveGate::state((int)$company->id) : null;
        $summary = json_decode($state->summary ?? '{}', true) ?: [];
        $status = $state->status ?? 'active';
        $managed = isset($summary['group_root_id']) && (int)$summary['group_root_id'] !== (int)$company->id;
        $actions = match ($status) {
            'active'=>config('company_archive.enabled') && $ready ? ['prepare'] : [],
            'preparing'=>['prepare','cancel'], 'ready'=>['download','verify','cancel'],
            'verified'=>['download','verify','remove','cancel'],
            'cleanup_pending'=>['remove','restore'], 'archived'=>['restore'], default=>[],
        };
        if ($managed) $actions = [];
        return [
            'company'=>['id'=>(int)$company->id,'name'=>$company->name,'slug'=>$company->slug],
            'setup_ready'=>$ready,'enabled'=>(bool)config('company_archive.enabled'),'status'=>$status,
            'managed_by_parent'=>$managed,'available_actions'=>$actions,
            'workspaces'=>$managed ? [['id'=>(int)$company->id,'name'=>$company->name,'slug'=>$company->slug]]
                : ($summary['companies'] ?? (in_array($status,['active','preparing'],true) ? CompanyArchiveScope::family($company) : [['id'=>(int)$company->id,'name'=>$company->name,'slug'=>$company->slug]])),
            'archive_id'=>$managed ? null : ($state->archive_id ?? null),
            'filename'=>!$managed && ($state->archive_id ?? null) ? 'company-'.$company->slug.'-'.$state->archive_id.'.zip' : null,
            'downloaded_at'=>$managed ? null : ($state->downloaded_at ?? null),
            'verified_at'=>$managed ? null : ($state->verified_at ?? null),
            'summary'=>$managed ? null : array_intersect_key($summary,array_flip(['records','files','shared_files_retained','shared_legacy','bytes','staff_accounts','staff_count','progress','media_policy','cloud_references','missing_optional_images','missing_optional_sources'])),
        ];
    }

    public function status(Request $request)
    {
        return response()->json(['success'=>true,'data'=>$this->payload($this->company($request,false))])->header('Cache-Control','private, no-store');
    }

    private function run(Request $request, callable $operation, string $message)
    {
        $company = $this->company($request);
        try { $operation($company); }
        catch (ValidationException $e) { throw $e; }
        catch (\Throwable $e) {
            Log::error('Company archive API action interrupted',['company_id'=>$company->id,'exception'=>get_class($e)]);
            return response()->json(['success'=>false,'code'=>'ARCHIVE_OPERATION_INTERRUPTED',
                'message'=>'The action could not finish. Check archive status before retrying; contact the administrator if an operation is still running.'],503)->header('Cache-Control','private, no-store');
        }
        return response()->json(['success'=>true,'message'=>$message,'data'=>$this->payload($company)])->header('Cache-Control','private, no-store');
    }

    private function upload(Request $request): array
    {
        $request->validate(['archive_file'=>'required|file|max:'.config('company_archive.max_upload_kb'),'password'=>'required|string|max:1024']);
        return [$request->file('archive_file')->getRealPath(),$request->input('password')];
    }

    public function prepare(Request $request, CompanyArchiveService $service)
    {
        return $this->run($request,function ($company) use ($request,$service) {
            $request->validate(['password'=>'required|string|min:12|max:1024|confirmed']);
            $service->prepare($company,(int)$request->user()->id,$request->input('password'));
        },'Encrypted archive created. Save the download and a second safe copy, then upload the saved file for verification. Entries are paused.');
    }

    public function download(Request $request)
    {
        $company = $this->company($request);
        return \App\Services\CompanyArchiveLog::run('download',(int)$company->id,fn()=>$this->downloadArchive($company));
    }

    private function downloadArchive(Company $company)
    {
        \App\Services\CompanyArchiveLog::step('checking_download_state');
        $leases = [];
        $release = CompanyArchiveGate::protectRequests($leases);
        $state = CompanyArchiveGate::enterDownload((int)$company->id);
        $leases[] = (int)$company->id;
        \App\Services\CompanyArchiveLog::archive($state->archive_id);
        try {
            \App\Services\CompanyArchiveLog::step('checking_download_file');
            $disk = Storage::disk(config('company_archive.disk'));
            CompanyArchiveGate::check($disk->exists($state->archive_path),'Archive file is unavailable. Cancel and recreate it.');
            $response = $disk->download($state->archive_path,'company-'.$company->slug.'-'.$state->archive_id.'.zip',
                ['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
            $callback = $response->getCallback();
            $response->setCallback(function () use ($callback,$release) {
                try { $callback(); } finally { $release(); }
            });
            // Initiation is not proof of a saved file; upload verification remains mandatory.
            DB::table('company_archive_states')->where('company_id',$company->id)->update(['downloaded_at'=>now()]);
            \App\Services\CompanyArchiveLog::step('download_response_prepared',['bytes'=>$disk->size($state->archive_path)]);
            return $response;
        } catch (\Throwable $e) { $release(); throw $e; }
    }

    public function verify(Request $request, CompanyArchiveService $service)
    {
        return $this->run($request,function ($company) use ($request,$service) {
            [$path,$password] = $this->upload($request); $service->verify($company,$path,$password);
        },'Saved archive verified. No records have been removed.');
    }

    public function remove(Request $request, CompanyArchiveService $service)
    {
        return $this->run($request,function ($company) use ($request,$service) {
            $request->validate(['company_confirmation'=>'required|string','second_copy'=>'accepted']);
            CompanyArchiveGate::check(hash_equals($company->name,$request->input('company_confirmation')),'Type the exact company name to confirm removal.');
            [$path,$password] = $this->upload($request); $service->remove($company,$path,$password);
        },'Verified company business data is archived. Administrator accounts remain available for restore.');
    }

    public function restore(Request $request, CompanyArchiveService $service)
    {
        return $this->run($request,function ($company) use ($request,$service) {
            [$path,$password] = $this->upload($request); $service->restore($company,$path,$password);
        },'Records and attachments restored and reconciled. Entries are available again; restored staff must log in again.');
    }

    public function cancel(Request $request, CompanyArchiveService $service)
    {
        return $this->run($request,fn ($company)=>$service->cancel($company),'Archive cancelled. Original records are unchanged; entries are available again.');
    }
}
