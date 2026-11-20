<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CategoryPerson;
use App\Models\Company;
use App\Models\CompanyPlanRenewal;
use App\Models\User;
use App\Services\CompanyPurgeService;
use App\Services\CompanyPlanService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Yajra\DataTables\Facades\DataTables;
use App\Mail\CompanyLoginMail;
use Illuminate\Support\Facades\DB;


class CompanyController extends Controller
{
    private const PAID_PLAN_YEARS = [
        'yearly' => 1,
        '2_years' => 2,
        '3_years' => 3,
        '4_years' => 4,
        '5_years' => 5,
    ];

    /*
    ======================================================
    LIST PAGE + DATATABLE
    ======================================================
    */
    public function index(Request $request)
    {
        if ($request->ajax()) {

            $companies = Company::query()
                ->when(\App\Services\CompanyBranches::ready(), function ($query) {
                    $query->whereNull('parent_company_id')
                        ->with(['branches' => fn ($branches) => $branches->withCount('users')->orderBy('name')]);
                })
                ->withCount('users')
                ->withCount(['planRenewals as plan_renewals_count'])
                ->with(['users' => function ($query) {
                    $query->select('id', 'company_id', 'password_set_url', 'password_changed', 'is_active', 'role')
                        ->addSelect([
                            'password_setup_expires_at' => DB::table('password_set_tokens')
                                ->select('expires_at')
                                ->whereColumn('user_id', 'users.id')
                                ->whereNull('used_at')
                                ->orderByDesc('id')
                                ->limit(1),
                        ])
                        ->where(function ($q) {
                            $q->where('role', 'admin')
                                ->orWhereHas('roles', function ($roleQuery) {
                                    $roleQuery->where('name', 'company_admin');
                                });
                        });
                }])
                ->orderByDesc('id');

            return DataTables::of($companies)

                ->addIndexColumn()
                ->editColumn('name', function ($row) {
                    return view('superadmin.auth.company.branch-list', [
                        'company' => $row,
                        'parentActive' => $this->companyEffectiveStatus($row),
                    ])->render();
                })
                ->addColumn('password_set_url', function ($row) {
                    $adminUser = $row->users->first();
                    $copyButton = '<button type="button" class="btn btn-sm btn-info" disabled>No setup link</button>';

                    if ($adminUser && $adminUser->password_changed) {
                        $copyButton = '<button type="button" class="btn btn-sm btn-info" disabled>Password already set</button>';
                    } elseif ($adminUser && $adminUser->password_set_url && $adminUser->password_setup_expires_at) {
                        $expiresAt = Carbon::parse($adminUser->password_setup_expires_at);
                        if ($expiresAt->isFuture()) {
                            $copyButton = '<button type="button" class="btn btn-sm btn-info copyBtn" data-url="' . e($adminUser->password_set_url) . '">Copy Link</button>'
                                . '<small class="setup-link-expiry">Expires: ' . e($expiresAt->format('d-m-Y h:i A')) . '</small>';
                        } else {
                            $copyButton = '<button type="button" class="btn btn-sm btn-danger" disabled>Link expired</button>'
                                . '<small class="setup-link-expiry">Expired: ' . e($expiresAt->format('d-m-Y h:i A')) . '</small>';
                        }
                    } elseif ($adminUser && $adminUser->password_set_url) {
                        $copyButton = '<button type="button" class="btn btn-sm btn-danger" disabled>Link status unknown</button>';
                    }

                    return '<div class="login-setup-actions">'
                        . $copyButton
                        . '<form method="POST" action="' . route('superadmin.companies.regenerate-password-link', $row->id) . '" onsubmit="return confirm(\'Generate a fresh 24-hour password setup link? The old link will stop working.\')">'
                        . csrf_field()
                        . '<button type="submit" class="btn btn-sm btn-warning">Generate New Link</button>'
                        . '</form></div>';
                })
                ->addColumn('logo', function ($row) {
                    $defaultLogo = asset('celestial/assets/images/ornasphere-auth-logo.png') . '?v=' . @filemtime(public_path('celestial/assets/images/ornasphere-auth-logo.png'));
                    $src = $row->company_logo_url ?: $defaultLogo;
                    if ($row->company_logo && !str_contains($src, '?')) {
                        $src .= '?v=' . optional($row->updated_at)->timestamp;
                    }
                    return '<img src="' . e($src) . '" alt="logo" style="height:40px;width:40px;object-fit:cover;border-radius:6px;" onerror="this.onerror=null;this.src=\'' . e($defaultLogo) . '\';">';
                })
                ->addColumn('plan_status', function ($row) {
                    $status = CompanyPlanService::status($row);
                    $badgeClass = $status['expired'] ? 'badge-danger' : 'badge-success';
                    $label = $row->plan === 'trial' ? ($status['expired'] ? 'Trial Expired' : 'Trial - '.$status['days_remaining'].' days left') : ($status['expired'] ? 'Plan Expired' : 'Paid - Active');

                    return '
                        <div class="plan-status-cell">
                            <span class="badge ' . $badgeClass . '">' . $label . '</span>
                            <small>Expire: ' . e($status['expires_at_view'] ?: '-') . '</small>
                            <small>Renewed: ' . (int) $row->plan_renewals_count . ' time(s)</small>
                        </div>
                    ';
                })

                ->filterColumn('users_count', function ($query, $keyword) {
                    $query->whereHas('users', function ($q) use ($keyword) {
                        $q->where('name', 'like', "%{$keyword}%");
                    });
                })

                ->addColumn('status', function ($row) {
                    $checked = $this->companyEffectiveStatus($row) ? 'checked' : '';

                    return '
                        <div class="form-check form-switch" style="margin-left:40px;">
                            <input class="form-check-input toggleStatus"
                                type="checkbox"
                                data-id="' . $row->id . '"
                                ' . $checked . '>
                        </div>
                    ';
                })
                ->addColumn('action', function ($row) {
                    $planStatus = CompanyPlanService::status($row);
                    $effectiveStatus = $this->companyEffectiveStatus($row);

                    return '
                        <a href="' . route('superadmin.companies.edit', $row->id) . '"
                        class="btn btn-sm btn-primary">Edit</a>

                        <button type="button"
                                class="btn btn-sm btn-success renewPlanBtn"
                                data-url="' . route('superadmin.companies.renew-plan', $row->id) . '"
                                data-name="' . e($row->name) . '"
                                data-expiry="' . e($planStatus['expires_at_view'] ?: '-') . '">
                            Renew Plan
                        </button>

                        <a href="' . url('superadmin/companies/' . $row->id . '/plan-history') . '"
                           class="btn btn-sm btn-info">
                            History
                        </a>

                        
                        <form method="POST"
                            action="' . route('superadmin.companies.reset-2fa', $row->id) . '"
                            style="display:inline"
                            onsubmit="return confirm(\'Reset 2FA for this company?\')">
                            ' . csrf_field() . '
                            <button class="btn btn-sm btn-secondary">
                                Reset 2FA
                            </button>
                        </form>
                        <button type="button"
                                data-id="' . $row->id . '"
                                data-name="' . e($row->name) . '"
                                data-active="' . (int) $effectiveStatus . '"
                                data-url="' . route('superadmin.companies.destroy', $row->id) . '"
                                class="btn btn-sm btn-danger deleteBtn">
                            Delete
                        </button>
                    ';
                })



                ->rawColumns(['name', 'password_set_url', 'logo', 'plan_status', 'action', 'status'])
                ->make(true);
        }

        return view('superadmin.auth.company.index');
    }

    public function validityReport(Request $request)
    {
        [$filters, $rows] = $this->validityReportData($request);

        return view('superadmin.auth.company.validity-report', compact('filters', 'rows'));
    }

    public function validityReportExcel(Request $request)
    {
        [, $rows] = $this->validityReportData($request);
        $exportRows = [[
            'Company / Branch', 'Type', 'Parent Company', 'Branches', 'Company Email', 'Admin Name', 'Admin Email',
            'Users', 'Active Users', 'User Limit', 'Plan', 'Plan Start', 'Plan Expiry', 'Plan Validity',
            'Company Access', 'City', 'State', 'Postcode', 'Country', 'Created At',
        ]];

        foreach ($rows as $row) {
            $exportRows[] = [
                $row['name'], $row['type'], $row['parent_name'], $row['branch_count'], $row['email'], $row['admin_name'], $row['admin_email'],
                $row['users'], $row['active_users'], $row['user_limit'], $row['plan'], $row['started_at'],
                $row['expires_at'], $row['validity'], $row['company_access'], $row['city'], $row['state'],
                $row['postcode'], $row['country'], $row['created_at'],
            ];
        }

        return \App\Services\ReportXlsx::download($exportRows, 'company_validity_report.xlsx', 'Company Validity');
    }

    public function validityReportPdf(Request $request)
    {
        [$filters, $rows] = $this->validityReportData($request);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('superadmin.auth.company.validity-report-pdf', [
            'filters' => $filters,
            'rows' => $rows,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape')->download('company_validity_report.pdf');
    }

    private function validityReportData(Request $request): array
    {
        $filters = $request->validate([
            'plan' => ['nullable', 'string', \Illuminate\Validation\Rule::in(array_merge(['trial', 'custom'], array_keys(self::PAID_PLAN_YEARS)))],
            'validity' => ['nullable', 'string', \Illuminate\Validation\Rule::in(['active', 'expired'])],
            'expiry_from' => ['nullable', 'date_format:Y-m-d'],
            'expiry_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:expiry_from'],
        ]);

        $companies = Company::query()
            ->withCount([
                'users',
                'users as active_users_count' => fn ($query) => $query->where('is_active', true),
                'branches',
            ])
            ->with([
                'moduleParent:id,name,plan,plan_started_at,plan_expires_at',
                'users' => function ($query) {
                    $query->select('id', 'company_id', 'name', 'email', 'role')
                        ->where(function ($userQuery) {
                            $userQuery->where('role', 'admin')
                                ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'company_admin'));
                        })
                        ->orderBy('id');
                },
            ])
            // Show the newest company or branch first in the report and exports.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $rows = $companies->map(function (Company $company) {
            $effectiveCompany = $company->moduleParent ?: $company;
            $planStatus = CompanyPlanService::status($effectiveCompany);
            $adminUser = $company->users->first();
            $expiryDate = $planStatus['expires_at'] ? Carbon::parse($planStatus['expires_at'])->toDateString() : null;

            return [
                'name' => $company->name,
                'type' => $company->parent_company_id ? 'Branch' : 'Company',
                'parent_name' => $company->moduleParent?->name ?: '-',
                'branch_count' => $company->parent_company_id ? '-' : (int) $company->branches_count,
                'email' => $company->email ?: '-',
                'admin_name' => $adminUser?->name ?: '-',
                'admin_email' => $adminUser?->email ?: '-',
                'users' => (int) $company->users_count,
                'active_users' => (int) $company->active_users_count,
                'user_limit' => (int) $company->max_users,
                'plan' => $effectiveCompany->plan ? ucwords(str_replace('_', ' ', $effectiveCompany->plan)) : '-',
                'plan_value' => (string) ($effectiveCompany->plan ?? ''),
                'started_at' => $planStatus['started_at_view'] ?: '-',
                'expires_at' => $planStatus['expires_at_view'] ?: '-',
                'expiry_date' => $expiryDate,
                'validity' => $planStatus['expired'] ? 'Expired' : 'Active',
                'validity_value' => $planStatus['expired'] ? 'expired' : 'active',
                'company_access' => $company->status ? 'Active' : 'Inactive',
                'city' => $company->city ?: '-',
                'state' => $company->state ?: '-',
                'postcode' => $company->postcode ?: '-',
                'country' => $company->country ?: '-',
                'created_at' => optional($company->created_at)->format('d-m-Y h:i A') ?: '-',
            ];
        })->filter(function (array $row) use ($filters) {
            if (!empty($filters['plan']) && $row['plan_value'] !== $filters['plan']) return false;
            if (!empty($filters['validity']) && $row['validity_value'] !== $filters['validity']) return false;
            if (!empty($filters['expiry_from']) && (!$row['expiry_date'] || $row['expiry_date'] < $filters['expiry_from'])) return false;
            if (!empty($filters['expiry_to']) && (!$row['expiry_date'] || $row['expiry_date'] > $filters['expiry_to'])) return false;
            return true;
        })->values();

        return [$filters, $rows];
    }

    private function companyEffectiveStatus(Company $company): bool
    {
        // Company access and individual user access are independent settings.
        return (bool) $company->status;
    }

    private function resolvePlanDates(string $plan, Request $request, ?Company $existingCompany = null): array
    {
        if ($plan === 'custom') {
            return [
                'started_at' => Carbon::parse($request->input('plan_start_date'))->startOfDay(),
                'expires_at' => Carbon::parse($request->input('plan_end_date'))->endOfDay(),
            ];
        }

        // Saving unrelated company details must not restart the current plan clock.
        if ($existingCompany && $existingCompany->plan === $plan
            && $existingCompany->plan_started_at && $existingCompany->plan_expires_at) {
            return [
                'started_at' => $existingCompany->plan_started_at,
                'expires_at' => $existingCompany->plan_expires_at,
            ];
        }

        $startedAt = now();
        if ($plan === 'trial') {
            return [
                'started_at' => $startedAt,
                'expires_at' => $startedAt->copy()->addDays(10),
            ];
        }

        $years = self::PAID_PLAN_YEARS[$plan] ?? 1;
        $expiresAt = $startedAt->copy();
        for ($year = 0; $year < $years; $year++) {
            $expiresAt->addYearNoOverflow();
        }

        return ['started_at' => $startedAt, 'expires_at' => $expiresAt];
    }

    private function companyAdminUsersQuery(Company $company)
    {
        return User::where('company_id', $company->id)
            ->where(function ($query) {
                $query->where('role', 'admin')
                    ->orWhereHas('roles', function ($roleQuery) {
                        $roleQuery->where('name', 'company_admin');
                    });
            });
    }

    public function planHistory(Company $company)
    {
        $company->loadCount('users');

        $renewals = $company->planRenewals()
            ->with('renewedBy:id,name,email')
            ->latest()
            ->paginate(15);

        $planStatus = CompanyPlanService::status($company);

        return view('superadmin.auth.company.plan-history', compact('company', 'renewals', 'planStatus'));
    }

    public function create()
    {
        return view('superadmin.auth.company.create');
    }
    /*
    ======================================================
    STORE COMPANY
    ======================================================
    */

    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|unique:companies,name',
            'admin_name' => 'nullable|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'max_users' => 'required|integer|min:1',
            'plan'      => ['required', 'string', \Illuminate\Validation\Rule::in(array_merge(['trial', 'custom'], array_keys(self::PAID_PLAN_YEARS)))],
            'plan_start_date' => ['nullable', 'required_if:plan,custom', 'date_format:Y-m-d'],
            'plan_end_date' => ['nullable', 'required_if:plan,custom', 'date_format:Y-m-d', 'after_or_equal:plan_start_date'],
            'status'    => 'nullable|boolean',
            'company_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'wholesale_price_enabled' => ['sometimes', 'boolean'],
            'allowed_modules' => ['nullable', 'array'],
            'allowed_modules.*' => ['string', 'distinct', \Illuminate\Validation\Rule::in(array_keys(config('company_modules')))],
        ]);

        $planDates = $this->resolvePlanDates($request->plan, $request);
        $creationStage = 'begin_transaction';
        try {
            DB::beginTransaction();
            $creationStage = 'prepare_company';
            /*
        -------------------------
        Create Company
        -------------------------
        */
            $slug = Str::slug($request->name) . '-' . rand(100, 999);

            $logoPath = null;
            if ($request->hasFile('company_logo')) {
                $file = $request->file('company_logo');
                $fileName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
                $creationStage = 'upload_company_logo';
                $logoPath = $this->storeCompanyLogo($file, $fileName);
            }

            $creationStage = 'create_company';
            $company = Company::create([
                'wholesale_price_enabled' => $request->boolean('wholesale_price_enabled'),
                'allowed_modules' => $request->input('allowed_modules', []),
                'name'       => $request->name,
                'slug'       => $slug,
                'email'      => $request->email,
                'company_logo' => $logoPath,
                'max_users'  => $request->max_users,
                'plan'       => $request->plan,
                'plan_started_at' => $planDates['started_at'],
                'plan_expires_at' => $planDates['expires_at'],

                // Address fields
                'address_1'  => $request->address_1,
                'address_2'  => $request->address_2,
                'city'       => $request->city,
                'state'      => $request->state,
                'postcode'   => $request->postcode,
                'country'    => $request->country,

                'status'     => $request->boolean('status'),
            ]);

            $creationStage = 'create_default_categories';
            CategoryPerson::ensureCompanyDefaults((int) $company->id);

            /*
        -------------------------
        Create Default Admin User
        -------------------------
        */
            $creationStage = 'create_admin_user';
            $tempPassword = Str::password(10);

            $user = User::create([
                'name'             => $request->filled('admin_name') ? $request->admin_name : $company->name . ' Admin',
                'email'            => $request->email,
                'password'         => Hash::make($tempPassword),
                'company_id'       => $company->id,
                'role'             =>'admin',
                'password_changed' => false,
            ]);

            // Assign role
            $creationStage = 'assign_admin_role';
            $user->assignRole('company_admin');

            /*
        -------------------------
        Create Password Set Token
        -------------------------
        */

            $creationStage = 'create_password_setup_token';
            $token = Str::uuid()->toString();

            // Generate full URL
            $setPasswordUrl = url('/set-password/' . $token);

            // Save full URL in users table
            $user->password_set_url = $setPasswordUrl;
            $user->save();


            DB::table('password_set_tokens')->insert([
                'user_id'    => $user->id,
                'token'      => $token,
                'expires_at' => now()->addHours(24),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /*
        -------------------------
        Send Email (Queued)
        -------------------------
        */
            $creationStage = 'queue_login_email';
            Mail::to($user->email)
                ->queue(new CompanyLoginMail($company, $user, $token));

            $creationStage = 'commit_transaction';
            DB::commit();

            $creationStage = 'write_audit_log';
            AuditLog::logEvent(
                'create',
                'company',
                'Created company "' . $company->name . '" (ID: ' . $company->id . ') with admin user ID ' . $user->id,
                [
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                ]
            );

            return redirect()
                ->route('superadmin.companies.index')
                ->with('success', 'Company created. Login email sent successfully.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Company creation failed', [
                'stage' => $creationStage,
                'plan' => $request->input('plan'),
                'actor_id' => $request->user()?->id,
                'exception' => $e,
            ]);
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return back()->withErrors([
                'error' => $creationStage === 'upload_company_logo'
                    ? 'Company logo could not be uploaded. Please ask the server administrator to check the company logo upload folder permissions and the error log.'
                    : 'Something went wrong. Please try again.',
            ]);
        }
    }



    /*
    ======================================================
    EDIT COMPANY
    ======================================================
    */
    public function edit(Company $company)
    {
        $adminUser = $company->users()
            ->whereHas('roles', function ($q) {
                $q->where('name', 'company_admin');
            })
            ->first();

        if (!$adminUser) {
            $adminUser = $company->users()->orderBy('id')->first();
        }

        return view('superadmin.auth.company.edit', compact('company', 'adminUser'));
    }


    /*
    ======================================================
    UPDATE COMPANY
    ======================================================
    */

    public function update(Request $request, Company $company)
    {
        $request->validate([
            'name'       => 'required|string|max:255|unique:companies,name,' . $company->id,
            'admin_name' => 'nullable|string|max:255',
            'email'      => 'required|email|max:255',
            'max_users'  => 'required|integer|min:1',
            'plan'       => ['required', 'string', \Illuminate\Validation\Rule::in(array_merge(['trial', 'custom'], array_keys(self::PAID_PLAN_YEARS)))],
            'plan_start_date' => ['nullable', 'required_if:plan,custom', 'date_format:Y-m-d'],
            'plan_end_date' => ['nullable', 'required_if:plan,custom', 'date_format:Y-m-d', 'after_or_equal:plan_start_date'],
            'status'     => 'nullable|boolean',
            'company_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'wholesale_price_enabled' => ['sometimes', 'boolean'],
            'allowed_modules' => ['nullable', 'array'],
            'allowed_modules.*' => ['string', 'distinct', \Illuminate\Validation\Rule::in(array_keys(config('company_modules')))],
            'admin_password' => 'nullable|string|min:6|confirmed',
        ]);

        $planDates = $this->resolvePlanDates($request->plan, $request, $company);

        $logoPath = $company->company_logo;
        if ($request->hasFile('company_logo')) {
            $file = $request->file('company_logo');
            $fileName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $logoPath = $this->storeCompanyLogo($file, $fileName);

            foreach ($this->companyLogoAbsolutePaths($company->company_logo) as $oldLogoPath) {
                if (File::exists($oldLogoPath)) {
                    File::delete($oldLogoPath);
                }
            }
        }

        $company->update([
            'wholesale_price_enabled' => $request->has('wholesale_price_enabled') ? $request->boolean('wholesale_price_enabled') : $company->wholesale_price_enabled,
            'allowed_modules' => $request->has('modules_present') ? $request->input('allowed_modules', []) : $company->allowed_modules,
            'name'       => $request->name,
            'email'      => $request->email,
            'company_logo' => $logoPath,
            'max_users'  => $request->max_users,
            'plan'       => $request->plan,
            'plan_started_at' => $planDates['started_at'],
            'plan_expires_at' => $planDates['expires_at'],
            'status'     => $request->boolean('status'),

            'address_1'  => $request->address_1,
            'address_2'  => $request->address_2,
            'city'       => $request->city,
            'state'      => $request->state,
            'postcode'   => $request->postcode,
            'country'    => $request->country,
        ]);

        $adminUser = User::where('company_id', $company->id)
            ->whereHas('roles', function ($q) {
                $q->where('name', 'company_admin');
            })
            ->first();

        if (!$adminUser) {
            $adminUser = User::where('company_id', $company->id)->orderBy('id')->first();
        }

        if ($adminUser && $request->filled('admin_name')) {
            $adminUser->update([
                'name' => (string) $request->admin_name,
            ]);
        }

        if ($request->filled('admin_password')) {
            if ($adminUser) {
                $adminUser->update([
                    'password' => Hash::make((string) $request->admin_password),
                    'password_changed' => false,
                    'password_set_url' => null,
                ]);

                AuditLog::logEvent(
                    'reset_password',
                    'company',
                    'Super admin reset password for company "' . $company->name . '" admin user ID ' . $adminUser->id,
                    [
                        'company_id' => $company->id,
                        'user_id' => $adminUser->id,
                    ]
                );
            }
        }

        AuditLog::logEvent(
            'update',
            'company',
            'Updated company "' . $company->name . '" (ID: ' . $company->id . ')',
            ['company_id' => $company->id]
        );

        return redirect()
            ->route('superadmin.companies.index')
            ->with('success', 'Company updated successfully');
    }



    /*
    ======================================================
    DELETE COMPANY
    ======================================================
    */
    public function destroy(Request $request, Company $company, CompanyPurgeService $companyPurgeService)
    {
        $expectsJson = $request->expectsJson() || $request->ajax();
        if ($expectsJson && (string) $request->input('confirm_name') !== (string) $company->name
            && !$this->companyEffectiveStatus($company)) {
            return response()->json(['success' => false, 'message' => 'Company name confirmation does not match.'], 422);
        }

        $company->load(['users' => function ($query) {
            $query->select('id', 'company_id', 'password_set_url', 'is_active', 'role')
                ->where(function ($q) {
                    $q->where('role', 'admin')
                        ->orWhereHas('roles', function ($roleQuery) {
                            $roleQuery->where('name', 'company_admin');
                        });
                });
        }]);

        if ($this->companyEffectiveStatus($company)) {
            $company->update(['status' => false]);
            $this->companyAdminUsersQuery($company)->update(['is_active' => false]);

            $company->users()->update([
                'active_web_session_id' => (string) Str::uuid(),
            ]);

            AuditLog::logEvent(
                'deactivate_before_delete',
                'company',
                'Deactivated company "' . $company->name . '" (ID: ' . $company->id . ') before permanent delete',
                ['company_id' => $company->id]
            );

            if ($expectsJson) {
                return response()->json([
                    'success' => true,
                    'action' => 'deactivated',
                    'message' => 'Company deactivated. Click Delete again to permanently remove it.',
                    'status' => false,
                ]);
            }

            return redirect()
                ->route('superadmin.companies.index')
                ->with('success', 'Company deactivated first. Click Delete again to permanently delete it.');
        }

        $companyId = $company->id;
        $companyName = $company->name;
        $summary = $companyPurgeService->purge($company);

        AuditLog::logEvent(
            'delete_permanent',
            'company',
            'Permanently deleted company "' . $companyName . '" (ID: ' . $companyId . '). Deleted rows from ' . count($summary['tables']) . ' table(s); files deleted: ' . $summary['files_deleted'],
            []
        );

        if ($expectsJson) {
            return response()->json([
                'success' => true,
                'action' => 'deleted',
                'message' => 'Company and all related data deleted permanently.',
            ]);
        }

        return redirect()
            ->route('superadmin.companies.index')
            ->with('success', 'Company and all related data deleted permanently');
    }

    public function toggleStatus(Company $company)
    {
        $company->load(['users' => function ($query) {
            $query->select('id', 'company_id', 'password_set_url', 'is_active', 'role')
                ->where(function ($q) {
                    $q->where('role', 'admin')
                        ->orWhereHas('roles', function ($roleQuery) {
                            $roleQuery->where('name', 'company_admin');
                        });
                });
        }]);

        $newStatus = !(bool) $company->status;

        $company->update([
            'status' => $newStatus
        ]);

        if (!$newStatus) {
            $ids = [$company->id];
            if (\App\Services\CompanyBranches::ready()) {
                $ids = array_merge($ids, Company::where('parent_company_id', $company->id)->pluck('id')->all());
            }
            foreach (User::whereIn('company_id', $ids)->get() as $user) {
                $user->forceFill(['active_web_session_id' => (string) Str::uuid(), 'remember_token' => null])->save();
                \App\Services\InactiveTokenResponse::revoke($user);
            }
        }

        AuditLog::logEvent(
            'toggle_status',
            'company',
            'Company "' . $company->name . '" (ID: ' . $company->id . ') status changed to ' . ($newStatus ? 'active' : 'inactive'),
            ['company_id' => $company->id]
        );

        return response()->json([
            'success' => true,
            'status'  => $newStatus
        ]);
    }

    public function renewPlan(Request $request, Company $company)
    {
        abort_if($company->parent_company_id, 422, 'Renew the main company plan. Branches inherit its expiry date.');
        $request->validate([
            'plan' => ['required', 'string', \Illuminate\Validation\Rule::in(array_merge(['trial', 'custom'], array_keys(self::PAID_PLAN_YEARS)))],
            'plan_start_date' => ['nullable', 'required_if:plan,custom', 'date_format:Y-m-d'],
            'plan_end_date' => ['nullable', 'required_if:plan,custom', 'date_format:Y-m-d', 'after_or_equal:plan_start_date'],
            'remarks' => 'nullable|string|max:1000',
        ]);

        $planStatus = CompanyPlanService::status($company);
        $oldExpiresAt = $planStatus['expires_at'] ? Carbon::parse($planStatus['expires_at']) : null;
        $renewalPlan = $request->plan;

        if ($renewalPlan === 'custom') {
            $newStartedAt = Carbon::parse($request->plan_start_date)->startOfDay();
            $newExpiresAt = Carbon::parse($request->plan_end_date)->endOfDay();
        } elseif ($renewalPlan === 'trial') {
            $newStartedAt = now();
            $newExpiresAt = $newStartedAt->copy()->addDays(10);
        } else {
            $extendsActivePaidPlan = $company->plan !== 'trial' && $oldExpiresAt && $oldExpiresAt->isFuture();
            $newStartedAt = $extendsActivePaidPlan
                ? Carbon::parse($company->plan_started_at ?: $planStatus['started_at'])
                : now();
            $baseDate = $extendsActivePaidPlan ? $oldExpiresAt->copy() : now();
            $years = self::PAID_PLAN_YEARS[$renewalPlan] ?? 1;
            $newExpiresAt = $baseDate->copy();
            for ($year = 0; $year < $years; $year++) {
                $newExpiresAt->addYearNoOverflow();
            }
        }

        DB::transaction(function () use ($company, $oldExpiresAt, $newStartedAt, $newExpiresAt, $renewalPlan, $request) {
            $company->update([
                'plan' => $renewalPlan,
                'plan_started_at' => $newStartedAt,
                'plan_expires_at' => $newExpiresAt,
                'plan_renewed_at' => now(),
            ]);

            CompanyPlanRenewal::create([
                'company_id' => $company->id,
                'old_expires_at' => $oldExpiresAt,
                'new_expires_at' => $newExpiresAt,
                'renewed_by' => auth('superadmin')->id(),
                'remarks' => $request->remarks,
            ]);
        });

        AuditLog::logEvent(
            'renew_plan',
            'company',
            'Renewed company "' . $company->name . '" on the ' . str_replace('_', ' ', $renewalPlan) . ' plan until ' . $newExpiresAt->format('d-m-Y h:i A'),
            [
                'company_id' => $company->id,
                'plan' => $renewalPlan,
                'plan_started_at' => $newStartedAt->toDateTimeString(),
                'old_expires_at' => optional($oldExpiresAt)->toDateTimeString(),
                'new_expires_at' => $newExpiresAt->toDateTimeString(),
            ]
        );

        return redirect()
            ->back()
            ->with('success', 'Plan renewed as ' . ucwords(str_replace('_', ' ', $renewalPlan)) . ' until ' . $newExpiresAt->format('d-m-Y h:i A'));
    }

    public function regeneratePasswordLink(Company $company)
    {
        $user = $this->companyAdminUsersQuery($company)->orderBy('id')->first();

        if (!$user) {
            return back()->with('error', 'No admin user found.');
        }

        $token = Str::uuid()->toString();
        $setPasswordUrl = url('/set-password/' . $token);
        $expiresAt = now()->addHours(24);

        DB::transaction(function () use ($user, $token, $setPasswordUrl, $expiresAt) {
            // A newly generated link replaces any previous valid or expired token.
            DB::table('password_set_tokens')
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->update(['used_at' => now(), 'updated_at' => now()]);
            DB::table('password_set_tokens')->insert([
                'user_id' => $user->id,
                'token' => $token,
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $user->update([
                'password_set_url' => $setPasswordUrl,
                'password_changed' => false,
            ]);
        });

        AuditLog::logEvent(
            'regenerate_password_link',
            'company',
            'Generated a new 24-hour password setup link for company "' . $company->name . '" (ID: ' . $company->id . ') admin user ID ' . $user->id,
            [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'expires_at' => $expiresAt->toDateTimeString(),
            ]
        );

        return back()->with('success', 'A new 24-hour password setup link was generated. Use Copy Link to share it with the company admin.');
    }

    public function reset2fa(Company $company)
    {
        // Reset 2FA and force existing company sessions to log in again.
        $company->users()->update([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
            'two_factor_recovery_codes' => null,
            'active_web_session_id' => (string) Str::uuid(),
        ]);

        AuditLog::logEvent(
            'reset_2fa',
            'company',
            'Reset 2FA for all users of company "' . $company->name . '" (ID: ' . $company->id . ')',
            ['company_id' => $company->id]
        );

        return redirect()
            ->back()
            ->with('success', '2FA has been reset for this company.');
    }

    private function storeCompanyLogo($file, string $fileName): string
    {
        $relativePath = 'uploads/company_logos/' . $fileName;
        $uploadDir = public_path('uploads/company_logos');

        if (!$file->isValid()) {
            throw new \RuntimeException('Company logo upload is invalid: ' . $file->getErrorMessage());
        }
        File::ensureDirectoryExists($uploadDir, 0755, true);
        clearstatcache(true, $uploadDir);
        if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
            throw new \RuntimeException('Company logo directory is not writable by PHP: ' . $uploadDir);
        }

        $file->move($uploadDir, $fileName);
        $primaryPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

        // The public upload is authoritative; an optional legacy copy must not
        // prevent saving a successfully uploaded company logo.
        foreach ($this->companyLogoUploadDirs() as $mirrorDir) {
            if ($mirrorDir === $uploadDir) {
                continue;
            }
            try {
                File::ensureDirectoryExists($mirrorDir, 0755, true);
                if (!File::copy($primaryPath, $mirrorDir . DIRECTORY_SEPARATOR . $fileName)) {
                    throw new \RuntimeException('Unable to copy company logo to legacy directory.');
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Company logo legacy copy failed; public logo saved', [
                    'directory' => $mirrorDir,
                    'exception' => $e,
                ]);
            }
        }

        return $relativePath;
    }

    private function companyLogoUploadDirs(): array
    {
        return array_values(array_unique(array_filter([
            public_path('uploads/company_logos'),
            base_path('uploads/company_logos'),
        ])));
    }

    private function companyLogoAbsolutePaths(?string $path): array
    {
        $path = ltrim((string) $path, '/');
        if ($path === '') {
            return [];
        }

        $paths = [
            public_path($path),
            base_path($path),
        ];

        if (str_starts_with($path, 'public/')) {
            $paths[] = public_path(substr($path, 7));
            $paths[] = base_path(substr($path, 7));
        }

        return array_values(array_unique($paths));
    }
}
