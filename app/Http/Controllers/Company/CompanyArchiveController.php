<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\CompanyArchiveGate;
use App\Services\CompanyArchiveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CompanyArchiveController extends Controller
{
    private function company(Request $request, string $slug): Company
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        $user = $request->user();
        abort_unless($user && (int) $user->company_id === (int) $company->id && $user->hasRole('company_admin'), 403);
        CompanyArchiveGate::check(CompanyArchiveGate::ready(), 'Archive setup is not complete. Contact the administrator.');
        \App\Services\CompanyArchiveScope::stateMembers((int)$company->id);
        $request->validate(['current_password' => 'required|string|max:1024']);
        CompanyArchiveGate::check(Hash::check($request->input('current_password'), $user->password), 'Your current account password is incorrect.');
        return $company;
    }

    private function run(Request $request, string $slug, callable $operation, string $message)
    {
        $company = $this->company($request, $slug);
        try { $operation($company); }
        catch (ValidationException $e) { throw $e; }
        catch (\Throwable $e) {
            Log::error('Company archive action interrupted', ['company_id' => $company->id, 'exception' => get_class($e)]);
            return redirect()->route('company.profile.show', $slug)->withErrors(['archive' => 'The action could not finish. Check the archive status and contact the administrator before retrying.']);
        }
        return redirect()->to(route('company.profile.show', $slug).'#company-archive')->with('archive_success', $message);
    }

    private function upload(Request $request): array
    {
        $request->validate(['archive_file' => 'required|file|max:'.config('company_archive.max_upload_kb'), 'password' => 'required|string|max:1024']);
        return [$request->file('archive_file')->getRealPath(), $request->input('password')];
    }

    public function prepare(Request $request, string $slug, CompanyArchiveService $service)
    {
        return $this->run($request, $slug, function ($company) use ($request, $service) {
            $request->validate(['password' => 'required|string|min:12|max:1024|confirmed']);
            $service->prepare($company, $request->user()->id, $request->input('password'));
        }, 'Encrypted archive created. Download to your pendrive, keep a second copy, then verify the saved file. Company entries are paused.');
    }

    public function download(Request $request, string $slug)
    {
        $company = $this->company($request, $slug);
        return \App\Services\CompanyArchiveLog::run('download',(int)$company->id,fn()=>$this->downloadArchive($company));
    }

    private function downloadArchive(Company $company)
    {
        \App\Services\CompanyArchiveLog::step('checking_download_state');
        $state = CompanyArchiveGate::state($company->id);
        \App\Services\CompanyArchiveLog::archive($state->archive_id ?? null);
        CompanyArchiveGate::check($state && in_array($state->status, ['ready', 'verified'], true) && $state->archive_path, 'No downloadable archive is ready.');
        $disk = Storage::disk(config('company_archive.disk'));
        \App\Services\CompanyArchiveLog::step('checking_download_file');
        CompanyArchiveGate::check($disk->exists($state->archive_path), 'Archive file is unavailable. Cancel and recreate it.');
        DB::table('company_archive_states')->where('company_id', $company->id)->update(['downloaded_at' => now()]);
        \App\Services\CompanyArchiveLog::step('download_response_prepared',['bytes'=>$disk->size($state->archive_path)]);
        return $disk->download($state->archive_path, 'company-'.$company->slug.'-'.$state->archive_id.'.zip', [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function verify(Request $request, string $slug, CompanyArchiveService $service)
    {
        return $this->run($request, $slug, function ($company) use ($request, $service) {
            [$path, $password] = $this->upload($request); $service->verify($company, $path, $password);
        }, 'Saved archive decrypted and all record/file checksums verified. No business data has been removed.');
    }

    public function remove(Request $request, string $slug, CompanyArchiveService $service)
    {
        return $this->run($request, $slug, function ($company) use ($request, $service) {
            $request->validate(['company_confirmation' => 'required|string', 'second_copy' => 'accepted']);
            CompanyArchiveGate::check(hash_equals($company->name, $request->input('company_confirmation')), 'Type the exact company name to confirm removal.');
            [$path, $password] = $this->upload($request); $service->remove($company, $path, $password);
        }, 'Company business data is archived. Account, users, permissions and required settings remain available for restore.');
    }

    public function restore(Request $request, string $slug, CompanyArchiveService $service)
    {
        return $this->run($request, $slug, function ($company) use ($request, $service) {
            [$path, $password] = $this->upload($request); $service->restore($company, $path, $password);
        }, 'Business records and attachments restored and reconciled. Company entries are available again.');
    }

    public function cancel(Request $request, string $slug, CompanyArchiveService $service)
    {
        return $this->run($request, $slug, fn ($company) => $service->cancel($company), 'Archive cancelled. Original business data is unchanged; company entries are available again.');
    }
}
