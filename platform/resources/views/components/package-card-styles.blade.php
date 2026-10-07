@once
    <style>
        .course-card {
            background: var(--theme-card-bg, #ffffff);
            border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 14%, #e2e8f0);
            border-radius: 18px;
            box-shadow: var(--ew-shadow, 0 16px 40px rgba(16, 40, 38, .09));
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
            transition: box-shadow .2s ease, transform .2s ease;
        }

        .course-card:hover {
            border-color: color-mix(in srgb, var(--theme-primary, #0f766e) 28%, #e2e8f0);
            box-shadow: 0 22px 48px rgba(16, 40, 38, .14);
            transform: translateY(-3px);
        }

        .course-card-head {
            background: linear-gradient(145deg, color-mix(in srgb, var(--ew-primary, #08786c) 8%, #fff), var(--ew-surface, #fff));
            border-bottom: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 13%, #e2e8f0);
            color: var(--theme-heading, #0f172a);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 154px;
            padding: 18px 20px;
            position: relative;
            text-decoration: none;
        }

        .course-card-head::before {
            background: var(--theme-primary, #0f766e);
            content: "";
            inset: 0 auto 0 0;
            position: absolute;
            width: 5px;
        }

        .course-card-topline {
            align-items: flex-start;
            display: flex;
            gap: 8px;
            justify-content: space-between;
            min-height: 34px;
        }

        .course-card-badge {
            background: var(--theme-secondary, #f59e0b);
            border-radius: 999px;
            color: var(--theme-button-text, #ffffff);
            display: inline-flex;
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
            padding: 8px 12px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .course-card-badge-free {
            background: transparent;
            border: 1px solid var(--ew-primary, #08786c);
            color: var(--ew-primary, #08786c);
        }
        .course-card-badge-paid { background: var(--ew-accent, #f47a10); }
        .course-card-hierarchy { color: var(--ew-primary, #08786c); font-size: 10px; font-weight: 850; letter-spacing: .04em; margin-top: 12px; text-transform: uppercase; }
        .course-card-price {
            background: var(--theme-card-bg, #ffffff);
            border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 18%, #e2e8f0);
            border-radius: 999px;
            color: var(--theme-primary, #0f766e);
            font-size: 13px;
            font-weight: 850;
            padding: 7px 11px;
            white-space: nowrap;
        }

        .course-card-title {
            color: var(--theme-heading, #0f172a);
            font-size: 1rem;
            font-weight: 850;
            line-height: 1.35;
            margin: 22px 0 0;
            overflow-wrap: anywhere;
        }

        .course-card-counts {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }

        .course-card-exams {
            align-items: center;
            background: color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #ffffff);
            border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 18%, #ffffff);
            border-radius: 999px;
            color: var(--theme-primary, #0f766e);
            display: inline-flex;
            font-size: 12px;
            font-weight: 800;
            gap: 6px;
            padding: 7px 12px;
            width: fit-content;
        }

        .course-card-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
        }

        .course-card-tag {
            background: color-mix(in srgb, var(--theme-primary, #0f766e) 4%, #ffffff);
            border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 14%, #e2e8f0);
            border-radius: 999px;
            color: var(--theme-text, #64748b);
            display: inline-flex;
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
            padding: 6px 9px;
        }

        .course-card-body {
            display: flex;
            flex: 1;
            flex-direction: column;
            gap: 16px;
            padding: 16px;
        }

        .course-card-meta {
            display: grid;
            gap: 10px;
        }

        .course-card-meta span {
            align-items: center;
            color: var(--theme-text, #64748b);
            display: flex;
            font-size: 13px;
            gap: 8px;
        }

        .course-card-meta i {
            align-items: center;
            background: color-mix(in srgb, var(--theme-primary, #0f766e) 9%, #ffffff);
            border-radius: 10px;
            color: var(--theme-primary, #0f766e);
            display: inline-flex;
            flex: 0 0 32px;
            font-size: 16px;
            height: 32px;
            justify-content: center;
            width: 32px;
        }

        .course-card-actions {
            display: grid;
            gap: 12px;
            margin-top: auto;
        }

        .course-card-btn {
            align-items: center;
            border: 1px solid transparent;
            border-radius: 999px;
            display: inline-flex;
            font-size: 14px;
            font-weight: 800;
            gap: 8px;
            justify-content: center;
            min-height: 48px;
            padding: 11px 18px;
            text-decoration: none;
            width: 100%;
        }

        .course-card-btn-primary {
            background: var(--ew-primary, #08786c);
            border-color: var(--ew-primary, #08786c);
            color: #ffffff;
        }

        .course-card-btn-paid { background: var(--ew-accent, #f47a10); border-color: var(--ew-accent, #f47a10); }

        .course-card-btn-secondary {
            background: var(--ew-surface, #ffffff);
            border-color: var(--ew-border, #d8e5e3);
            color: var(--ew-primary, #08786c);
        }

        .course-card-btn-primary:hover,
        .course-card-btn-primary:focus,
        .course-card-btn-primary:active,
        .course-card-btn-primary:disabled,
        .course-card-btn-primary.disabled {
            background: var(--theme-primary, #0f766e);
            border-color: var(--theme-primary, #0f766e);
            color: var(--theme-button-text, #ffffff);
            -webkit-text-fill-color: var(--theme-button-text, #ffffff);
            opacity: 1;
        }

        .course-card-btn-secondary:hover,
        .course-card-btn-secondary:focus,
        .course-card-btn-secondary:active {
            background: var(--theme-secondary, #f59e0b);
            border-color: var(--theme-secondary, #f59e0b);
            color: var(--theme-button-text, #ffffff);
            -webkit-text-fill-color: var(--theme-button-text, #ffffff);
            opacity: 1;
        }

        .course-card-btn:hover,
        .course-card-btn:focus {
            text-decoration: none;
        }

        .course-card-btn:hover *,
        .course-card-btn:focus *,
        .course-card-btn:active * {
            color: inherit;
            -webkit-text-fill-color: inherit;
        }


        .course-card-footer {
            align-items: center;
            background: var(--theme-card-bg, #ffffff);
            display: grid;
            gap: 14px;
            grid-template-columns: minmax(0, 1fr) auto;
            margin-top: auto;
            padding: 16px 18px 18px;
        }
        .course-card-validity { align-items:center; color:var(--theme-text,#64748b); display:flex; font-size:12px; font-weight:750; gap:7px; }
        .course-card-validity i { color:var(--theme-primary,#0f766e); font-size:17px; }
        .course-card-explore { align-items:center; background:var(--theme-secondary,#f59e0b); border-radius:999px; color:var(--theme-button-text,#fff); display:inline-flex; font-size:13px; font-weight:850; gap:7px; min-height:42px; padding:10px 16px; text-decoration:none; white-space:nowrap; }
        .course-card-explore:hover,.course-card-explore:focus,.course-card-explore:active { background:color-mix(in srgb,var(--theme-secondary,#f59e0b) 86%,#000); color:var(--theme-button-text,#fff) !important; -webkit-text-fill-color:var(--theme-button-text,#fff) !important; text-decoration:none; transform:translateX(2px); }
        .course-card-explore:hover i,.course-card-explore:focus i,.course-card-explore:active i { color:inherit !important; -webkit-text-fill-color:inherit !important; }
        @media (max-width: 420px) { .course-card-footer { grid-template-columns:1fr; } .course-card-explore { justify-content:center; width:100%; } }        @media (max-width: 575.98px) {
            .course-card-head {
                min-height: 150px;
            }

            .course-card-btn {
                min-height: 46px;
            }
        }
    </style>
@endonce
