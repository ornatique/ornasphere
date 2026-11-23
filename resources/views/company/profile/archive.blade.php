@if($user->hasRole('company_admin'))
@php
    $archiveReady = \App\Services\CompanyArchiveGate::ready();
    $archiveState = $archiveReady ? \App\Services\CompanyArchiveGate::state((int)$company->id) : null;
    $archiveStatus = $archiveState->status ?? 'active';
    $archiveSummary = json_decode($archiveState->summary ?? '{}', true) ?: [];
    $archiveManagedByParent = isset($archiveSummary['group_root_id']) && (int)$archiveSummary['group_root_id'] !== (int)$company->id;
    $archiveMembers = $archiveSummary['companies'] ?? (in_array($archiveStatus,['active','preparing']) && !$archiveManagedByParent
        ? \App\Services\CompanyArchiveScope::family($company) : [['id'=>(int)$company->id,'name'=>$company->name,'slug'=>$company->slug]]);
    $archiveActions = match ($archiveStatus) {
        'active' => ['prepare'], 'preparing' => ['prepare', 'cancel'],
        'ready' => ['download', 'verify', 'cancel'], 'verified' => ['download', 'verify', 'remove', 'cancel'],
        'cleanup_pending' => ['remove', 'restore'], 'archived' => ['restore'], default => [],
    };
    if ($archiveManagedByParent) $archiveActions=[];
    $archiveLabels = ['prepare' => '1. Pause company and create encrypted archive', 'download' => '2. Download encrypted archive',
        'verify' => '3. Verify saved pendrive file', 'remove' => $archiveStatus === 'cleanup_pending' ? 'Finish file cleanup' : '4. Remove verified business data',
        'restore' => 'Verify and restore company', 'cancel' => 'Cancel archive and resume company'];
@endphp
<section class="card" id="company-archive" aria-labelledby="company-archive-title">
    <div class="card-header"><h3 id="company-archive-title" class="mb-1">Data Backup</h3><span>Protect this company's business data on your pendrive and restore it later.</span></div>
    <div class="card-body">
        @if(session('archive_success'))<div class="alert alert-success" role="status">{{ session('archive_success') }}</div>@endif
        @if(session('archive_notice'))<div class="alert alert-info" role="status">{{ session('archive_notice') }}</div>@endif
        @foreach(['archive', 'current_password', 'password', 'archive_file', 'company_confirmation', 'second_copy'] as $archiveField)
            @error($archiveField)<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
        @endforeach
        @if($archiveManagedByParent)
            <div class="alert alert-info">This branch is paused as part of its main company's archive. The main company administrator manages the group download, restore and cancellation.</div>
        @else
            <p><strong>{{ $company->name }}</strong>{{ count($archiveMembers)>1 ? ' and all included branches' : ' only in this archive' }}.
                @if(in_array($archiveStatus, ['active','preparing']) || ($archiveSummary['staff_accounts'] ?? false))
                    Company Admin accounts stay available for login and restore. All other users, their role/permission assignments and device settings move into the archive and return on restore. Shared role/permission definitions and required company settings stay in the ERP.
                @else
                    This existing archive keeps users and access settings in the ERP. To move non-admin users too, restore or cancel it first, then create a new archive.
                @endif
                Lists and reports can be viewed while the archive is idle; adding, editing and deleting stay disabled until the group is restored or cancelled.</p>
            <p>Included workspaces: <strong>{{ collect($archiveMembers)->pluck('name')->implode(' / ') }}</strong></p>
            @if(!in_array($archiveStatus,['active','preparing']) && !isset($archiveSummary['companies']) && !$company->parent_company_id)
                <p>This existing archive contains the main company only. To include branches, restore or cancel this archive first, then create a new archive.</p>
            @elseif($company->parent_company_id)
                <p>A branch administrator archives only this branch. Use the main company profile to archive the main company and all branches together.</p>
            @endif
        @endif
        <p>Older background-removal images without a company/user link are shared records. They stay on the server and are not treated as this company's data.</p>
        @if(($archiveSummary['media_policy'] ?? null)==='cloud_paths_only' || in_array($archiveStatus,['active','preparing']))
            <div class="alert alert-info">Cloud images: only their saved paths are included in new backups. Images stay in the cloud and are not downloaded or deleted. Restore needs the same cloud storage; this ZIP cannot recover images deleted or changed in the cloud. Available local files are included. Missing logos and optional images are skipped and listed; required production documents must be present.</div>
        @elseif(app()->environment('local'))
            <div class="alert alert-info">This existing archive keeps its original attachment format. Cloud originals are preserved. Local business records and unshared local attachments can be removed for this test.</div>
        @endif
        <p>Status: <strong>{{ ucwords(str_replace('_', ' ', $archiveStatus)) }}</strong></p>
        @if($archiveStatus==='active' && isset($archiveSummary['last_prepare_failure']))
            <div class="alert alert-info">The last backup attempt failed before an archive was saved. Company entries are available. Correct the reported issue before retrying.</div>
        @endif
        @if(!$archiveManagedByParent && !empty($archiveSummary['missing_optional_images']))
            <div class="alert alert-warning">{{ $archiveSummary['missing_optional_images'] }} optional image(s) were missing when this backup was created and are not included. Existing records are preserved; restore cannot recreate these missing images.
                <details><summary>Missing optional images</summary>
                    @foreach(($archiveSummary['missing_optional_sources'] ?? []) as $source)
                        @php
                            $displaySource = $source;
                            if (preg_match('/^companies record #(\d+), field company_logo, storage [^,]+$/D', (string)$source, $sourceMatch)) {
                                $sourceCompany = collect($archiveMembers)->firstWhere('id', (int)$sourceMatch[1]);
                                $displaySource = ($sourceCompany['name'] ?? 'Company').' — Company logo missing';
                            }
                        @endphp
                        <div>{{ $displaySource }}</div>
                    @endforeach
                </details>
            </div>
        @endif
        @if(!$archiveManagedByParent && isset($archiveSummary['progress']))
            @php
                $backupProgress = $archiveSummary['progress'];
                $backupStep = ['checking_records'=>'Checking records', 'reading_schema'=>'Checking database structure', 'exporting_records'=>'Copying records',
                    'checking_images'=>'Checking image references', 'copying_images'=>'Copying images', 'encrypting_zip'=>'Encrypting ZIP',
                    'verifying_zip'=>'Verifying ZIP', 'saving_zip'=>'Saving encrypted ZIP'][$backupProgress['stage'] ?? ''] ?? 'Preparing backup';
            @endphp
            <div class="alert alert-info" role="status">
                Last backup step: <strong>{{ $backupStep }}</strong>. Last progress: {{ $backupProgress['updated_at'] ?? '-' }}.
                @if(!empty($backupProgress['error_code']))<br>Backup error code: <strong>{{ $backupProgress['error_code'] }}</strong>. Ask the server administrator to review this attempt before resuming.@endif
                <details><summary>Support details</summary>
                    Reference: {{ $backupProgress['attempt'] ?? '-' }}<br>
                    PHP: {{ $backupProgress['php_version'] ?? '-' }}; memory limit: {{ $backupProgress['memory_limit'] ?? '-' }}; execution limit: {{ $backupProgress['max_execution_time'] ?? '-' }} seconds.<br>
                    Step detail: {{ $backupProgress['detail'] ?? '-' }}
                </details>
            </div>
        @endif
        @if(!$archiveReady || (!config('company_archive.enabled') && $archiveStatus === 'active'))
            <div class="alert alert-info">Archive tools are awaiting server setup. Existing company data is unchanged.</div>
        @else
            @if(isset($archiveSummary['records']))
                <p>{{ array_sum(array_column($archiveSummary['records'] ?? [], 'count')) }} records in the archive; {{ $archiveSummary['files'] ?? 0 }} stored files; {{ $archiveSummary['cloud_references'] ?? 0 }} cloud image paths. {{ $archiveSummary['shared_files_retained'] ?? 0 }} shared/account files remain on the server.</p>
                <details class="mb-3"><summary>Archive contents</summary><div class="table-responsive"><table class="table table-bordered mt-3"><thead><tr><th>Record group</th><th>Records</th><th>Action</th></tr></thead><tbody>
                @foreach(($archiveSummary['records'] ?? []) as $group => $details)
                    <tr><td>{{ ucwords(str_replace('_', ' ', $group)) }}</td><td>{{ $details['count'] }}</td><td>{{ in_array($details['mode'], ['move','staff_move']) ? 'Archive / restore' : 'Account/settings retained' }}</td></tr>
                @endforeach
                </tbody></table></div></details>
            @endif
            @if(!$archiveActions && !$archiveManagedByParent)<div class="alert alert-info">An archive operation is running. Refresh after it finishes. If interrupted, ask the administrator to review the operation before resuming.</div>@endif
            @foreach($archiveActions as $archiveAction)
                <form method="POST" enctype="multipart/form-data" action="{{ route('company.profile.archive.'.$archiveAction, $company->slug) }}" class="border rounded p-3 mb-3" data-backup-loading-form data-backup-action="{{ $archiveAction }}" data-backup-bytes="{{ $archiveSummary['bytes'] ?? 0 }}">
                    @csrf
                    <h5>{{ $archiveLabels[$archiveAction] }}</h5>
                    @if($archiveAction === 'prepare')
                        <p>Create a separate archive password and keep it safe. A forgotten password cannot be recovered.</p>
                    @elseif($archiveAction === 'download')
                        <p>Choose your pendrive in the browser's Save dialog. Keep a second safe copy before removing company data.</p>
                    @elseif($archiveAction === 'remove')
                        <p>Business data for every workspace listed above will be removed from the ERP. Restore requires this file and password. AWS backups and S3 object versions are not erased.</p>
                        <label for="archive-remove-name">Type {{ $company->name }} to confirm</label><input id="archive-remove-name" name="company_confirmation" class="form-control mb-3" autocomplete="off" required>
                        <label class="d-block mb-3"><input type="checkbox" name="second_copy" value="1" required> I have a second safe copy and understand that company business data will be removed.</label>
                    @elseif($archiveAction === 'restore')
                        <p>Select the current archive from your pendrive. Conflicting records/files will not be overwritten. Older database versions require a reviewed compatibility conversion.</p>
                    @endif
                    @if(in_array($archiveAction, ['verify', 'remove', 'restore']))
                        <label for="archive-{{ $archiveAction }}-file">Saved archive file</label><input id="archive-{{ $archiveAction }}-file" type="file" name="archive_file" accept=".zip" class="form-control mb-3" required>
                    @endif
                    @if(in_array($archiveAction, ['prepare', 'verify', 'remove', 'restore']))
                        <label for="archive-{{ $archiveAction }}-password">{{ $archiveAction === 'prepare' ? 'New archive password (at least 12 characters)' : 'Archive password' }}</label>
                        <input id="archive-{{ $archiveAction }}-password" type="password" name="password" class="form-control mb-3" autocomplete="{{ $archiveAction === 'prepare' ? 'new-password' : 'off' }}" @if($archiveAction === 'prepare') minlength="12" @endif required>
                    @endif
                    @if($archiveAction === 'prepare')
                        <label for="archive-password-confirm">Confirm archive password</label><input id="archive-password-confirm" type="password" name="password_confirmation" class="form-control mb-3" autocomplete="new-password" minlength="12" required>
                    @endif
                    <label for="archive-{{ $archiveAction }}-current">Current account password</label><input id="archive-{{ $archiveAction }}-current" type="password" name="current_password" class="form-control mb-3" autocomplete="current-password" required>
                    <button type="submit" class="btn {{ $archiveAction === 'remove' ? 'btn-danger' : ($archiveAction === 'cancel' ? 'btn-secondary' : 'btn-primary') }}">
                        <span class="backup-button-loader" data-backup-spinner aria-hidden="true" hidden></span>
                        <span data-backup-button-label role="status" aria-live="polite">{{ $archiveLabels[$archiveAction] }}</span>
                    </button>
                    <p data-backup-feedback class="backup-download-feedback mt-3 mb-0" role="alert" hidden></p>
                </form>
            @endforeach
        @endif
    </div>
</section>
@push('styles')
<style>
    #company-archive .backup-button-loader {
        display: inline-block;
        width: 14px;
        height: 14px;
        margin-right: 8px;
        vertical-align: -2px;
        border: 2px solid rgba(255, 255, 255, .4);
        border-top-color: #fff;
        border-radius: 50%;
        animation: backup-button-spin .7s linear infinite;
    }
    #company-archive .backup-button-loader[hidden] { display: none; }
    #company-archive .backup-download-feedback { color: #ffb4b4; }
    @keyframes backup-button-spin { to { transform: rotate(360deg); } }
    @media (prefers-reduced-motion: reduce) {
        #company-archive .backup-button-loader { animation-duration: 2s; }
    }
</style>
@endpush
@push('scripts')
<script>
(() => {
    const section = document.getElementById('company-archive');
    if (!section) return;
    const messages = {
        prepare: 'Preparing backup…', download: 'Downloading archive…', verify: 'Verifying saved file…',
        remove: 'Archiving company data…', restore: 'Verifying and restoring…', cancel: 'Cancelling archive…'
    };
    const reset = form => {
        form.dataset.backupBusy = '0';
        form.removeAttribute('aria-busy');
        const button = form.querySelector('button[type="submit"]');
        const label = form.querySelector('[data-backup-button-label]');
        const spinner = form.querySelector('[data-backup-spinner]');
        if (button) button.disabled = false;
        if (spinner) spinner.hidden = true;
        if (label) label.textContent = label.dataset.originalLabel || label.textContent;
    };
    const startingNativeDownload = (form, message = 'Starting download…') => {
        const label = form.querySelector('[data-backup-button-label]');
        if (label) label.textContent = message;
        // Native large-file downloads expose no completion event. Show startup only;
        // the browser handles transfer progress without buffering a large ZIP in memory.
        let timer;
        const finish = () => { clearTimeout(timer); window.removeEventListener('focus', finish); reset(form); };
        window.addEventListener('focus', finish, { once: true });
        timer = setTimeout(finish, 15000);
    };
    section.addEventListener('submit', async event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-backup-loading-form]') || event.defaultPrevented) return;
        if (form.dataset.backupBusy === '1') {
            event.preventDefault();
            return;
        }
        if (!form.checkValidity()) return;
        const button = form.querySelector('button[type="submit"]');
        const label = form.querySelector('[data-backup-button-label]');
        const spinner = form.querySelector('[data-backup-spinner]');
        if (!button || !label || !spinner) return;
        const feedback = form.querySelector('[data-backup-feedback]');
        if (feedback) feedback.hidden = true;
        label.dataset.originalLabel = label.dataset.originalLabel || label.textContent;
        form.dataset.backupBusy = '1';
        form.setAttribute('aria-busy', 'true');
        button.disabled = true;
        spinner.hidden = false;
        label.textContent = messages[form.dataset.backupAction] || 'Processing…';
        if (form.dataset.backupAction !== 'download') return;

        const size = Number(form.dataset.backupBytes);
        const maxBufferedDownload = 32 * 1024 * 1024;
        if (!window.fetch || !window.URL || !window.URL.createObjectURL || !size || size > maxBufferedDownload) {
            startingNativeDownload(form);
            return;
        }
        event.preventDefault();
        try {
            const response = await fetch(form.action, {
                method: 'POST', body: new FormData(form), credentials: 'same-origin', cache: 'no-store',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const contentType = response.headers.get('Content-Type') || '';
            if (!response.ok || contentType.includes('application/json')) {
                let message = 'Download could not finish. Please check your password and try again.';
                if (contentType.includes('application/json')) {
                    const error = await response.json();
                    if (typeof error.message === 'string') message = error.message;
                }
                throw new Error(message);
            }
            const disposition = response.headers.get('Content-Disposition') || '';
            if (!/attachment/i.test(disposition)) throw new Error('Download could not start. Refresh this page and sign in again if needed.');
            const actualSize = Number(response.headers.get('Content-Length'));
            if (actualSize > maxBufferedDownload) {
                try { if (response.body) await response.body.cancel(); } catch (_) {}
                startingNativeDownload(form);
                HTMLFormElement.prototype.submit.call(form);
                return;
            }
            const blob = await response.blob();
            if (!blob.size || (actualSize > 0 && blob.size !== actualSize)) throw new Error('The download was incomplete. Please try again.');
            const encodedName = disposition.match(/filename\*=UTF-8''([^;]+)/i);
            const plainName = disposition.match(/filename="([^"]+)"|filename=([^;]+)/i);
            let filename = 'company-backup.zip';
            if (encodedName) { try { filename = decodeURIComponent(encodedName[1]); } catch (_) {} }
            else if (plainName) filename = (plainName[1] || plainName[2]).trim();
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename.split(/[\\/]/).pop() || 'company-backup.zip';
            document.body.appendChild(link); link.click(); link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 60000);
        } catch (error) {
            if (feedback) {
                feedback.textContent = error instanceof Error ? error.message : 'Download failed. Please try again.';
                feedback.hidden = false;
            }
        } finally { reset(form); }
    });
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        section.querySelectorAll('[data-backup-loading-form][data-backup-busy="1"]').forEach(reset);
    });
})();
</script>
@endpush
@endif
