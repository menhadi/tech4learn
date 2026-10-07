@once
    <style>
        .exam-action-group {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            white-space: nowrap;
        }

        .exam-action-control {
            min-height: 42px;
            border-radius: 8px;
            padding: 8px 12px;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.1;
            text-decoration: none !important;
            cursor: pointer;
            transition: transform .18s ease, background-color .18s ease, border-color .18s ease, box-shadow .18s ease;
        }

        .exam-action-control--primary {
            min-width: 132px;
            background: var(--theme-primary, var(--el-primary, #0f766e)) !important;
            border-color: var(--theme-primary, var(--el-primary, #0f766e)) !important;
            color: var(--theme-button-text, #ffffff) !important;
            -webkit-text-fill-color: var(--theme-button-text, #ffffff) !important;
            box-shadow: 0 7px 16px rgba(15, 118, 110, .18);
        }

        .exam-action-control--download {
            min-width: 108px;
            background: var(--theme-secondary-soft, var(--el-secondary-soft, #fff7ed)) !important;
            border-color: color-mix(in srgb, var(--theme-secondary, var(--el-secondary, #f59e0b)) 48%, #ffffff) !important;
            color: color-mix(in srgb, var(--theme-secondary, var(--el-secondary, #f59e0b)) 72%, #111827) !important;
            -webkit-text-fill-color: currentColor !important;
            box-shadow: none;
        }

        .exam-action-control--icon {
            min-width: 42px;
            width: 42px;
            padding-inline: 0;
        }

        .exam-action-control:hover,
        .exam-action-control:focus-visible {
            transform: translateY(-1px);
        }

        .exam-action-control--primary:hover,
        .exam-action-control--primary:focus-visible {
            color: var(--theme-button-text, #ffffff) !important;
            filter: brightness(.96);
        }

        .exam-action-control--download:hover,
        .exam-action-control--download:focus-visible {
            background: var(--theme-secondary, var(--el-secondary, #f59e0b)) !important;
            border-color: var(--theme-secondary, var(--el-secondary, #f59e0b)) !important;
            color: var(--theme-button-text, #ffffff) !important;
            -webkit-text-fill-color: var(--theme-button-text, #ffffff) !important;
        }

        @media (max-width: 640px) {
            .exam-action-group {
                display: grid;
                grid-column: 1 / -1;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                width: 100%;
                white-space: normal;
            }

            .exam-action-control {
                width: 100%;
                min-width: 0;
            }

            .exam-action-control--primary {
                grid-column: 1 / -1;
            }

            .exam-action-control--icon {
                width: 42px;
                justify-self: start;
            }
        }
    </style>
@endonce

<div {{ $attributes->class(['exam-action-group']) }}>
    @isset($primary){{ $primary }}@endisset
    @isset($paper){{ $paper }}@endisset
    @isset($solution){{ $solution }}@endisset
    @isset($details){{ $details }}@endisset
</div>