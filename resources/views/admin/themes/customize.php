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
<style>
/* 定制器全宽布局：用 margin 补偿 admin-content 的 padding */
.customizer-layout {
  margin: -28px;
  height: calc(100vh - var(--header-h, 60px));
  display: flex;
  flex-direction: column;
  background: var(--c-bg);
  position: relative;
  overflow: hidden;
}

/* 定制器头部：替代 page-header，固定在顶部 */
.customizer-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 28px;
  background: var(--c-bg-card);
  border-bottom: 1px solid var(--c-border);
  flex-shrink: 0;
  gap: 12px;
  min-height: 56px;
}

.customizer-header-left {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.customizer-header-left h2 {
  font-size: 1.15rem;
  font-weight: 700;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.customizer-header-actions {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

/* 定制器主体：flex 双栏 */
.customizer-body {
  flex: 1;
  display: flex;
  min-height: 0;
  overflow: hidden;
}

.customizer-panel-left {
  width: 400px;
  min-width: 360px;
  max-width: 460px;
  background: var(--c-bg-card);
  border-right: 1px solid var(--c-border);
  overflow-y: auto;
  flex-shrink: 0;
}

.customizer-panel-right {
  flex: 1;
  display: flex;
  flex-direction: column;
  background: var(--c-bg);
  min-width: 0;
  overflow: hidden;
}

@media (max-width: 1024px) {
  .customizer-body {
    flex-direction: column;
  }
  .customizer-panel-left {
    width: 100%;
    min-width: 0;
    max-width: 100%;
    border-right: none;
    border-bottom: 1px solid var(--c-border);
    max-height: 45vh;
    overflow-y: auto;
  }
  .customizer-panel-right {
    flex: 1;
    min-height: 55vh;
  }
}

@media (max-width: 768px) {
  .customizer-layout {
    margin: -16px;
  }
  .customizer-header {
    padding: 10px 16px;
    flex-wrap: wrap;
    min-height: 48px;
  }
  .customizer-header-left h2 {
    font-size: 1rem;
  }
  .customizer-panel-left {
    max-height: 40vh;
  }
  .customizer-panel-right {
    min-height: 60vh;
  }
}
</style>

<div class="customizer-layout">
    <!-- 定制器头部 -->
    <div class="customizer-header">
        <div class="customizer-header-left">
            <a href="<?= route('admin.themes.detail', ['name' => $theme['name']]) ?>" class="btn btn-back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                返回
            </a>
            <h2>自定义 <?= e($theme['meta']['name'] ?? $theme['name']) ?></h2>
        </div>
        <div class="customizer-header-actions">
            <a href="<?= route('admin.themes.revisions', ['name' => $theme['name']]) ?>" class="btn btn-secondary" title="配置历史">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
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

    <!-- 定制器主体：左右双栏 -->
    <div class="customizer-body">

<?php if (empty($groupedOptions)): ?>
<div class="card card-empty">
    <div class="card-empty-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
    </div>
    <p>该主题没有可配置的选项</p>
    <p class="text-muted">开发者可以在 theme.json 的 options 字段中声明配置项</p>
</div>
<?php else: ?>

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
                    sandbox="allow-scripts allow-same-origin allow-forms"></iframe>
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

    // ── Text/number 输入触发预览 ──
    document.querySelectorAll('.customize-input').forEach(function(inp) {
        inp.addEventListener('input', debounce(triggerPreview, 300));
    });

    // ── Textarea 触发预览 ──
    document.querySelectorAll('.customize-textarea').forEach(function(ta) {
        ta.addEventListener('input', debounce(triggerPreview, 300));
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

    // ── 实时预览（混合策略 Phase 2） ──
    // 策略说明：
    //   1. postMessage：CSS 变量选项即时更新，无刷新
    //   2. POST + sessionStorage + iframe 轻量重载：非 CSS 变量选项（如 layout、footer_text）
    //      先通过 POST 存储配置到 session，再重载 iframe（无 URL 参数，避免长度限制）
    //   3. sessionStorage 回退：iframe 加载时从 sessionStorage 读取配置作为兜底

    /** 预览配置存储端点 URL */
    var storeConfigUrl = '<?= route('admin.themes.preview-config', ['name' => $theme['name']]) ?>';
    /** 上次发送的配置快照，用于检测变更 */
    window._lastPreviewConfig = null;
    /** CSRF Token */
    var csrfToken = '<?= csrf_token() ?>';

    /** 收集表单配置和 CSS 变量映射 */
    function collectConfig() {
        var config = {};
        var cssVars = {};
        var form = document.getElementById('customizeForm');
        if (!form) return { config: {}, cssVars: {} };
        var formData = new FormData(form);
        formData.forEach(function(value, key) {
            var match = key.match(/^options\[(.+)\]$/);
            if (match) {
                config[match[1]] = value;
            }
        });
        form.querySelectorAll('[data-css-var]').forEach(function(el) {
            var key = el.name ? el.name.match(/^options\[(.+)\]$/) : null;
            if (key && key[1] && el.dataset.cssVar) {
                cssVars[key[1]] = el.dataset.cssVar;
            }
        });
        return { config: config, cssVars: cssVars };
    }

    /** 发送配置到 iframe（postMessage + sessionStorage 双重保障） */
    function sendConfigToIframe(config, cssVars) {
        // 1. sessionStorage 持久化（iframe 加载时使用）
        try {
            sessionStorage.setItem('theme_preview_config', JSON.stringify(config));
            sessionStorage.setItem('theme_preview_cssVars', JSON.stringify(cssVars));
        } catch(e) {
            // sessionStorage 不可用时忽略
        }

        // 2. postMessage 即时更新
        if (previewFrame && previewFrame.contentWindow) {
            try {
                previewFrame.contentWindow.postMessage({
                    type: 'theme-config',
                    config: config,
                    cssVars: cssVars
                }, '*');
            } catch(e) {
                console.warn('postMessage 发送失败，将使用 sessionStorage 回退:', e);
            }
        }
    }

    /** 存储配置到服务器 session（POST），用于非 CSS 选项重载 */
    function storeConfigOnServer(config, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', storeConfigUrl, true);
        // 使用 application/x-www-form-urlencoded 而非 JSON，避免 PHP 内置服务器解析失败
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                if (callback) callback();
            } else {
                console.warn('存储预览配置失败:', xhr.status, xhr.responseText);
                // 即使失败也执行回调，尝试重载
                if (callback) callback();
            }
        };
        xhr.onerror = function() {
            console.warn('存储预览配置网络错误');
            showPreviewError('预览配置存储失败，请重试');
            if (callback) callback();
        };
        // 序列化为 URL 编码格式
        var params = '_token=' + encodeURIComponent(csrfToken) + '&config=' + encodeURIComponent(JSON.stringify(config));
        xhr.send(params);
    }

    /** 显示预览错误提示 */
    function showPreviewError(msg) {
        var toolbar = document.getElementById('previewToolbar');
        if (!toolbar) return;
        var errEl = toolbar.querySelector('.preview-error');
        if (!errEl) {
            errEl = document.createElement('span');
            errEl.className = 'preview-error';
            errEl.style.cssText = 'color:#e53935;font-size:12px;margin-left:8px;';
            toolbar.appendChild(errEl);
        }
        errEl.textContent = '⚠ ' + msg;
        setTimeout(function() { errEl.textContent = ''; }, 5000);
    }

    /** 触发预览更新 */
    function triggerPreview() {
        var collected = collectConfig();
        var config = collected.config;
        var cssVars = collected.cssVars;
        if (Object.keys(config).length === 0) return;

        // 1. 始终通过 postMessage + sessionStorage 发送（CSS 变量即时更新）
        sendConfigToIframe(config, cssVars);

        // 2. 检测是否有非 CSS 变量选项变更，需要重载 iframe
        var needsReload = false;
        if (window._lastPreviewConfig) {
            for (var key in config) {
                if (config.hasOwnProperty(key)) {
                    if (!cssVars[key] && config[key] !== window._lastPreviewConfig[key]) {
                        needsReload = true;
                        break;
                    }
                }
            }
        }
        // 保存当前配置快照
        window._lastPreviewConfig = JSON.parse(JSON.stringify(config));

        if (needsReload) {
            // 先存储到服务器 session，再重载 iframe（无 URL 参数）
            storeConfigOnServer(config, function() {
                queuePreviewReload();
            });
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

    /** 防抖重载 iframe（无 URL 参数，配置通过 session 传递） */
    var _reloadTimer = null;
    function queuePreviewReload() {
        if (_reloadTimer) clearTimeout(_reloadTimer);
        _reloadTimer = setTimeout(function() {
            if (previewFrame) {
                previewFrame.src = livePreviewUrl;
            }
        }, 400);
    }

    // ── iframe 加载完成后应用配置 ──
    previewFrame.addEventListener('load', function() {
        // 等待 iframe 内脚本初始化完成
        setTimeout(triggerPreview, 300);
    });

    // ── 页面加载完成后也发送一次初始配置 ──
    if (document.readyState === 'complete') {
        setTimeout(triggerPreview, 500);
    } else {
        window.addEventListener('load', function() {
            setTimeout(triggerPreview, 500);
        });
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

    // 合并的 change 事件监听：触发预览 + show_if 更新
    var form = document.getElementById('customizeForm');
    if (form) {
        form.addEventListener('change', function(e) {
            if (e.target && e.target.closest('.customize-field')) {
                triggerPreview();
            }
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