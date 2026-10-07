@php
    $socialDefaults = [
        'enabled' => true,
        'platforms' => ['native', 'facebook', 'x', 'linkedin', 'whatsapp', 'telegram', 'email', 'copy'],
        'profiles' => [],
    ];
    $socialSettings = array_replace($socialDefaults, is_array($configuration->social_sharing_settings ?? null) ? $configuration->social_sharing_settings : []);
    $sharePlatforms = array_values(array_intersect($socialSettings['platforms'] ?? [], array_keys([
        'native' => 1, 'facebook' => 1, 'x' => 1, 'linkedin' => 1, 'whatsapp' => 1,
        'telegram' => 1, 'reddit' => 1, 'pinterest' => 1, 'email' => 1, 'copy' => 1,
    ])));
    $shareLabels = ['native' => 'Share', 'facebook' => 'Facebook', 'x' => 'X', 'linkedin' => 'LinkedIn', 'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'reddit' => 'Reddit', 'pinterest' => 'Pinterest', 'email' => 'Email', 'copy' => 'Copy link'];
    $shareIcons = ['native' => 'ri-share-forward-line', 'facebook' => 'ri-facebook-fill', 'x' => 'ri-twitter-x-line', 'linkedin' => 'ri-linkedin-fill', 'whatsapp' => 'ri-whatsapp-line', 'telegram' => 'ri-telegram-line', 'reddit' => 'ri-reddit-line', 'pinterest' => 'ri-pinterest-line', 'email' => 'ri-mail-line', 'copy' => 'ri-link'];
@endphp

@if($socialSettings['enabled'] && count($sharePlatforms))
<div class="el-share" id="elShare" data-platforms='@json($sharePlatforms)'>
    <button type="button" class="el-share-toggle" aria-expanded="false" aria-controls="elSharePanel"><i class="ri-share-line"></i><span>Share</span></button>
    <div class="el-share-panel" id="elSharePanel" hidden>
        <div class="el-share-heading"><strong>Share this page</strong><button type="button" class="el-share-close" aria-label="Close"><i class="ri-close-line"></i></button></div>
        <div class="el-share-grid">
            @foreach($sharePlatforms as $platform)
                <button type="button" class="el-share-action" data-share-platform="{{ $platform }}"><i class="{{ $shareIcons[$platform] }}"></i><span>{{ $shareLabels[$platform] }}</span></button>
            @endforeach
        </div>
        <div class="el-share-status" role="status" aria-live="polite"></div>
    </div>
</div>
<style>
    .el-share{position:fixed;right:22px;bottom:82px;z-index:1040;font-family:inherit}.el-share-toggle{align-items:center;background:var(--theme-primary,#0f766e);border:0;border-radius:999px;box-shadow:0 10px 28px rgba(15,23,42,.22);color:#fff;display:flex;font-weight:700;gap:7px;padding:11px 17px}.el-share-toggle i{font-size:20px}.el-share-panel{background:#fff;border:1px solid #e2e8f0;border-radius:16px;bottom:54px;box-shadow:0 18px 50px rgba(15,23,42,.2);padding:16px;position:absolute;right:0;width:min(330px,calc(100vw - 28px))}.el-share-heading{align-items:center;color:#0f172a;display:flex;justify-content:space-between;margin-bottom:12px}.el-share-close{background:transparent;border:0;color:#64748b;font-size:20px}.el-share-grid{display:grid;gap:8px;grid-template-columns:repeat(2,1fr)}.el-share-action{align-items:center;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;color:#334155;display:flex;font-size:13px;font-weight:650;gap:8px;padding:10px;text-align:left}.el-share-action:hover{background:#f0fdfa;border-color:var(--theme-primary,#0f766e);color:var(--theme-primary,#0f766e)}.el-share-action i{font-size:18px}.el-share-status{color:#0f766e;font-size:12px;min-height:18px;padding-top:8px}@media(max-width:575px){.el-share{bottom:72px;right:14px}.el-share-toggle span{display:none}.el-share-toggle{height:46px;justify-content:center;padding:0;width:46px}}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('elShare'); if (!root) return;
    const toggle = root.querySelector('.el-share-toggle'), panel = root.querySelector('.el-share-panel'), status = root.querySelector('.el-share-status');
    const close = () => { panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); };
    toggle.addEventListener('click', () => { panel.hidden = !panel.hidden; toggle.setAttribute('aria-expanded', String(!panel.hidden)); });
    root.querySelector('.el-share-close').addEventListener('click', close);
    document.addEventListener('click', e => { if (!root.contains(e.target)) close(); });
    root.querySelectorAll('[data-share-platform]').forEach(button => button.addEventListener('click', async () => {
        const platform = button.dataset.sharePlatform, url = location.href, title = document.title, text = document.querySelector('meta[name="description"]')?.content || title;
        const u = encodeURIComponent(url), t = encodeURIComponent(title), body = encodeURIComponent(text + ' ' + url);
        const links = {
            facebook: `https://www.facebook.com/sharer/sharer.php?u=${u}`, x: `https://twitter.com/intent/tweet?text=${t}&url=${u}`,
            linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${u}`, whatsapp: `https://api.whatsapp.com/send?text=${body}`,
            telegram: `https://t.me/share/url?url=${u}&text=${t}`, reddit: `https://www.reddit.com/submit?url=${u}&title=${t}`,
            pinterest: `https://pinterest.com/pin/create/button/?url=${u}&description=${t}`, email: `mailto:?subject=${t}&body=${body}`
        };
        try {
            if (platform === 'native') { if (navigator.share) await navigator.share({title, text, url}); else await navigator.clipboard.writeText(url); }
            else if (platform === 'copy') await navigator.clipboard.writeText(url);
            else window.open(links[platform], '_blank', 'noopener,noreferrer,width=720,height=620');
            if (platform === 'copy' || (platform === 'native' && !navigator.share)) status.textContent = 'Link copied.';
        } catch (error) { if (error.name !== 'AbortError') status.textContent = 'Unable to share. Please copy the page address.'; }
    }));
});
</script>
@endif
