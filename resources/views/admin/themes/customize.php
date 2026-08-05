<?php
/**
 * 主题定制器 - 增强版
 * 左面板：配置分组 + 表单
 * 右面板：实时预览 iframe
 * @var array $theme
 * @var array $groupedOptions
 * @var array $configValues
 * @var array $snapshots
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
    <div class="page-header-actions">
        <a href="<?= route('admin.themes.revisions', ['name' => $theme['name']]) ?>" class="btn btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            配置历史
        </a>
        <button type="button" class="btn btn-secondary" id="deviceDesktop" title="桌面端">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        </button>
        <button type="button" class="btn btn-secondary" id="deviceTablet" title="平板端">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
        </button>
        <button type="button" class="btn btn-secondary" id="deviceMobile" title="手机端">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
        </button>
    </div>
</div>

<?php if (empty($groupedOptions)): ?>
<div class="card card-empty">
    <div class="card-empty-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
    </div>
    <p>该主题没有可配置的选项</p>
    <p class="text-muted">开发者可以在 theme.json 的 options 字段中声明配置项</p>
</div>
<?php else: ?>

<div class="customizer-layout">
    <!-- 左面板：配置表单 -->
    <div class="customizer-panel-left">
        <form method="post" action="<?= route('admin.themes.config', ['name' => $theme['name']]) ?>" class="customize-form" id="customizeForm">
            <input type="hidden" name="_token" value="<?= csrf_token() ?>">

            <div class="customizer-sections">
                <?php foreach ($groupedOptions as $gIdx => $group): ?>
                <div class="customizer-section">
                    <div class="customizer-section-header <?= $gIdx === 0 ? 'expanded' : '' ?>" data-section="<?= $gIdx ?>">
                        <div class="section-header-left">
                            <h3 class="customizer-section-title"><?= e($group['section']) ?></h3>
                            <?php if (!empty($group['description'])): ?>
                                <span class="customizer-section-desc"><?= e($group['description']) ?></span>
                            <?php endif; ?>
                        </div>
                        <svg class="section-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                    <div class="customizer-section-body" style="<?= $gIdx === 0 ? '' : 'display:none;' ?>">
                        <?php foreach ($group['fields'] as $key => $cfg): ?>
                            <?php if (!is_array($cfg)) continue; ?>
                            <?php $type = $cfg['type'] ?? 'text'; ?>
                            <?php $label = $cfg['label'] ?? $key; ?>
                            <?php $desc = $cfg['description'] ?? ''; ?>
                            <?php $currentValue = $configValues[$key] ?? ($cfg['default'] ?? ''); ?>
                            <?php
                            // 检查 show_if 条件
                            $showIf = $cfg['show_if'] ?? null;
                            $showIfAttr = '';
                            if ($showIf && is_array($showIf)) {
                                $showIfAttr = 'data-show-if="' . e(json_encode($showIf)) . '"';
                            }
                            ?>

                            <div class="customize-field customize-field-<?= e($type) ?>" <?= $showIfAttr ?> data-field-key="<?= e($key) ?>">
                                <label class="customize-label" for="option_<?= e($key) ?>">
                                    <?= e($label) ?>
                                    <?php if ($desc): ?>
                                        <span class="customize-desc"><?= e($desc) ?></span>
                                    <?php endif; ?>
                                </label>

                                <?php if ($type === 'color'): ?>
                                    <div class="color-picker-wrapper">
                                        <input type="color" name="options[<?= e($key) ?>]" id="option_<?= e($key) ?>"
                                               value="<?= e($currentValue) ?>" class="color-picker" data-css-var="<?= e($cfg['css_var'] ?? '') ?>">
                                        <input type="text" class="color-hex-input" value="<?= e($currentValue) ?>"
                                               data-target="option_<?= e($key) ?>">
                                    </div>

                                <?php elseif ($type === 'select' && !empty($cfg['choices'])): ?>
                                    <select name="options[<?= e($key) ?>]" id="option_<?= e($key) ?>" class="customize-select" data-css-var="<?= e($cfg['css_var'] ?? '') ?>">
                                        <?php foreach ($cfg['choices'] as $val => $choiceLabel): ?>
                                            <option value="<?= e($val) ?>" <?= $currentValue === $val ? 'selected' : '' ?>>
                                                <?= e($choiceLabel) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                <?php elseif ($type === 'range'): ?>
                                    <div class="range-wrapper">
                                        <input type="range" name="options[<?= e($key) ?>]" id="option_<?= e($key) ?>"
                                               value="<?= e($currentValue) ?>" class="customize-range"
                                               min="<?= e($cfg['min'] ?? 0) ?>" max="<?= e($cfg['max'] ?? 100) ?>"
                                               step="<?= e($cfg['step'] ?? 1) ?>"
                                               data-css-var="<?= e($cfg['css_var'] ?? '') ?>"
                                               data-unit="<?= e($cfg['unit'] ?? '') ?>">
                                        <span class="range-value"><?= e($currentValue) ?><?= e($cfg['unit'] ?? '') ?></span>
                                    </div>

                                <?php elseif ($type === 'switch'): ?>
                                    <label class="switch-label">
                                        <input type="hidden" name="options[<?= e($key) ?>]" value="0">
                                        <input type="checkbox" name="options[<?= e($key) ?>]" value="1"
                                               id="option_<?= e($key) ?>" <?= $currentValue ? 'checked' : '' ?>
                                               class="switch-input" data-css-var="<?= e($cfg['css_var'] ?? '') ?>">
                                        <span class="switch-track">
                                            <span class="switch-thumb"></span>
                                        </span>
                                    </label>

                                <?php elseif ($type === 'image'): ?>
                                    <div class="image-picker-wrapper">
                                        <div class="image-preview" id="preview_<?= e($key) ?>">
                                            <?php if ($currentValue): ?>
                                                <img src="<?= e($currentValue) ?>" alt="预览">
                                            <?php endif; ?>
                                        </div>
                                        <div class="image-picker-actions">
                                            <input type="text" name="options[<?= e($key) ?>]" id="option_<?= e($key) ?>"
                                                   value="<?= e($currentValue) ?>" class="customize-input image-url-input"
                                                   placeholder="输入图片 URL 或上传">
                                            <button type="button" class="btn btn-sm btn-secondary upload-image-btn"
                                                    data-target="option_<?= e($key) ?>">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                                上传
                                            </button>
                                            <?php if ($currentValue): ?>
                                                <button type="button" class="btn btn-sm btn-link image-remove-btn"
                                                        data-target="option_<?= e($key) ?>">移除</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                <?php elseif ($type === 'code'): ?>
                                    <div class="code-editor-wrapper">
                                        <textarea name="options[<?= e($key) ?>]" id="option_<?= e($key) ?>"
                                                  class="customize-code-editor" rows="8"
                                                  data-language="<?= e($cfg['language'] ?? 'css') ?>"
                                                  spellcheck="false"><?= e($currentValue) ?></textarea>
                                    </div>

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
                                           value="<?= e($currentValue) ?>" class="customize-input"
                                           data-css-var="<?= e($cfg['css_var'] ?? '') ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="customize-actions">
                <button type="submit" class="btn btn-primary btn-lg" id="saveConfigBtn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    保存配置
                </button>
                <a href="<?= route('admin.themes.detail', ['name' => $theme['name']]) ?>" class="btn btn-secondary">取消</a>
            </div>
        </form>
    </div>

    <!-- 右面板：实时预览 -->
    <div class="customizer-panel-right">
        <div class="customizer-preview-toolbar" id="previewToolbar">
            <div class="preview-mode-tabs">
                <button type="button" class="preview-mode-tab active" data-mode="light" title="浅色模式">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    浅色
                </button>
                <button type="button" class="preview-mode-tab" data-mode="dark" title="深色模式">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    深色
                </button>
            </div>
            <span class="preview-status">实时预览</span>
        </div>
        <div class="customizer-preview-frame" id="previewFrame">
            <iframe src="<?= route('admin.themes.preview-ajax', ['name' => $theme['name']]) ?>" id="livePreviewIframe"
                    sandbox="allow-scripts allow-same-origin allow-forms" loading="lazy"></iframe>
        </div>
    </div>
</div>

<script>
(function() {
    var livePreviewUrl = '<?= route('admin.themes.preview-ajax', ['name' => $theme['name']]) ?>';

    // ── 设备切换 ──
    var previewFrame = document.getElementById('livePreviewIframe');
    var frameWrap = document.getElementById('previewFrame');

    document.getElementById('deviceDesktop').addEventListener('click', function() {
        frameWrap.className = 'customizer-preview-frame device-desktop';
    });
    document.getElementById('deviceTablet').addEventListener('click', function() {
        frameWrap.className = 'customizer-preview-frame device-tablet';
    });
    document.getElementById('deviceMobile').addEventListener('click', function() {
        frameWrap.className = 'customizer-preview-frame device-mobile';
    });

    // ── 分区折叠 ──
    document.querySelectorAll('.customizer-section-header').forEach(function(header) {
        header.addEventListener('click', function() {
            var body = this.nextElementSibling;
            if (body.style.display === 'none') {
                body.style.display = '';
                this.classList.add('expanded');
            } else {
                body.style.display = 'none';
                this.classList.remove('expanded');
            }
        });
    });

    // ── 颜色选择器联动 ──
    document.querySelectorAll('.color-picker-wrapper').forEach(function(wrap) {
        var picker = wrap.querySelector('.color-picker');
        var hexInput = wrap.querySelector('.color-hex-input');
        if (!picker || !hexInput) return;
        picker.addEventListener('input', function() {
            hexInput.value = this.value;
            triggerPreview();
        });
        hexInput.addEventListener('input', function() {
            if (/^#[0-9a-fA-F]{6}$/.test(this.value)) {
                picker.value = this.value;
                triggerPreview();
            }
        });
        hexInput.addEventListener('blur', function() {
            if (!/^#[0-9a-fA-F]{6}$/.test(this.value)) {
                this.value = picker.value;
            }
        });
    });

    // ── Range 值显示联动 ──
    document.querySelectorAll('.customize-range').forEach(function(range) {
        var valueSpan = range.closest('.range-wrapper').querySelector('.range-value');
        if (!valueSpan) return;
        range.addEventListener('input', function() {
            var unit = this.dataset.unit || '';
            valueSpan.textContent = this.value + unit;
            triggerPreview();
        });
    });

    // ── Select 变更触发预览 ──
    document.querySelectorAll('.customize-select').forEach(function(sel) {
        sel.addEventListener('change', triggerPreview);
    });

    // ── Switch 变更触发预览 ──
    document.querySelectorAll('.switch-input').forEach(function(inp) {
        inp.addEventListener('change', triggerPreview);
    });

    // ── Text 输入触发预览 ──
    document.querySelectorAll('.customize-input[data-css-var]').forEach(function(inp) {
        inp.addEventListener('input', debounce(triggerPreview, 300));
    });

    // ── Code 编辑器触发预览 ──
    document.querySelectorAll('.customize-code-editor').forEach(function(ta) {
        ta.addEventListener('input', debounce(triggerPreview, 500));
    });

    // ── 图片上传联动 ──
    document.querySelectorAll('.upload-image-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var targetId = this.dataset.target;
            var input = document.getElementById(targetId);
            if (!input) return;
            var url = prompt('请输入图片 URL：', input.value);
            if (url) {
                input.value = url;
                var preview = document.getElementById('preview_' + targetId.replace('option_', ''));
                if (preview) {
                    preview.innerHTML = '<img src="' + url + '" alt="预览">';
                }
                triggerPreview();
            }
        });
    });

    document.querySelectorAll('.image-remove-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var targetId = this.dataset.target;
            var input = document.getElementById(targetId);
            if (!input) return;
            input.value = '';
            var preview = document.getElementById('preview_' + targetId.replace('option_', ''));
            if (preview) preview.innerHTML = '';
            this.style.display = 'none';
            triggerPreview();
        });
    });

    // ── 图片 URL 输入预览 ──
    document.querySelectorAll('.image-url-input').forEach(function(inp) {
        inp.addEventListener('input', debounce(function() {
            var preview = document.getElementById('preview_' + this.id.replace('option_', ''));
            if (preview) {
                preview.innerHTML = this.value ? '<img src="' + this.value + '" alt="预览">' : '';
            }
            triggerPreview();
        }, 300));
    });

    // ── 预览模式切换（浅色/深色） ──
    document.querySelectorAll('.preview-mode-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.preview-mode-tab').forEach(function(t) { t.classList.remove('active'); });
            this.classList.add('active');
            var mode = this.dataset.mode;
            if (previewFrame) {
                previewFrame.contentWindow.postMessage({ type: 'theme-mode', mode: mode }, '*');
            }
        });
    });

    // ── 实时预览 ──
    function triggerPreview() {
        var config = {};
        var form = document.getElementById('customizeForm');
        if (!form) return;
        var formData = new FormData(form);
        formData.forEach(function(value, key) {
            // 只提取 options[...] 字段
            var match = key.match(/^options\[(.+)\]$/);
            if (match) {
                config[match[1]] = value;
            }
        });
        if (previewFrame && previewFrame.contentWindow) {
            previewFrame.contentWindow.postMessage({
                type: 'theme-config',
                config: config
            }, '*');
        }
    }

    function debounce(fn, delay) {
        var timer = null;
        return function() {
            var args = arguments;
            var ctx = this;
            clearTimeout(timer);
            timer = setTimeout(function() { fn.apply(ctx, args); }, delay);
        };
    }

    // ── 保存时创建快照 ──
    document.getElementById('saveConfigBtn').addEventListener('click', function(e) {
        // 先提交表单，不做额外拦截（表单提交到 saveConfig 路由）
        // saveConfig 路由会自动创建快照
    });

    // ── show_if 条件显示 ──
    function updateShowIfFields() {
        document.querySelectorAll('[data-show-if]').forEach(function(field) {
            try {
                var condition = JSON.parse(field.dataset.showIf);
                var allMet = true;
                for (var condKey in condition) {
                    var condVal = condition[condKey];
                    var sourceInput = document.querySelector('[name="options[' + condKey + ']"]');
                    if (!sourceInput) { allMet = false; break; }
                    var actualVal = sourceInput.type === 'checkbox' ? (sourceInput.checked ? '1' : '0') : sourceInput.value;
                    if (actualVal !== condVal) { allMet = false; break; }
                }
                field.style.display = allMet ? '' : 'none';
            } catch(e) {}
        });
    }

    // 监听 show_if 依赖字段的变化
    var form = document.getElementById('customizeForm');
    if (form) {
        form.addEventListener('change', function(e) {
            if (e.target.name && e.target.name.startsWith('options[')) {
                updateShowIfFields();
            }
        });
        setTimeout(updateShowIfFields, 100);
    }
})();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');