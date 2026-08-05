<?php
/**
 * 主题定制器 - 配置选项页面
 * @var array $theme
 * @var array $options
 * @var array $configValues
 */
ob_start();
?>
<div class="page-header">
    <div class="page-header-left">
        <a href="<?= route('admin.themes.detail', ['name' => $theme['name']]) ?>" class="btn btn-back">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            返回详情
        </a>
        <h2>自定义 <?= e($theme['meta']['name'] ?? $theme['name']) ?></h2>
    </div>
</div>

<?php if (empty($options)): ?>
<div class="card card-empty">
    <div class="card-empty-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
    </div>
    <p>该主题没有可配置的选项</p>
    <p class="text-muted">开发者可以在 theme.json 的 options 字段中声明配置项</p>
</div>
<?php else: ?>
<form method="post" action="<?= route('admin.themes.config', ['name' => $theme['name']]) ?>" class="customize-form">
    <input type="hidden" name="_token" value="<?= csrf_token() ?>">

    <div class="customize-grid">
        <?php foreach ($options as $key => $cfg): ?>
            <?php if (!is_array($cfg)) continue; ?>
            <?php $type = $cfg['type'] ?? 'text'; ?>
            <?php $label = $cfg['label'] ?? $key; ?>
            <?php $desc = $cfg['description'] ?? ''; ?>
            <?php $currentValue = $configValues[$key] ?? ($cfg['default'] ?? ''); ?>

            <div class="customize-field customize-field-<?= e($type) ?>">
                <label class="customize-label" for="option_<?= e($key) ?>">
                    <?= e($label) ?>
                    <?php if ($desc): ?>
                        <span class="customize-desc"><?= e($desc) ?></span>
                    <?php endif; ?>
                </label>

                <?php if ($type === 'color'): ?>
                    <div class="color-picker-wrapper">
                        <input type="color" name="options[<?= e($key) ?>]" id="option_<?= e($key) ?>"
                               value="<?= e($currentValue) ?>" class="color-picker">
                        <input type="text" class="color-hex-input" value="<?= e($currentValue) ?>"
                               data-target="option_<?= e($key) ?>">
                    </div>

                <?php elseif ($type === 'select' && !empty($cfg['choices'])): ?>
                    <select name="options[<?= e($key) ?>]" id="option_<?= e($key) ?>" class="customize-select">
                        <?php foreach ($cfg['choices'] as $val => $label): ?>
                            <option value="<?= e($val) ?>" <?= $currentValue === $val ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                <?php elseif ($type === 'textarea'): ?>
                    <textarea name="options[<?= e($key) ?>]" id="option_<?= e($key) ?>"
                              class="customize-textarea" rows="5"><?= e($currentValue) ?></textarea>

                <?php elseif ($type === 'checkbox'): ?>
                    <label class="checkbox-label">
                        <input type="hidden" name="options[<?= e($key) ?>]" value="0">
                        <input type="checkbox" name="options[<?= e($key) ?>]" value="1"
                               id="option_<?= e($key) ?>" <?= $currentValue ? 'checked' : '' ?>>
                        <span class="checkbox-custom"></span>
                        <?= e($label) ?>
                    </label>

                <?php elseif ($type === 'number'): ?>
                    <input type="number" name="options[<?= e($key) ?>]" id="option_<?= e($key) ?>"
                           value="<?= e($currentValue) ?>" class="customize-input"
                           <?php if (isset($cfg['min'])): echo 'min="' . e($cfg['min']) . '"'; endif; ?>
                           <?php if (isset($cfg['max'])): echo 'max="' . e($cfg['max']) . '"'; endif; ?>>

                <?php else: ?>
                    <input type="text" name="options[<?= e($key) ?>]" id="option_<?= e($key) ?>"
                           value="<?= e($currentValue) ?>" class="customize-input">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="customize-actions">
        <button type="submit" class="btn btn-primary btn-lg">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            保存配置
        </button>
        <a href="<?= route('admin.themes.detail', ['name' => $theme['name']]) ?>" class="btn btn-secondary">取消</a>
    </div>
</form>
<?php endif; ?>

<script>
(function() {
    // 颜色选择器联动：hex 输入 ↔ color picker
    var colorPickers = document.querySelectorAll('.color-picker');
    colorPickers.forEach(function(picker) {
        var hexInput = picker.closest('.color-picker-wrapper').querySelector('.color-hex-input');
        if (!hexInput) return;
        picker.addEventListener('input', function() {
            hexInput.value = this.value;
        });
        hexInput.addEventListener('input', function() {
            if (/^#[0-9a-fA-F]{6}$/.test(this.value)) {
                picker.value = this.value;
            }
        });
        hexInput.addEventListener('blur', function() {
            if (!/^#[0-9a-fA-F]{6}$/.test(this.value)) {
                this.value = picker.value;
            }
        });
    });
})();
</script>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');