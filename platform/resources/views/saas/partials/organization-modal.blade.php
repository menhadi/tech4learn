<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="POST" action="{{ $action }}" class="modal-content">
            @csrf
            @if($method !== 'POST')
                @method($method)
            @endif

            <div class="modal-header">
                <h5 class="modal-title">{{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                @php
                    $isExamElite = optional($organization)->slug === 'examelite';
                @endphp

                @if($isExamElite)
                    <div class="alert alert-info">
                        ExamElite is the default organization. Its status is protected and remains active.
                    </div>
                @endif

                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Organization Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $organization->name ?? '') }}" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Domain</label>
                        <input type="text" name="domain" class="form-control" value="{{ old('domain', $organization->domain ?? '') }}" placeholder="example.com">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Subdomain</label>
                        <input type="text" name="subdomain" class="form-control" value="{{ old('subdomain', $organization->subdomain ?? '') }}" placeholder="school-name">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $organization->email ?? '') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone', $organization->phone ?? '') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Plan</label>
                        <select name="saas_plan_id" class="form-select">
                            <option value="">No Plan</option>
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}" {{ old('saas_plan_id', $organization->saas_plan_id ?? '') == $plan->id ? 'selected' : '' }}>
                                    {{ $plan->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" {{ $isExamElite ? 'disabled' : '' }}>
                            @foreach(['active', 'inactive', 'suspended'] as $status)
                                <option value="{{ $status }}" {{ old('status', $organization->status ?? 'active') === $status ? 'selected' : '' }}>
                                    {{ ucfirst($status) }}
                                </option>
                            @endforeach
                        </select>

                        @if($isExamElite)
                            <input type="hidden" name="status" value="active">
                        @endif
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Trial Ends At</label>
                        <input type="date" name="trial_ends_at" class="form-control" value="{{ old('trial_ends_at', optional(optional($organization)->trial_ends_at)->format('Y-m-d')) }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Subscription Ends At</label>
                        <input type="date" name="subscription_ends_at" class="form-control" value="{{ old('subscription_ends_at', optional(optional($organization)->subscription_ends_at)->format('Y-m-d')) }}">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn el-btn-primary">Save Organization</button>
            </div>
        </form>
    </div>
</div>
