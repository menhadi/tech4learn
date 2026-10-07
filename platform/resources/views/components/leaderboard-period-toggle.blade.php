@php
    $leaderboardPeriod = request('leaderboard_period', 'year') === 'all' ? 'all' : 'year';
    $leaderboardYearUrl = request()->fullUrlWithQuery(['leaderboard_period' => 'year']);
    $leaderboardAllUrl = request()->fullUrlWithQuery(['leaderboard_period' => 'all']);
    $leaderboardPanelKey = $leaderboardPanelKey ?? 'leaderboard';
    $leaderboardApiUrl = $leaderboardApiUrl ?? null;
    $leaderboardApiSeparator = $leaderboardApiUrl && str_contains($leaderboardApiUrl, '?') ? '&' : '?';
    $leaderboardYearApi = $leaderboardApiUrl ? $leaderboardApiUrl . $leaderboardApiSeparator . 'leaderboard_period=year' : null;
    $leaderboardAllApi = $leaderboardApiUrl ? $leaderboardApiUrl . $leaderboardApiSeparator . 'leaderboard_period=all' : null;
@endphp
<div class="d-inline-flex gap-1 p-1 rounded mb-3" style="background:var(--theme-surface-muted, var(--el-surface-muted, #f1f5f9));" role="group" aria-label="Leaderboard period">
    <a href="{{ $leaderboardYearUrl }}" data-leaderboard-key="{{ $leaderboardPanelKey }}" data-leaderboard-api="{{ $leaderboardYearApi }}" class="btn btn-sm js-leaderboard-period {{ $leaderboardPeriod === 'year' ? 'btn-primary' : 'btn-light' }}">Current Year</a>
    <a href="{{ $leaderboardAllUrl }}" data-leaderboard-key="{{ $leaderboardPanelKey }}" data-leaderboard-api="{{ $leaderboardAllApi }}" class="btn btn-sm js-leaderboard-period {{ $leaderboardPeriod === 'all' ? 'btn-primary' : 'btn-light' }}">All Time</a>
</div>

@once
<script>
(function () {
    const cache = window.__leaderboardCache || (window.__leaderboardCache = new Map());

    async function fetchText(url) {
        if (cache.has(url)) return cache.get(url);
        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) throw new Error('Leaderboard request failed');
        const text = await response.text();
        cache.set(url, text);
        return text;
    }

    document.addEventListener('click', async function (event) {
        const link = event.target.closest('.js-leaderboard-period');
        if (!link) return;

        event.preventDefault();
        const key = link.dataset.leaderboardKey;
        const panel = document.querySelector('[data-leaderboard-panel="' + key + '"]');
        if (!panel || panel.dataset.loading === '1') return;

        panel.dataset.loading = '1';
        panel.style.opacity = '.65';
        panel.style.pointerEvents = 'none';

        try {
            if (link.dataset.leaderboardApi) {
                const rows = panel.querySelector('[data-leaderboard-rows]');
                if (!rows) throw new Error('Leaderboard rows missing');
                rows.innerHTML = await fetchText(link.dataset.leaderboardApi);
                panel.querySelectorAll('.js-leaderboard-period').forEach(function (button) {
                    button.classList.toggle('btn-primary', button.href === link.href);
                    button.classList.toggle('btn-light', button.href !== link.href);
                });
            } else {
                const html = await fetchText(link.href);
                const documentHtml = new DOMParser().parseFromString(html, 'text/html');
                const updatedPanel = documentHtml.querySelector('[data-leaderboard-panel="' + key + '"]');
                if (!updatedPanel) throw new Error('Leaderboard panel missing');
                panel.replaceWith(updatedPanel);
            }
            window.history.replaceState({}, '', link.href);
        } catch (error) {
            window.location.href = link.href;
            return;
        }

        const activePanel = document.querySelector('[data-leaderboard-panel="' + key + '"]');
        if (activePanel) {
            activePanel.dataset.loading = '0';
            activePanel.style.opacity = '';
            activePanel.style.pointerEvents = '';
        }
    });
})();
</script>
@endonce
