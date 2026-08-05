<?php
ob_start();
?>
<div class="page-header">
    <h2>标签管理</h2>
    <div class="page-header-actions">
        <button class="btn btn-primary" data-toggle-form="tag-form">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            新建标签
        </button>
    </div>
</div>

<div id="tag-form" class="card mb-16 form-toggle">
    <form method="post" action="<?= route('admin.tags.store') ?>">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <div class="form-row">
            <div class="form-group"><label>名称</label><input type="text" name="name" class="form-control" required></div>
            <div class="form-group"><label>别名</label><input type="text" name="slug" class="form-control"></div>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn btn-primary">创建</button>
            <button type="button" class="btn btn-secondary" data-toggle-form="tag-form">取消</button>
        </div>
    </form>
</div>

<div class="table-wrap" id="tagsPage">
    <!-- 批量操作表单 -->
    <form method="post" class="batch-form" style="display:none" action="<?= route('admin.tags.batch') ?>">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="batch_ids" value="">
        <input type="hidden" name="batch_action" value="">
    </form>
    <div class="batch-bar">
        <span>已选中 <span class="batch-count">0</span> 项</span>
        <div class="batch-actions">
            <button type="button" class="btn btn-sm btn-danger" data-batch-action="delete" data-confirm="确定删除选中的标签？">批量删除</button>
        </div>
    </div>

    <!-- 筛选区域 -->
    <div class="admin-filter-bar">
        <div class="admin-filter-row-top">
            <div class="search-box">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="tagSearchInput" placeholder="搜索标签名称、别名..." class="form-control" autocomplete="off">
            </div>
        </div>
    </div>

    <!-- 标签表格 -->
    <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th class="th-checkbox"><input type="checkbox" class="check-all"></th>
                <th class="sortable" data-sort="name">名称<span class="sort-arrow"></span></th>
                <th class="sortable" data-sort="slug">别名<span class="sort-arrow"></span></th>
                <th class="sortable" data-sort="post_count">文章数<span class="sort-arrow"></span></th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody id="tagsTableBody">
                <tr><td colspan="5" class="empty-cell">加载中...</td></tr>
            </tbody>
        </table>
    </div>

    <!-- 分页 -->
    <nav class="pagination" id="tagsPagination"></nav>

    <!-- 统计信息 -->
    <div class="admin-summary" id="tagsSummary"></div>
</div>

<script>
window.AdminSearchConfig = {
    searchUrl: '<?= route('admin.tags.search') ?>',
    csrfToken: '<?= csrf_token() ?>',
    routes: {
        update: function(id) { return '<?= route('admin.tags.update', ['id' => '__ID__']) ?>'.replace('__ID__', id); },
        delete: function(id) { return '<?= route('admin.tags.delete', ['id' => '__ID__']) ?>'.replace('__ID__', id); },
        view: function(slug) { return '<?= route('tag.show', ['slug' => '__SLUG__']) ?>'.replace('__SLUG__', encodeURIComponent(slug)); }
    }
};
</script>
<script src="<?= asset('admin/admin-search.js') ?>?v=<?= time() ?>"></script>
<script>
(function () {
    var cfg = window.AdminSearchConfig;
    var esc = window.AdminSearch.esc;

    window.AdminSearch.init({
        searchUrl: cfg.searchUrl,
        pageWrap: '#tagsPage',
        tbodyId: 'tagsTableBody',
        paginationId: 'tagsPagination',
        summaryId: 'tagsSummary',
        summaryUnit: '个标签',
        searchInputId: 'tagSearchInput',
        itemsKey: 'items',
        colspan: 5,
        emptyText: '暂无标签',
        batchEmptyAlert: '请先选择标签',
        stateDefaults: { q: '', sort: 'name', order: 'desc', page: 1 },
        countedFilters: [],
        filterFields: [],
        renderRow: function (item, state, config) {
            var id = item.id;
            var editFormId = 'tag-edit-form-' + id;
            var postCount = item.post_count || 0;

            // 数据行
            var html = '<tr>' +
                '<td class="td-checkbox"><input type="checkbox" class="check-item" value="' + esc(id) + '"></td>' +
                '<td>' + esc(item.name) + '</td>' +
                '<td>' + esc(item.slug) + '</td>' +
                '<td>' + postCount + '</td>' +
                '<td>' +
                    '<div class="btn-row">' +
                        '<a href="' + esc(cfg.routes.view(item.slug)) + '" target="_blank" class="btn btn-sm btn-secondary">查看</a>' +
                        '<button type="button" class="btn btn-sm btn-primary" data-toggle-form="' + editFormId + '">编辑</button>' +
                        '<form method="post" action="' + esc(cfg.routes.delete(id)) + '" data-confirm="确定删除该标签？">' +
                            '<input type="hidden" name="_token" value="' + esc(cfg.csrfToken) + '">' +
                            '<button type="submit" class="btn btn-sm btn-danger">删除</button>' +
                        '</form>' +
                    '</div>' +
                '</td>' +
            '</tr>';

            // 行内编辑表单
            html += '<tr class="form-toggle" id="' + editFormId + '">' +
                '<td colspan="5" style="padding:0">' +
                    '<div class="card" style="margin:8px;box-shadow:none">' +
                        '<form method="post" action="' + esc(cfg.routes.update(id)) + '">' +
                            '<input type="hidden" name="_token" value="' + esc(cfg.csrfToken) + '">' +
                            '<div class="form-row">' +
                                '<div class="form-group"><label>名称</label><input type="text" name="name" class="form-control" value="' + esc(item.name) + '" required></div>' +
                                '<div class="form-group"><label>别名</label><input type="text" name="slug" class="form-control" value="' + esc(item.slug) + '"></div>' +
                            '</div>' +
                            '<div class="btn-row">' +
                                '<button type="submit" class="btn btn-primary">保存</button>' +
                                '<button type="button" class="btn btn-secondary" data-toggle-form="' + editFormId + '">取消</button>' +
                            '</div>' +
                        '</form>' +
                    '</div>' +
                '</td>' +
            '</tr>';

            return html;
        }
    });
})();
</script>

<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');
