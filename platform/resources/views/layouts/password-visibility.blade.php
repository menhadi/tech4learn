<style>
.el-password-visibility { position: relative; width: 100%; }
.el-password-visibility > input { padding-right: 46px !important; }
.el-password-visibility-toggle {
    align-items: center; background: transparent; border: 0; color: var(--el-primary, #0f766e);
    cursor: pointer; display: inline-flex; height: 38px; justify-content: center; padding: 0;
    position: absolute; right: 5px; top: 50%; transform: translateY(-50%); width: 38px; z-index: 5;
}
.el-password-visibility-toggle:hover, .el-password-visibility-toggle:focus {
    color: var(--el-secondary, #f59e0b); outline: none;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[type="password"]').forEach(function (input) {
        if (input.dataset.passwordVisibilityReady === '1' || input.dataset.noPasswordToggle === '1') return;
        if (input.closest('.input-group') || input.parentElement.querySelector('.password-toggle, .password-addon, [data-password-toggle]')) return;

        input.dataset.passwordVisibilityReady = '1';
        const wrapper = document.createElement('div');
        wrapper.className = 'el-password-visibility';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'el-password-visibility-toggle';
        button.setAttribute('aria-label', 'Show password');
        button.setAttribute('title', 'Show password');
        button.innerHTML = '<i class="ri-eye-line" aria-hidden="true"></i>';

        button.addEventListener('click', function () {
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            button.setAttribute('title', showing ? 'Show password' : 'Hide password');
            button.innerHTML = showing ? '<i class="ri-eye-line" aria-hidden="true"></i>' : '<i class="ri-eye-off-line" aria-hidden="true"></i>';
        });

        wrapper.appendChild(button);
    });
});
</script>
