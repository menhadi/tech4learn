@extends('layouts.master')

@section('title', 'SaaS Control Center')

@section('content')
@php
    $tenant = $currentOrganization ?? null;
    $organizationUsers = \Illuminate\Support\Facades\DB::table('organization_users')
        ->join('organizations', 'organizations.id', '=', 'organization_users.organization_id')
        ->join('users', 'users.id', '=', 'organization_users.user_id')
        ->select(
            'organizations.name as organization_name',
            'users.name as user_name',
            'users.id as user_id',
            'users.email as user_email',
            'organization_users.role',
            'organization_users.status'
        )
        ->orderBy('organizations.name')
        ->get();
@endphp

<div class="el-page">
    <div class="el-page-header">
        <div class="el-page-header-main">
            <span class="el-page-kicker">Platform Admin</span>
            <h1 class="el-page-title">SaaS Control Center</h1>
            <p class="el-page-subtitle">Manage organizations, plans, ownership, and readiness from one place.</p>
            <div class="el-row-meta">
                Current tenant: <strong>{{ $tenant?->name ?? 'Not resolved' }}</strong>
                <span class="mx-1">|</span>
                Host: <strong>{{ request()->getHost() }}</strong>
            </div>
        </div>
        <div class="el-action-bar">
            <a href="{{ route('saas.question-sharing.index') }}" class="btn el-btn-soft el-btn-icon">
                <i class="ri-share-forward-line"></i> Question Sharing
            </a>
            <button class="btn el-btn-soft el-btn-icon" data-bs-toggle="modal" data-bs-target="#createPlatformAdminModal">
                <i class="ri-shield-user-line"></i> Add Super Admin
            </button>
            <button class="btn el-btn-primary el-btn-icon" data-bs-toggle="modal" data-bs-target="#createOrganizationModal">
                <i class="ri-building-4-line"></i> Add Organization
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            {!! implode('<br>', $errors->all()) !!}
        </div>
    @endif

    <div class="el-stats-grid">
        <div class="el-stat">
            <div>
                <div class="el-stat-label">Organizations</div>
                <div class="el-stat-value">{{ $organizations->count() }}</div>
                <div class="el-stat-note">All tenants</div>
            </div>
            <span class="el-stat-icon"><i class="ri-building-line"></i></span>
        </div>
        <div class="el-stat">
            <div>
                <div class="el-stat-label">Active</div>
                <div class="el-stat-value">{{ $organizations->where('status', 'active')->count() }}</div>
                <div class="el-stat-note">Live organizations</div>
            </div>
            <span class="el-stat-icon"><i class="ri-checkbox-circle-line"></i></span>
        </div>
        <div class="el-stat">
            <div>
                <div class="el-stat-label">Plans</div>
                <div class="el-stat-value">{{ $plans->count() }}</div>
                <div class="el-stat-note">Active plans</div>
            </div>
            <span class="el-stat-icon"><i class="ri-price-tag-3-line"></i></span>
        </div>
        <div class="el-stat">
            <div>
                <div class="el-stat-label">Default Org</div>
                <div class="el-stat-value fs-5">{{ $defaultOrganization?->name ?? 'Not Set' }}</div>
                <div class="el-stat-note">Platform website owner</div>
            </div>
            <span class="el-stat-icon"><i class="ri-home-gear-line"></i></span>
        </div>
    </div>
    <div class="el-panel">
        <div class="el-panel-header">
            <div>
                <h2 class="el-panel-title">Platform Super Admins</h2>
                <p class="el-panel-subtitle">Accounts with unrestricted platform and tenant access. Each admin changes their own email or password from Profile, or uses Forgot Password.</p>
            </div>

        </div>
        <div class="el-table-wrap">
            <table class="table table-hover align-middle el-table">
                <thead><tr><th>Name</th><th>Login Email</th><th>Status</th><th>Access</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse($platformAdmins as $platformAdmin)
                        <tr>
                            <td><strong>{{ $platformAdmin->name }}</strong>@if(auth()->id() === $platformAdmin->id)<span class="badge el-badge-soft ms-2">You</span>@endif</td>
                            <td>
                                <span>{{ $platformAdmin->email }}</span>
                                <button type="button" class="btn btn-sm btn-link p-1 ms-1" data-copy-email="{{ $platformAdmin->email }}" title="Copy login email" aria-label="Copy login email"><i class="ri-file-copy-line"></i></button>
                            </td>
                            <td><span class="el-status {{ $platformAdmin->status === 'Active' ? 'el-status-success' : 'el-status-muted' }}">{{ $platformAdmin->status }}</span></td>
                            <td><span class="el-status el-status-success">Full platform access</span></td>
                            <td>
                                <button type="button" class="btn btn-sm el-btn-soft" data-edit-admin-email data-user-id="{{ $platformAdmin->id }}" data-email="{{ $platformAdmin->email }}" data-bs-toggle="modal" data-bs-target="#editAdministratorEmailModal">
                                    <i class="ri-mail-settings-line me-1"></i> Edit Email
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="el-empty-state">No flagged platform super admins found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="el-panel">
        <div class="el-panel-header">
            <div>
                <h2 class="el-panel-title">Institute Leads</h2>
                <p class="el-panel-subtitle">Requests submitted from the public For Institutes page.</p>
            </div>
        </div>
        <div class="el-filter-bar" data-table-controls="saasLeads">
            <div class="el-filter-field">
                <label for="saasLeadsSearch">Search</label>
                <input id="saasLeadsSearch" type="text" class="form-control" data-el-search placeholder="Search institute, contact, email, plan">
            </div>
            <div class="el-filter-field">
                <label for="saasLeadsSize">Show</label>
                <select id="saasLeadsSize" class="form-select" data-el-size>
                    <option value="50">50 rows</option>
                    <option value="100">100 rows</option>
                    <option value="500">500 rows</option>
                    <option value="all">All rows</option>
                </select>
            </div>
        </div>
        <div class="el-table-wrap">
            <table class="table table-hover align-middle el-table" data-el-table="saasLeads">
                <thead>
                    <tr>
                        <th>Institute</th>
                        <th>Contact</th>
                        <th>Plan</th>
                        <th>Students</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Received</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($saasLeads as $lead)
                        <tr data-search="{{ strtolower($lead->institute_name . ' ' . $lead->contact_name . ' ' . $lead->email . ' ' . $lead->phone . ' ' . $lead->preferred_plan . ' ' . $lead->institute_type . ' ' . $lead->status) }}">
                            <td>
                                <strong>{{ $lead->institute_name }}</strong>
                                <div class="text-muted small">{{ $lead->institute_type ?: 'Institute' }}</div>
                            </td>
                            <td>
                                <div>{{ $lead->contact_name }}</div>
                                <div class="text-muted small">{{ $lead->email }}</div>
                                @if($lead->phone)<div class="text-muted small">{{ $lead->phone }}</div>@endif
                            </td>
                            <td><span class="badge el-badge-soft">{{ $lead->preferred_plan ?: 'Help me decide' }}</span></td>
                            <td>{{ $lead->expected_students ? number_format($lead->expected_students) : '-' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($lead->message ?: '-', 90) }}</td>
                            <td><span class="badge el-badge-soft">{{ ucfirst($lead->status) }}</span></td>
                            <td>{{ optional($lead->created_at)->format('d M, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No institute leads yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="el-panel">
        <div class="el-panel-header">
            <h2 class="el-panel-title">SaaS Plans</h2>
            <div class="el-action-bar">
                <button class="btn el-btn-primary el-btn-icon" data-bs-toggle="modal" data-bs-target="#createPlanModal">
                    <i class="ri-add-line"></i> Add Plan
                </button>
            </div>
        </div>
        <div class="el-filter-bar" data-table-controls="plans">
            <div class="el-filter-field">
                <label for="plansSearch">Search</label>
                <input id="plansSearch" type="text" class="form-control" data-el-search placeholder="Search plan, price, cycle, status">
            </div>
            <div class="el-filter-field">
                <label for="plansSort">Sort</label>
                <select id="plansSort" class="form-select" data-el-sort>
                    <option value="name">Name</option>
                    <option value="price">Price</option>
                    <option value="cycle">Billing Cycle</option>
                    <option value="status">Status</option>
                </select>
            </div>
            <div class="el-filter-field">
                <label for="plansSize">Show</label>
                <select id="plansSize" class="form-select" data-el-size>
                    <option value="50">50 rows</option>
                    <option value="100">100 rows</option>
                    <option value="500">500 rows</option>
                    <option value="all">All rows</option>
                </select>
            </div>
        </div>
        <div class="el-table-wrap">
            <table class="table table-hover align-middle el-table" data-el-table="plans">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Cycle</th>
                        <th>Default</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($plans as $plan)
                        <tr data-search="{{ strtolower($plan->name . ' ' . $plan->slug . ' ' . $plan->price . ' ' . $plan->billing_cycle . ' ' . ($plan->status ? 'active' : 'inactive')) }}"
                            data-name="{{ strtolower($plan->name) }}"
                            data-price="{{ (float) $plan->price }}"
                            data-cycle="{{ strtolower($plan->billing_cycle) }}"
                            data-status="{{ $plan->status ? 'active' : 'inactive' }}">
                            <td>
                                <strong>{{ $plan->name }}</strong>
                                <div class="el-row-meta">{{ $plan->slug }}</div>
                            </td>
                            <td>{{ number_format((float) $plan->price, 2) }}</td>
                            <td>{{ ucfirst($plan->billing_cycle) }}</td>
                            <td>
                                @if($plan->is_default)
                                    <span class="el-status el-status-success">Default</span>
                                @else
                                    <span class="el-row-meta">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="el-status {{ $plan->status ? 'el-status-success' : 'el-status-muted' }}">
                                    {{ $plan->status ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm el-btn-soft" data-bs-toggle="modal" data-bs-target="#editPlanModal{{ $plan->id }}">
                                    <i class="ri-pencil-line"></i> Edit
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr data-empty-row>
                            <td colspan="6" class="el-empty-state">No plans found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="el-panel">
        <div class="el-panel-header">
            <h2 class="el-panel-title">Organizations</h2>
            <div class="el-action-bar">
                <button class="btn el-btn-primary el-btn-icon" data-bs-toggle="modal" data-bs-target="#createOrganizationModal">
                    <i class="ri-building-4-line"></i> Add Organization
                </button>
            </div>
        </div>
        <div class="el-filter-bar" data-table-controls="organizations">
            <div class="el-filter-field">
                <label for="organizationsSearch">Search</label>
                <input id="organizationsSearch" type="text" class="form-control" data-el-search placeholder="Search name, domain, plan, status">
            </div>
            <div class="el-filter-field">
                <label for="organizationsSort">Sort</label>
                <select id="organizationsSort" class="form-select" data-el-sort>
                    <option value="name">Name</option>
                    <option value="domain">Domain</option>
                    <option value="plan">Plan</option>
                    <option value="status">Status</option>
                </select>
            </div>
            <div class="el-filter-field">
                <label for="organizationsSize">Show</label>
                <select id="organizationsSize" class="form-select" data-el-size>
                    <option value="50">50 rows</option>
                    <option value="100">100 rows</option>
                    <option value="500">500 rows</option>
                    <option value="all">All rows</option>
                </select>
            </div>
        </div>
        <div class="el-table-wrap">
            <table class="table table-hover align-middle el-table" data-el-table="organizations">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Domain</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($organizations as $organization)
                        @php
                            $organizationSearch = strtolower(collect([
                                $organization->name,
                                $organization->slug,
                                $organization->domain,
                                $organization->subdomain,
                                $organization->plan?->name,
                                $organization->status,
                            ])->filter()->implode(' '));
                        @endphp
                        <tr data-search="{{ $organizationSearch }}"
                            data-name="{{ strtolower($organization->name) }}"
                            data-domain="{{ strtolower($organization->domain ?: $organization->subdomain ?: '') }}"
                            data-plan="{{ strtolower($organization->plan?->name ?: '') }}"
                            data-status="{{ strtolower($organization->status) }}">
                            <td>
                                <strong>{{ $organization->name }}</strong>
                                <div class="el-row-meta">{{ $organization->slug }}</div>
                            </td>
                            <td>
                                {{ $organization->domain ?: '-' }}
                                @if($organization->subdomain)
                                    <div class="el-row-meta">{{ $organization->subdomain }}</div>
                                @endif
                            </td>
                            <td>{{ $organization->plan?->name ?: '-' }}</td>
                            <td>
                                <span class="el-status {{ $organization->status === 'active' ? 'el-status-success' : ($organization->status === 'suspended' ? 'el-status-danger' : 'el-status-muted') }}">
                                    {{ ucfirst($organization->status) }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="el-action-bar justify-content-end el-table-actions">
                                    <button class="btn btn-sm el-btn-soft" data-bs-toggle="modal" data-bs-target="#editOrganizationModal{{ $organization->id }}">
                                        <i class="ri-pencil-line"></i> Edit
                                    </button>
                                    <a href="{{ route('saas.organizations.website', $organization) }}" class="btn btn-sm el-btn-soft">
                                        <i class="ri-settings-3-line"></i> Website
                                    </a>
                                    <button type="button" class="btn btn-sm el-btn-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal{{ $organization->id }}">
                                        <i class="ri-user-add-line"></i> Add Organization User
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr data-empty-row>
                            <td colspan="5" class="el-empty-state">No organizations found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="el-panel">
        <div class="el-panel-header">
            <h2 class="el-panel-title">Data Ownership Readiness</h2>
        </div>
        <div class="el-filter-bar" data-table-controls="readiness">
            <div class="el-filter-field">
                <label for="readinessSearch">Search</label>
                <input id="readinessSearch" type="text" class="form-control" data-el-search placeholder="Search table or status">
            </div>
            <div class="el-filter-field">
                <label for="readinessSort">Sort</label>
                <select id="readinessSort" class="form-select" data-el-sort>
                    <option value="table">Table</option>
                    <option value="missing">Missing Rows</option>
                    <option value="status">Status</option>
                </select>
            </div>
            <div class="el-filter-field">
                <label for="readinessSize">Show</label>
                <select id="readinessSize" class="form-select" data-el-size>
                    <option value="50">50 rows</option>
                    <option value="100">100 rows</option>
                    <option value="500">500 rows</option>
                    <option value="all">All rows</option>
                </select>
            </div>
        </div>
        <div class="el-table-wrap">
            <table class="table table-hover align-middle el-table" data-el-table="readiness">
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Assigned</th>
                        <th>Missing</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ownedDataSummary as $row)
                        @php
                            $statusText = ! $row['ready'] ? 'pending' : ($row['missing'] === 0 ? 'ready' : 'missing');
                        @endphp
                        <tr data-search="{{ strtolower(str_replace('_', ' ', $row['table']) . ' ' . $statusText) }}"
                            data-table="{{ strtolower($row['table']) }}"
                            data-missing="{{ $row['missing'] ?? 999999 }}"
                            data-status="{{ $statusText }}">
                            <td><strong>{{ str_replace('_', ' ', ucfirst($row['table'])) }}</strong></td>
                            <td>
                                @if($row['ready'])
                                    {{ $row['owned'] }} of {{ $row['total'] }} rows assigned
                                @else
                                    <span class="el-row-meta">organization_id not available</span>
                                @endif
                            </td>
                            <td>{{ $row['ready'] ? $row['missing'] : '-' }}</td>
                            <td>
                                @if($row['ready'] && $row['missing'] === 0)
                                    <span class="el-status el-status-success">Ready</span>
                                @elseif($row['ready'])
                                    <span class="el-status el-status-warning">{{ $row['missing'] }} missing</span>
                                @else
                                    <span class="el-status el-status-muted">Pending</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="el-panel">
        <div class="el-panel-header">
            <h2 class="el-panel-title">Organization Users</h2>
        </div>
        <div class="el-filter-bar" data-table-controls="users">
            <div class="el-filter-field">
                <label for="usersSearch">Search</label>
                <input id="usersSearch" type="text" class="form-control" data-el-search placeholder="Search organization, user, email, role">
            </div>
            <div class="el-filter-field">
                <label for="usersSort">Sort</label>
                <select id="usersSort" class="form-select" data-el-sort>
                    <option value="organization">Organization</option>
                    <option value="user">User</option>
                    <option value="role">Role</option>
                    <option value="status">Status</option>
                </select>
            </div>
            <div class="el-filter-field">
                <label for="usersSize">Show</label>
                <select id="usersSize" class="form-select" data-el-size>
                    <option value="50">50 rows</option>
                    <option value="100">100 rows</option>
                    <option value="500">500 rows</option>
                    <option value="all">All rows</option>
                </select>
            </div>
        </div>
        <div class="el-table-wrap">
            <table class="table table-hover align-middle el-table" data-el-table="users">
                <thead>
                    <tr>
                        <th>Organization</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($organizationUsers as $assignment)
                        <tr data-search="{{ strtolower($assignment->organization_name . ' ' . $assignment->user_name . ' ' . $assignment->user_email . ' ' . $assignment->role . ' ' . ($assignment->status ? 'active' : 'inactive')) }}"
                            data-organization="{{ strtolower($assignment->organization_name) }}"
                            data-user="{{ strtolower($assignment->user_name) }}"
                            data-role="{{ strtolower($assignment->role) }}"
                            data-status="{{ $assignment->status ? 'active' : 'inactive' }}">
                            <td>{{ $assignment->organization_name }}</td>
                            <td>
                                <strong>{{ $assignment->user_name }}</strong>
                                <div class="el-row-meta">{{ $assignment->user_email }}</div>
                            </td>
                            <td>{{ ucfirst($assignment->role) }}</td>
                            <td>
                                <span class="el-status {{ $assignment->status ? 'el-status-success' : 'el-status-muted' }}">
                                    {{ $assignment->status ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                @if(in_array($assignment->role, ['owner', 'admin'], true))
                                    <button type="button" class="btn btn-sm el-btn-soft" data-edit-admin-email data-user-id="{{ $assignment->user_id }}" data-email="{{ $assignment->user_email }}" data-bs-toggle="modal" data-bs-target="#editAdministratorEmailModal">
                                        <i class="ri-mail-settings-line me-1"></i> Edit Email
                                    </button>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr data-empty-row>
                            <td colspan="5" class="el-empty-state">No organization users assigned.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="el-panel">
        <div class="el-panel-header">
            <h2 class="el-panel-title">Assign Existing User</h2>
        </div>
        <form method="POST" action="{{ route('saas.organization-users.assign') }}" class="p-3">
            @csrf
            <div class="row g-3">
                <div class="col-md-6 col-xl-3">
                    <label class="form-label">Organization</label>
                    <select name="organization_id" class="form-select select2" required>
                        @foreach($organizations as $organization)
                            <option value="{{ $organization->id }}">{{ $organization->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label">User</label>
                    <select name="user_id" class="form-select select2" required>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} - {{ $user->email }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-xl-2">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" required>
                        <option value="owner">Owner</option>
                        <option value="admin">Admin</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
                <div class="col-md-6 col-xl-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="col-md-12 col-xl-2 d-flex align-items-end">
                    <button type="submit" class="btn el-btn-primary el-btn-icon w-100">
                        <i class="ri-save-line"></i> Save
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@include('saas.partials.plan-modal', [
    'modalId' => 'createPlanModal',
    'title' => 'Add SaaS Plan',
    'action' => route('saas.plans.store'),
    'method' => 'POST',
    'plan' => null,
])

@foreach($plans as $plan)
    @include('saas.partials.plan-modal', [
        'modalId' => 'editPlanModal' . $plan->id,
        'title' => 'Edit SaaS Plan',
        'action' => route('saas.plans.update', $plan),
        'method' => 'PUT',
        'plan' => $plan,
    ])
@endforeach

@foreach($organizations as $organization)
    @include('saas.partials.admin-user-modal', ['organization' => $organization])
@endforeach

@include('saas.partials.platform-admin-modal')
@include('saas.partials.administrator-email-modal')

@include('saas.partials.organization-modal', [
    'modalId' => 'createOrganizationModal',
    'title' => 'Add Organization',
    'action' => route('saas.organizations.store'),
    'method' => 'POST',
    'organization' => null,
    'plans' => $plans,
])

@foreach($organizations as $organization)
@include('saas.partials.organization-modal', [
        'modalId' => 'editOrganizationModal' . $organization->id,
        'title' => 'Edit Organization',
        'action' => route('saas.organizations.update', $organization),
        'method' => 'PUT',
        'organization' => $organization,
        'plans' => $plans,
    ])
@endforeach
@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const adminEmailRoute = @json(route('saas.admin-users.email.update', ['user' => '__USER__']));
    const adminEmailForm = document.getElementById('editAdministratorEmailForm');
    const adminEmailInput = document.getElementById('administratorEmailInput');

    document.querySelectorAll('[data-edit-admin-email]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!adminEmailForm || !adminEmailInput) return;
            adminEmailForm.action = adminEmailRoute.replace('__USER__', button.dataset.userId);
            adminEmailInput.value = button.dataset.email || '';
        });
    });

    document.querySelectorAll('[data-copy-email]').forEach(function (button) {
        button.addEventListener('click', async function () {
            const email = button.dataset.copyEmail || '';
            try {
                await navigator.clipboard.writeText(email);
                const originalTitle = button.getAttribute('title');
                button.setAttribute('title', 'Copied');
                button.innerHTML = '<i class="ri-check-line"></i>';
                setTimeout(function () {
                    button.setAttribute('title', originalTitle || 'Copy login email');
                    button.innerHTML = '<i class="ri-file-copy-line"></i>';
                }, 1400);
            } catch (error) {
                if (window.Swal) Swal.fire('Login email', email, 'info');
            }
        });
    });
    document.querySelectorAll('.select2').forEach(function (select) {
        if (window.jQuery && jQuery.fn.select2) {
            jQuery(select).select2({ width: '100%' });
        }
    });

    document.querySelectorAll('[data-table-controls]').forEach(function (controls) {
        const key = controls.getAttribute('data-table-controls');
        const table = document.querySelector('[data-el-table="' + key + '"]');
        if (!table) return;

        const rows = Array.from(table.querySelectorAll('tbody tr:not([data-empty-row])'));
        const search = controls.querySelector('[data-el-search]');
        const sort = controls.querySelector('[data-el-sort]');
        const size = controls.querySelector('[data-el-size]');

        const apply = function () {
            const term = (search && search.value ? search.value : '').toLowerCase().trim();
            const sortKey = sort ? sort.value : '';
            const limitValue = size ? size.value : '50';
            const limit = limitValue === 'all' ? rows.length : parseInt(limitValue, 10);

            let visible = rows.filter(function (row) {
                return !term || (row.dataset.search || '').includes(term);
            });

            if (sortKey) {
                visible.sort(function (a, b) {
                    const av = a.dataset[sortKey] || '';
                    const bv = b.dataset[sortKey] || '';
                    const an = parseFloat(av);
                    const bn = parseFloat(bv);

                    if (!Number.isNaN(an) && !Number.isNaN(bn)) {
                        return an - bn;
                    }

                    return av.localeCompare(bv);
                });
            }

            const body = table.querySelector('tbody');
            visible.forEach(function (row) { body.appendChild(row); });

            rows.forEach(function (row) { row.style.display = 'none'; });
            visible.slice(0, limit).forEach(function (row) { row.style.display = ''; });
        };

        [search, sort, size].forEach(function (control) {
            if (control) control.addEventListener('input', apply);
            if (control) control.addEventListener('change', apply);
        });

        apply();
    });
});
</script>
@endsection
