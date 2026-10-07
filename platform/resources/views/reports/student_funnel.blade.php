@extends('layouts.master')

@section('title', 'Student Funnel')

@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Reports')
@slot('title', 'Student Funnel')
@endcomponent

<style>
    .funnel-page {
        --funnel-primary: var(--el-primary, var(--vz-primary, #0f766e));
        --funnel-secondary: var(--el-secondary, var(--vz-warning, #f59e0b));
        --funnel-soft: var(--el-primary-soft, rgba(15, 118, 110, 0.12));
        --funnel-head: var(--el-table-head, #eef7f5);
        --funnel-border: var(--el-border, #d7e2df);
        --funnel-muted: var(--el-muted, #7b8497);
    }

    .funnel-card {
        background: #fff;
        border: 1px solid var(--funnel-border);
        border-radius: var(--el-radius, 4px);
        box-shadow: var(--el-shadow-sm, 0 1px 2px rgba(15, 23, 42, 0.08));
        overflow: hidden;
    }

    .funnel-summary {
        display: grid;
        gap: 16px;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    }

    .funnel-summary-item {
        border-left: 4px solid var(--funnel-primary);
        padding: 18px;
    }

    .funnel-summary-item:nth-child(2n) {
        border-left-color: var(--funnel-secondary);
    }

    .funnel-summary-label {
        color: var(--funnel-muted);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .funnel-summary-value {
        color: var(--el-heading, #111827);
        font-size: 30px;
        font-weight: 800;
        margin-top: 8px;
    }

    .funnel-filter {
        align-items: end;
        display: grid;
        gap: 14px;
        grid-template-columns: repeat(4, minmax(180px, 1fr));
        padding: 18px;
    }

    .funnel-filter-actions {
        grid-column: 1 / -1;
        max-width: 333px;
        min-width: 0;
    }

    @media (max-width: 1199.98px) {
        .funnel-filter {
            grid-template-columns: repeat(2, minmax(220px, 1fr));
        }

        .funnel-filter-actions {
            grid-column: 1 / -1;
            max-width: 360px;
        }
    }

    @media (max-width: 575.98px) {
        .funnel-filter {
            grid-template-columns: minmax(0, 1fr);
        }

        .funnel-filter-actions {
            grid-column: auto;
            max-width: none;
        }
    }

    .funnel-section-title {
        align-items: center;
        color: var(--el-heading, #111827);
        display: flex;
        font-size: 18px;
        font-weight: 800;
        gap: 10px;
        margin-bottom: 16px;
    }

    .funnel-section-title i {
        align-items: center;
        background: var(--funnel-soft);
        border-radius: var(--el-radius, 4px);
        color: var(--funnel-primary);
        display: inline-flex;
        height: 34px;
        justify-content: center;
        width: 34px;
    }

    .funnel-step {
        align-items: center;
        display: grid;
        gap: 12px;
        grid-template-columns: minmax(210px, 1fr) minmax(260px, 2fr) 80px 110px;
        min-width: 0;
        padding: 13px 0;
    }

    .funnel-step > * {
        min-width: 0;
    }

    .funnel-step + .funnel-step {
        border-top: 1px solid var(--funnel-border);
    }

    .funnel-step-label {
        color: var(--el-heading, #111827);
        font-weight: 700;
        line-height: 1.35;
    }

    .funnel-count {
        background: var(--funnel-soft);
        border: 1px solid var(--funnel-border);
        border-radius: 999px;
        color: var(--funnel-primary);
        display: inline-flex;
        font-weight: 800;
        justify-content: center;
        min-width: 50px;
        padding: 6px 12px;
    }

    .funnel-bar {
        background: var(--el-row-alt, rgba(15, 118, 110, 0.05));
        border-radius: 999px;
        height: 12px;
        min-width: 0;
        overflow: hidden;
    }

    .funnel-bar-fill {
        background: var(--funnel-primary);
        border-radius: inherit;
        height: 100%;
        min-width: 3px;
    }

    .funnel-conversion {
        color: var(--funnel-primary);
        font-weight: 800;
        text-align: right;
    }

    .funnel-drop {
        color: var(--funnel-muted);
        font-size: 13px;
        text-align: right;
    }

    .funnel-table th {
        background: var(--funnel-head);
        color: var(--el-heading, #111827);
        font-size: 12px;
        text-transform: uppercase;
    }

    .funnel-table td,
    .funnel-table th {
        border-bottom: 1px solid var(--funnel-border);
        padding: 13px 15px;
        vertical-align: middle;
    }

    .funnel-event-badge {
        background: var(--funnel-soft);
        border: 1px solid var(--funnel-border);
        border-radius: 999px;
        color: var(--funnel-primary);
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        padding: 5px 10px;
    }

    .funnel-status {
        border-radius: 999px;
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        padding: 6px 10px;
    }

    .funnel-status-started {
        background: var(--el-row-alt, rgba(15, 118, 110, 0.05));
        color: var(--funnel-muted);
    }

    .funnel-status-submitted {
        background: var(--el-secondary-soft, rgba(245, 158, 11, 0.14));
        color: var(--funnel-secondary);
    }

    .funnel-status-contact {
        background: var(--funnel-soft);
        color: var(--funnel-primary);
    }

    .funnel-table-toolbar {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        justify-content: space-between;
    }

    .funnel-per-page {
        align-items: center;
        display: inline-flex;
        gap: 8px;
    }

    .funnel-per-page select {
        min-width: 120px;
    }

    .funnel-accordion-toggle {
        align-items: center;
        background: transparent;
        border: 0;
        color: inherit;
        display: flex;
        flex: 1 1 360px;
        gap: 16px;
        justify-content: space-between;
        min-width: 260px;
        padding: 0;
        text-align: left;
    }

    .funnel-accordion-toggle:hover .funnel-section-title,
    .funnel-accordion-toggle:focus .funnel-section-title {
        color: var(--funnel-primary);
    }

    .funnel-accordion-meta {
        align-items: center;
        background: var(--funnel-soft);
        border: 1px solid var(--funnel-border);
        border-radius: 999px;
        color: var(--funnel-primary);
        display: inline-flex;
        font-size: 13px;
        font-weight: 800;
        gap: 6px;
        padding: 7px 12px;
        white-space: nowrap;
    }

    .funnel-accordion-meta i {
        font-size: 18px;
        transition: transform .18s ease;
    }

    .funnel-accordion-toggle[aria-expanded="true"] .funnel-accordion-meta i {
        transform: rotate(180deg);
    }

    .funnel-accordion-card .funnel-table-toolbar {
        background: #fff;
    }

    @media (max-width: 767.98px) {
        .funnel-step {
            grid-template-columns: 1fr;
        }

        .funnel-conversion,
        .funnel-drop {
            text-align: left;
        }

        .funnel-accordion-toggle {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>

<div class="funnel-page">
    @php
        $openSection = request('open_section');
    @endphp

    <div class="funnel-summary mb-4">
        <div class="funnel-card funnel-summary-item">
            <div class="funnel-summary-label">Registered Students</div>
            <div class="funnel-summary-value">{{ number_format($summary['registered']) }}</div>
        </div>
        <div class="funnel-card funnel-summary-item">
            <div class="funnel-summary-label">Started Exams</div>
            <div class="funnel-summary-value">{{ number_format($summary['started']) }}</div>
        </div>
        <div class="funnel-card funnel-summary-item">
            <div class="funnel-summary-label">Submitted Exams</div>
            <div class="funnel-summary-value">{{ number_format($summary['submitted']) }}</div>
        </div>
        <div class="funnel-card funnel-summary-item">
            <div class="funnel-summary-label">Guest Submissions</div>
            <div class="funnel-summary-value">{{ number_format($summary['guest_submitted']) }}</div>
        </div>
        <div class="funnel-card funnel-summary-item">
            <div class="funnel-summary-label">Quick Quiz Participants</div>
            <div class="funnel-summary-value">{{ number_format($summary['quick_quiz_participants']) }}</div>
        </div>
        <div class="funnel-card funnel-summary-item">
            <div class="funnel-summary-label">Study Learners</div>
            <div class="funnel-summary-value">{{ number_format($summary['study_learners']) }}</div>
        </div>
        <div class="funnel-card funnel-summary-item">
            <div class="funnel-summary-label">Cards Reviewed</div>
            <div class="funnel-summary-value">{{ number_format($summary['study_cards']) }}</div>
        </div>
        <div class="funnel-card funnel-summary-item">
            <div class="funnel-summary-label">Study Points</div>
            <div class="funnel-summary-value">{{ number_format($summary['study_points']) }}</div>
        </div>
        <div class="funnel-card funnel-summary-item">
            <div class="funnel-summary-label">PDF Downloads</div>
            <div class="funnel-summary-value">{{ number_format($summary['pdf_downloads']) }}</div>
        </div>
    </div>

    <div class="funnel-card mb-4">
        <form method="GET" action="{{ route('results.student-funnel') }}" class="funnel-filter">
            <input type="hidden" name="guest_per_page" value="{{ $guestPerPage }}">
            <input type="hidden" name="guest_contact_per_page" value="{{ $guestContactPerPage }}">
            <input type="hidden" name="activity_per_page" value="{{ $activityPerPage }}">
            <input type="hidden" name="pdf_per_page" value="{{ $pdfPerPage }}">
            <div>
                <label class="form-label">From</label>
                <input type="date" name="date_from" class="form-control" value="{{ $dateFrom->toDateString() }}">
            </div>
            <div>
                <label class="form-label">To</label>
                <input type="date" name="date_to" class="form-control" value="{{ $dateTo->toDateString() }}">
            </div>
            <div>
                <label class="form-label">Source</label>
                <select name="source" class="form-select">
                    <option value="all" @selected($source === 'all')>All Sources</option>
                    <option value="student" @selected($source === 'student')>Student</option>
                    <option value="guest" @selected($source === 'guest')>Guest</option>
                    <option value="web" @selected($source === 'web')>Web</option>
                    <option value="admin" @selected($source === 'admin')>Admin</option>
                </select>
            </div>
            <div>
                <label class="form-label">Traffic</label>
                <select name="traffic" class="form-select">
                    <option value="human" @selected($traffic === 'human')>Humans only</option>
                    <option value="bot" @selected($traffic === 'bot')>Bots only</option>
                    <option value="all" @selected($traffic === 'all')>Humans + bots</option>
                </select>
                <small class="text-muted">{{ number_format($botEventsDetected) }} bot events detected in this period.</small>
            </div>
            <div class="d-flex gap-2 funnel-filter-actions">
                <button type="submit" class="btn el-btn-primary flex-fill"><i class="ri-filter-3-line me-1"></i>Filter</button>
                <a href="{{ route('results.student-funnel') }}" class="btn el-btn-secondary flex-fill">Reset</a>
            </div>
        </form>
    </div>

    <div class="row g-4">
        <div class="col-12">
            <div class="funnel-card p-4 h-100">
                <div class="funnel-section-title"><i class="ri-user-follow-line"></i>Student Funnel</div>
                @foreach($studentFunnel as $step)
                    <div class="funnel-step">
                        <div class="funnel-step-label">{{ $step['label'] }}</div>
                        <div class="funnel-bar" aria-label="{{ $step['label'] }}">
                            <div class="funnel-bar-fill" style="width: {{ min(100, $step['conversion']) }}%;"></div>
                        </div>
                        <div><span class="funnel-count">{{ number_format($step['count']) }}</span></div>
                        <div>
                            <div class="funnel-conversion">{{ $step['conversion'] }}%</div>
                            <div class="funnel-drop">{{ $step['drop_off'] ? number_format($step['drop_off']).' drop' : 'No drop' }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="col-12">
            <div class="funnel-card p-4 h-100">
                <div class="funnel-section-title"><i class="ri-user-search-line"></i>Guest Exam Funnel</div>
                @foreach($guestFunnel as $step)
                    <div class="funnel-step">
                        <div class="funnel-step-label">{{ $step['label'] }}</div>
                        <div class="funnel-bar" aria-label="{{ $step['label'] }}">
                            <div class="funnel-bar-fill" style="width: {{ min(100, $step['conversion']) }}%; background: var(--funnel-secondary);"></div>
                        </div>
                        <div><span class="funnel-count">{{ number_format($step['count']) }}</span></div>
                        <div>
                            <div class="funnel-conversion">{{ $step['conversion'] }}%</div>
                            <div class="funnel-drop">{{ $step['drop_off'] ? number_format($step['drop_off']).' drop' : 'No drop' }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="col-12">
            <div class="funnel-card p-4 h-100">
                <div class="funnel-section-title"><i class="ri-flashlight-line"></i>Quick Quiz Funnel</div>
                @foreach($quickQuizFunnel as $step)
                    <div class="funnel-step">
                        <div class="funnel-step-label">{{ $step['label'] }}</div>
                        <div class="funnel-bar" aria-label="{{ $step['label'] }}">
                            <div class="funnel-bar-fill" style="width: {{ min(100, $step['conversion']) }}%; background: var(--funnel-secondary);"></div>
                        </div>
                        <div><span class="funnel-count">{{ number_format($step['count']) }}</span></div>
                        <div>
                            <div class="funnel-conversion">{{ $step['conversion'] }}%</div>
                            <div class="funnel-drop">{{ $step['drop_off'] ? number_format($step['drop_off']).' drop' : 'No drop' }}</div>
                        </div>
                    </div>
                @endforeach
                <div class="text-muted mt-3">
                    {{ number_format($quickQuizStats['students']) }} signed-in students and {{ number_format($quickQuizStats['guests']) }} guests started; {{ number_format($quickQuizStats['completed']) }} completed.
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="funnel-card p-4 h-100">
                <div class="funnel-section-title"><i class="ri-stack-line"></i>Study Card Funnel</div>
                @foreach($studyCardFunnel as $step)
                    <div class="funnel-step">
                        <div class="funnel-step-label">{{ $step['label'] }}</div>
                        <div class="funnel-bar" aria-label="{{ $step['label'] }}">
                            <div class="funnel-bar-fill" style="width: {{ min(100, $step['conversion']) }}%;"></div>
                        </div>
                        <div><span class="funnel-count">{{ number_format($step['count']) }}</span></div>
                        <div>
                            <div class="funnel-conversion">{{ $step['conversion'] }}%</div>
                            <div class="funnel-drop">{{ $step['drop_off'] ? number_format($step['drop_off']).' drop' : 'No drop' }}</div>
                        </div>
                    </div>
                @endforeach
                <div class="text-muted mt-3">
                    {{ number_format($studyStats['points']) }} total study points from {{ number_format($studyStats['sets']) }} study-card set records.
                </div>
            </div>
        </div>
    </div>

    <div id="funnelRecordAccordion" class="d-flex flex-column">
    <div class="funnel-card funnel-accordion-card mt-4" style="order: 1">
        <div class="p-4 border-bottom funnel-table-toolbar">
            <button class="funnel-accordion-toggle {{ $openSection === 'guest_contacts' ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#savedGuestContactsCollapse" aria-expanded="{{ $openSection === 'guest_contacts' ? 'true' : 'false' }}" aria-controls="savedGuestContactsCollapse">
                <span>
                    <span class="funnel-section-title mb-0"><i class="ri-contacts-book-line"></i>Saved Guest Contacts</span>
                    <span class="text-muted d-block mt-1">These are the guests who shared a name or email before viewing their result.</span>
                </span>
                <span class="funnel-accordion-meta">Showing {{ number_format($savedGuestContacts->count()) }} of {{ number_format($savedGuestContacts->total()) }} <i class="ri-arrow-down-s-line"></i></span>
            </button>
            <form method="GET" action="{{ route('results.student-funnel') }}" class="funnel-per-page">
                <input type="hidden" name="open_section" value="guest_contacts">
                <input type="hidden" name="date_from" value="{{ $dateFrom->toDateString() }}">
                <input type="hidden" name="date_to" value="{{ $dateTo->toDateString() }}">
                <input type="hidden" name="source" value="{{ $source }}">
                <input type="hidden" name="traffic" value="{{ $traffic }}">
                <input type="hidden" name="guest_per_page" value="{{ $guestPerPage }}">
                <input type="hidden" name="activity_per_page" value="{{ $activityPerPage }}">
            <input type="hidden" name="pdf_per_page" value="{{ $pdfPerPage }}">
                <label class="form-label mb-0">Show</label>
                <select name="guest_contact_per_page" class="form-select" onchange="this.form.submit()">
                    @foreach($perPageOptions as $option)
                        <option value="{{ $option }}" @selected($guestContactPerPage === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div id="savedGuestContactsCollapse" class="collapse {{ $openSection === 'guest_contacts' ? 'show' : '' }}" data-bs-parent="#funnelRecordAccordion">
            <div class="table-responsive">
                <table class="table funnel-table mb-0">
                    <thead>
                        <tr>
                            <th>Saved</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Exam</th>
                            <th>Score</th>
                            <th>Guest ID</th>
                            <th>Device</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($savedGuestContacts as $contact)
                            <tr>
                                <td>{{ filled($contact->contact_saved_at) ? \Illuminate\Support\Carbon::parse($contact->contact_saved_at)->format('d M Y, h:i A') : '-' }}</td>
                                <td><strong>{{ $contact->guest_name ?: 'Guest' }}</strong></td>
                                <td>{{ $contact->guest_email ?: '-' }}</td>
                                <td>{{ $contact->exam?->name ?? '-' }}</td>
                                <td>
                                    @if($contact->end_time)
                                        {{ number_format((float) ($contact->obtained_marks ?? 0), 2) }} / {{ number_format((float) ($contact->total_marks ?? 0), 2) }}<br>
                                        <span class="text-muted">{{ number_format((float) ($contact->percent ?? 0), 2) }}%</span>
                                    @else
                                        <span class="text-muted">Not submitted</span>
                                    @endif
                                </td>
                                <td><span class="funnel-event-badge">{{ $contact->guest_id ?: '-' }}</span></td>
                                <td>
                                    <span>{{ $contact->guest_ip_address ?: '-' }}</span><br>
                                    <span class="text-muted">{{ \Illuminate\Support\Str::limit($contact->guest_user_agent ?: '-', 42) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">No saved guest contacts found for this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($savedGuestContacts->hasPages())
                <div class="p-3 border-top">
                    {{ $savedGuestContacts->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>

    <div class="funnel-card funnel-accordion-card mt-4" style="order: 3">
        <div class="p-4 border-bottom funnel-table-toolbar">
            <button class="funnel-accordion-toggle {{ $openSection === 'guest_attempts' ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#recentGuestAttemptsCollapse" aria-expanded="{{ $openSection === 'guest_attempts' ? 'true' : 'false' }}" aria-controls="recentGuestAttemptsCollapse">
                <span>
                    <span class="funnel-section-title mb-0"><i class="ri-user-location-line"></i>Recent Guest Attempts</span>
                    <span class="text-muted d-block mt-1">Shows guest starts, submits, scores and optional contact details captured without blocking the exam.</span>
                </span>
                <span class="funnel-accordion-meta">Showing {{ number_format($recentGuestAttempts->count()) }} of {{ number_format($recentGuestAttempts->total()) }} <i class="ri-arrow-down-s-line"></i></span>
            </button>
            <form method="GET" action="{{ route('results.student-funnel') }}" class="funnel-per-page">
                <input type="hidden" name="open_section" value="guest_attempts">
                <input type="hidden" name="date_from" value="{{ $dateFrom->toDateString() }}">
                <input type="hidden" name="date_to" value="{{ $dateTo->toDateString() }}">
                <input type="hidden" name="source" value="{{ $source }}">
                <input type="hidden" name="traffic" value="{{ $traffic }}">
                <input type="hidden" name="activity_per_page" value="{{ $activityPerPage }}">
            <input type="hidden" name="pdf_per_page" value="{{ $pdfPerPage }}">
                <input type="hidden" name="guest_contact_per_page" value="{{ $guestContactPerPage }}">
                <label class="form-label mb-0">Show</label>
                <select name="guest_per_page" class="form-select" onchange="this.form.submit()">
                    @foreach($perPageOptions as $option)
                        <option value="{{ $option }}" @selected($guestPerPage === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div id="recentGuestAttemptsCollapse" class="collapse {{ $openSection === 'guest_attempts' ? 'show' : '' }}" data-bs-parent="#funnelRecordAccordion">
            <div class="table-responsive">
                <table class="table funnel-table mb-0">
                    <thead>
                        <tr>
                            <th>Started</th>
                            <th>Guest</th>
                            <th>Exam</th>
                            <th>Status</th>
                            <th>Score</th>
                            <th>Device</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentGuestAttempts as $attempt)
                            @php
                                $hasContact = filled($attempt->guest_name) || filled($attempt->guest_email);
                                $statusClass = $hasContact ? 'funnel-status-contact' : ($attempt->end_time ? 'funnel-status-submitted' : 'funnel-status-started');
                                $statusText = $hasContact ? 'Contact saved' : ($attempt->end_time ? 'Submitted, no details' : 'Started, not submitted');
                            @endphp
                            <tr>
                                <td>{{ optional($attempt->start_time)->format('d M Y, h:i A') }}</td>
                                <td>
                                    <strong>{{ $attempt->guest_name ?: 'Guest' }}</strong><br>
                                    <span class="text-muted">{{ $attempt->guest_email ?: ($attempt->guest_id ?: '-') }}</span>
                                </td>
                                <td>{{ $attempt->exam?->name ?? '-' }}</td>
                                <td><span class="funnel-status {{ $statusClass }}">{{ $statusText }}</span></td>
                                <td>
                                    @if($attempt->end_time)
                                        {{ number_format((float) ($attempt->obtained_marks ?? 0), 2) }} / {{ number_format((float) ($attempt->total_marks ?? 0), 2) }}<br>
                                        <span class="text-muted">{{ number_format((float) ($attempt->percent ?? 0), 2) }}%</span>
                                    @else
                                        <span class="text-muted">Not submitted</span>
                                    @endif
                                </td>
                                <td>
                                    <span>{{ $attempt->guest_ip_address ?: '-' }}</span><br>
                                    <span class="text-muted">{{ \Illuminate\Support\Str::limit($attempt->guest_user_agent ?: '-', 42) }}</span>
                                    @if($attempt->guest_is_bot)<br><span class="badge bg-danger-subtle text-danger">Bot</span>@endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">No guest attempts found for this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($recentGuestAttempts->hasPages())
                <div class="p-3 border-top">
                    {{ $recentGuestAttempts->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>

    <div class="funnel-card funnel-accordion-card mt-4" style="order: 2">
        <div class="p-4 border-bottom funnel-table-toolbar">
            <button class="funnel-accordion-toggle {{ $openSection === 'pdf_downloads' ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#pdfDownloadsCollapse" aria-expanded="{{ $openSection === 'pdf_downloads' ? 'true' : 'false' }}" aria-controls="pdfDownloadsCollapse">
                <span>
                    <span class="funnel-section-title mb-0"><i class="ri-file-pdf-2-line"></i>PDF Downloads</span>
                    <span class="text-muted d-block mt-1">Tracks student, guest and admin PDF download activity across platform-controlled PDF routes.</span>
                </span>
                <span class="funnel-accordion-meta">Showing {{ number_format($pdfDownloads->count()) }} of {{ number_format($pdfDownloads->total()) }} <i class="ri-arrow-down-s-line"></i></span>
            </button>
            <form method="GET" action="{{ route('results.student-funnel') }}" class="funnel-per-page">
                <input type="hidden" name="open_section" value="pdf_downloads">
                <input type="hidden" name="date_from" value="{{ $dateFrom->toDateString() }}">
                <input type="hidden" name="date_to" value="{{ $dateTo->toDateString() }}">
                <input type="hidden" name="source" value="{{ $source }}">
                <input type="hidden" name="traffic" value="{{ $traffic }}">
                <input type="hidden" name="guest_per_page" value="{{ $guestPerPage }}">
                <input type="hidden" name="guest_contact_per_page" value="{{ $guestContactPerPage }}">
                <input type="hidden" name="activity_per_page" value="{{ $activityPerPage }}">
                <label class="form-label mb-0">Show</label>
                <select name="pdf_per_page" class="form-select" onchange="this.form.submit()">
                    @foreach($perPageOptions as $option)
                        <option value="{{ $option }}" @selected($pdfPerPage === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div id="pdfDownloadsCollapse" class="collapse {{ $openSection === 'pdf_downloads' ? 'show' : '' }}" data-bs-parent="#funnelRecordAccordion">
            <div class="table-responsive">
                <table class="table funnel-table mb-0">
                    <thead>
                        <tr>
                            <th>Downloaded</th>
                            <th>Student / Guest</th>
                            <th>Document</th>
                            <th>Context</th>
                            <th>Source</th>
                            <th>Device</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pdfDownloads as $download)
                            @php
                                $downloadMeta = $download->metadata ?? [];
                                $documentName = $downloadMeta['document_name'] ?? $download->exam?->name ?? 'PDF document';
                                $documentContext = str_replace('_', ' ', $downloadMeta['document_context'] ?? 'pdf');
                            @endphp
                            <tr>
                                <td>{{ optional($download->occurred_at)->format('d M Y, h:i A') }}</td>
                                <td>
                                    @if($download->student)
                                        <strong>{{ $download->student->name }}</strong><br>
                                        <span class="text-muted">{{ $download->student->email }}</span>
                                    @elseif($download->source === 'admin')
                                        <strong>Administrator</strong><br>
                                        <span class="text-muted">Authenticated admin</span>
                                    @else
                                        <strong>Guest</strong><br>
                                        <span class="text-muted">{{ $download->guest_id ?: 'Unidentified visitor' }}</span>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $documentName }}</strong>
                                    @if($download->exam)
                                        <br><span class="text-muted">Exam #{{ $download->exam_id }}</span>
                                    @endif
                                </td>
                                <td>{{ ucfirst($documentContext) }}</td>
                                <td><span class="funnel-event-badge">{{ ucfirst($download->source) }}</span>@if($download->is_bot)<br><span class="badge bg-danger-subtle text-danger mt-1">Bot{{ $download->bot_name ? ': '.$download->bot_name : '' }}</span>@endif</td>
                                <td>
                                    <span>{{ $download->ip_address ?: '-' }}</span><br>
                                    <span class="text-muted">{{ \Illuminate\Support\Str::limit($download->user_agent ?: '-', 42) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">No PDF downloads found for this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($pdfDownloads->hasPages())
                <div class="p-3 border-top">
                    {{ $pdfDownloads->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
    <div class="funnel-card funnel-accordion-card mt-4" style="order: 4">
        <div class="p-4 border-bottom funnel-table-toolbar">
            <button class="funnel-accordion-toggle {{ $openSection === 'activity' ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#recentFunnelActivityCollapse" aria-expanded="{{ $openSection === 'activity' ? 'true' : 'false' }}" aria-controls="recentFunnelActivityCollapse">
                <span class="funnel-section-title mb-0"><i class="ri-history-line"></i>Recent Funnel Activity</span>
                <span class="funnel-accordion-meta">Showing {{ number_format($recentEvents->count()) }} of {{ number_format($recentEvents->total()) }} <i class="ri-arrow-down-s-line"></i></span>
            </button>
            <form method="GET" action="{{ route('results.student-funnel') }}" class="funnel-per-page">
                <input type="hidden" name="open_section" value="activity">
                <input type="hidden" name="date_from" value="{{ $dateFrom->toDateString() }}">
                <input type="hidden" name="date_to" value="{{ $dateTo->toDateString() }}">
                <input type="hidden" name="source" value="{{ $source }}">
                <input type="hidden" name="traffic" value="{{ $traffic }}">
                <input type="hidden" name="guest_per_page" value="{{ $guestPerPage }}">
                <input type="hidden" name="guest_contact_per_page" value="{{ $guestContactPerPage }}">
                <input type="hidden" name="pdf_per_page" value="{{ $pdfPerPage }}">
                <label class="form-label mb-0">Show</label>
                <select name="activity_per_page" class="form-select" onchange="this.form.submit()">
                    @foreach($perPageOptions as $option)
                        <option value="{{ $option }}" @selected($activityPerPage === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div id="recentFunnelActivityCollapse" class="collapse {{ $openSection === 'activity' ? 'show' : '' }}" data-bs-parent="#funnelRecordAccordion">
            <div class="table-responsive">
                <table class="table funnel-table mb-0">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Event</th>
                            <th>Student / Guest</th>
                            <th>Exam</th>
                            <th>Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentEvents as $event)
                            <tr>
                                <td>{{ optional($event->occurred_at)->format('d M Y, h:i A') }}</td>
                                <td><span class="funnel-event-badge">{{ str_replace('_', ' ', $event->event_name) }}</span></td>
                                <td>
                                    @if($event->student)
                                        <strong>{{ $event->student->name }}</strong><br>
                                        <span class="text-muted">{{ $event->student->email }}</span>
                                    @else
                                        <strong>Guest</strong><br>
                                        <span class="text-muted">{{ $event->guest_id ?: '-' }}</span>
                                    @endif
                                </td>
                                <td>{{ $event->exam?->name ?? '-' }}</td>
                                <td>{{ ucfirst($event->source) }}@if($event->is_bot)<br><span class="badge bg-danger-subtle text-danger">Bot{{ $event->bot_name ? ': '.$event->bot_name : '' }}</span>@endif</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">No funnel activity found for this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($recentEvents->hasPages())
                <div class="p-3 border-top">
                    {{ $recentEvents->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
    </div>
</div>
@endsection
