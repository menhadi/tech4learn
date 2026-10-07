@php
    $seoModel = $seoModel ?? null;
    $seoCollapseId = 'seoSettings' . uniqid();
@endphp

<div class="card mt-4">
    <div class="card-header">
        <div class="d-flex flex-column gap-2">
            <button class="seo-settings-toggle"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $seoCollapseId }}"
                aria-expanded="false"
                aria-controls="{{ $seoCollapseId }}">
                <span class="seo-settings-toggle__content">
                    <span class="seo-settings-toggle__icon"><i class="ri-search-eye-line"></i></span>
                    <span>
                        <span class="seo-settings-toggle__title">SEO Settings</span>
                        <span class="seo-settings-toggle__hint">Meta title, social preview, robots and schema</span>
                    </span>
                </span>
                <span class="seo-settings-toggle__right">
                    <span class="seo-settings-toggle__badge">Optional</span>
                    <i class="ri-arrow-down-s-line"></i>
                </span>
            </button>
        </div>
    </div>

    <div class="card-body collapse" id="{{ $seoCollapseId }}">
        <div class="d-flex justify-content-end mb-3">
            <button type="button" class="btn btn-sm btn-warning generateSeoBtn">
                Generate with AI
            </button>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Meta Title</label>
                <input type="text" name="meta_title" class="form-control"
                       value="{{ old('meta_title', $seoModel->meta_title ?? '') }}"
                       maxlength="191">
            </div>

            <div class="col-md-6">
                <label class="form-label">Canonical URL</label>
                <input type="url" name="canonical_url" class="form-control"
                       value="{{ old('canonical_url', $seoModel->canonical_url ?? '') }}">
            </div>

            <div class="col-12">
                <label class="form-label">Meta Description</label>
                <textarea name="meta_description" class="form-control" rows="3">{{ old('meta_description', $seoModel->meta_description ?? '') }}</textarea>
            </div>

            <div class="col-12">
                <label class="form-label">Meta Keywords</label>
                <textarea name="meta_keywords" class="form-control" rows="2">{{ old('meta_keywords', $seoModel->meta_keywords ?? '') }}</textarea>
            </div>

            <div class="col-md-6">
                <label class="form-label">OG Title</label>
                <input type="text" name="og_title" class="form-control"
                       value="{{ old('og_title', $seoModel->og_title ?? '') }}"
                       maxlength="191">
            </div>

            <div class="col-md-6">
                <label class="form-label">OG Image URL / Path</label>
                <input type="text" name="og_image" class="form-control"
                       value="{{ old('og_image', $seoModel->og_image ?? '') }}">
            </div>

            <div class="col-12">
                <label class="form-label">OG Description</label>
                <textarea name="og_description" class="form-control" rows="3">{{ old('og_description', $seoModel->og_description ?? '') }}</textarea>
            </div>

            <div class="col-md-4">
                <label class="form-label">Robots Meta</label>
                <select name="robots_meta" class="form-control">
                    @php $robots = old('robots_meta', $seoModel->robots_meta ?? 'index,follow'); @endphp
                    <option value="index,follow" {{ $robots === 'index,follow' ? 'selected' : '' }}>index,follow</option>
                    <option value="noindex,follow" {{ $robots === 'noindex,follow' ? 'selected' : '' }}>noindex,follow</option>
                    <option value="index,nofollow" {{ $robots === 'index,nofollow' ? 'selected' : '' }}>index,nofollow</option>
                    <option value="noindex,nofollow" {{ $robots === 'noindex,nofollow' ? 'selected' : '' }}>noindex,nofollow</option>
                </select>
            </div>

            <div class="col-12">
                <label class="form-label">SEO Schema JSON</label>
                <textarea name="seo_schema" class="form-control font-monospace" rows="5">{{ old('seo_schema', $seoModel->seo_schema ?? '') }}</textarea>
                <small class="text-muted">Optional. Leave blank to use automatic schema.</small>
            </div>
        </div>
    </div>
</div>

@once
    <style>
        .seo-settings-toggle {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 12px 14px;
            border: 1px solid var(--el-border, #dbe7e6);
            border-left: 4px solid var(--el-secondary, #f59e0b);
            border-radius: 8px;
            background: var(--el-card-bg, #fff);
            color: var(--el-heading, #111827);
            text-align: left;
            box-shadow: 0 8px 18px rgba(var(--el-primary-rgb, 15, 118, 110), .08);
        }
        .seo-settings-toggle:hover,
        .seo-settings-toggle:focus {
            border-color: var(--el-primary, #0f766e);
            background: var(--el-primary-soft, rgba(15, 118, 110, .10));
            color: var(--el-heading, #111827);
            outline: none;
        }
        .seo-settings-toggle__content,
        .seo-settings-toggle__right {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .seo-settings-toggle__icon {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: var(--theme-button-text, #fff);
            background: var(--el-primary, #0f766e);
            flex: 0 0 auto;
        }
        .seo-settings-toggle__title {
            display: block;
            font-weight: 800;
            line-height: 1.2;
        }
        .seo-settings-toggle__hint {
            display: block;
            margin-top: 2px;
            color: var(--el-muted, #64748b);
            font-size: 12px;
            font-weight: 500;
        }
        .seo-settings-toggle__badge {
            border-radius: 999px;
            padding: 4px 10px;
            background: var(--el-secondary-soft, rgba(245, 158, 11, .14));
            color: var(--el-secondary, #f59e0b);
            font-size: 12px;
            font-weight: 800;
        }
    </style>
@endonce


@once
    @push('scripts')
        <script>
            document.addEventListener('click', function (event) {
                const button = event.target.closest('.generateSeoBtn');
                if (!button) return;

                const form = button.closest('form');
                if (!form) return;

                const readField = function (names) {
                    for (const name of names) {
                        const field = form.querySelector(`[name="${name}"]`);
                        if (field && field.value) return field.value;
                    }
                    return '';
                };

                const title = readField(['name', 'title', 'group_name', 'short_title']);
                const description = readField(['description', 'instruction', 'syllabus']);
                const entityType = document.title || 'ExamElite admin item';

                button.disabled = true;
                const originalText = button.textContent;
                button.textContent = 'Generating...';

                fetch("{{ route('admin.seo.generate') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        entity_type: entityType,
                        title: title,
                        description: description,
                        url: window.location.href
                    })
                })
                .then(response => response.json())
                .then(result => {
                    if (!result.success) {
                        alert(result.message || 'SEO generation failed.');
                        return;
                    }

                    const data = result.data || {};

                    const fillSeoField = function (name, value) {
                        const field = form.querySelector(`[name="${name}"]`);
                        if (field && value !== null && value !== undefined) {
                            field.value = value;
                            field.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    };

                    fillSeoField('meta_title', data.meta_title);
                    fillSeoField('meta_description', data.meta_description);
                    fillSeoField('meta_keywords', data.meta_keywords);
                    fillSeoField('canonical_url', data.canonical_url);
                    fillSeoField('og_title', data.og_title);
                    fillSeoField('og_description', data.og_description);
                    fillSeoField('robots_meta', data.robots_meta);
                    fillSeoField('seo_schema', data.seo_schema);
                })
                .catch(() => alert('SEO generation failed.'))
                .finally(() => {
                    button.disabled = false;
                    button.textContent = originalText;
                });
            });
        </script>
    @endpush
@endonce
