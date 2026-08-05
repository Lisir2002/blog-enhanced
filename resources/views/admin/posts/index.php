<?php
/** @var array $categories $authors $trash */
$trash = $trash ?? false;
ob_start();
?>
<div class="page-header">
    <h2><?= $trash ? '文章回收站' : '文章管理' ?></h2>
    <div class="page-header-actions">
        <a href="<?= route('admin.posts.create') ?>" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            写文章
        </a>
    </div>
</div>

<div class="table-wrap" id="postsPage">
    <!-- 批量操作表单 -->
    <form method="post" class="batch-form" style="display:none" action="<?= route('admin.posts.batch') ?>">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="batch_ids" value="">
        <input type="hidden" name="batch_action" value="">
        <input type="hidden" name="trash" value="<?= $trash ? '1' : '' ?>">
    </form>
    <div class="batch-bar">
        <span>已选中 <span class="batch-count">0</span> 项</span>
        <div class="batch-actions">
            <?php if ($trash): ?>
                <button type="button" class="btn btn-sm btn-success" data-batch-action="restore" data-confirm="确定恢复选中的文章？">批量恢复</button>
                <button type="button" class="btn btn-sm btn-danger" data-batch-action="force_delete" data-confirm="确定永久删除选中的文章？此操作不可恢复！">永久删除</button>
            <?php else: ?>
                <button type="button" class="btn btn-sm btn-danger" data-batch-action="delete" data-confirm="确定将选中的文章移入回收站？">批量删除</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- 筛选区域 -->
    <div class="admin-filter-bar">
        <!-- 第一行：搜索框 + 筛选切换按钮 -->
        <div class="admin-filter-row-top">
            <div class="search-box">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="postSearchInput" placeholder="搜索标题、内容、摘要..." class="form-control" autocomplete="off">
            </div>
            <button type="button" id="btnToggleFilters" class="btn btn-sm btn-secondary admin-filter-toggle" aria-expanded="false">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                筛选
                <span class="admin-filter-count" id="filterCountBadge" style="display:none">0</span>
            </button>
        </div>

        <!-- 第二行：状态标签（始终显示，横向滚动） -->
        <div class="filter-tabs admin-filter-tabs">
            <a href="javascript:;" class="filter-tab <?= $trash ? '' : 'is-default active' ?>" data-status="" data-trash="0">全部</a>
            <a href="javascript:;" class="filter-tab" data-status="published" data-trash="0">已发布</a>
            <a href="javascript:;" class="filter-tab" data-status="draft" data-trash="0">草稿</a>
            <a href="javascript:;" class="filter-tab <?= $trash ? 'is-default active' : '' ?>" data-status="" data-trash="1">回收站</a>
        </div>

        <!-- 第三行：高级筛选（可折叠） -->
        <div class="admin-filters" id="postsAdvancedFilters" style="display:none">
            <div class="admin-filter-item">
                <label class="admin-filter-label">分类</label>
                <select id="filterCategory" class="form-control form-control-sm">
                    <option value="0">全部分类</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat->getAttribute('id')) ?>"><?= e($cat->getAttribute('name')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-filter-item">
                <label class="admin-filter-label">作者</label>
                <select id="filterAuthor" class="form-control form-control-sm">
                    <option value="0">全部作者</option>
                    <?php foreach ($authors as $author): ?>
                        <option value="<?= e($author['id']) ?>"><?= e($author['display_name'] ?: $author['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-filter-item">
                <label class="admin-filter-label">开始日期</label>
                <input type="date" id="filterDateFrom" class="form-control form-control-sm">
            </div>
            <div class="admin-filter-item">
                <label class="admin-filter-label">结束日期</label>
                <input type="date" id="filterDateTo" class="form-control form-control-sm">
            </div>
            <button type="button" id="btnResetFilters" class="btn btn-sm btn-secondary admin-filter-reset">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                重置
            </button>
        </div>
    </div>

    <!-- 文章表格 -->
    <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th class="th-checkbox"><input type="checkbox" class="check-all"></th>
                <th class="sortable" data-sort="title">标题<span class="sort-arrow"></span></th>
                <th class="sortable" data-sort="author">作者<span class="sort-arrow"></span></th>
                <th>分类</th>
                <th class="sortable" data-sort="status">状态<span class="sort-arrow"></span></th>
                <th class="sortable" data-sort="created_at">发布时间<span class="sort-arrow"></span></th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody id="postsTableBody">
                <tr><td colspan="7" class="empty-cell">加载中...</td></tr>
            </tbody>
        </table>
    </div>

    <!-- 分页 -->
    <nav class="pagination" id="postsPagination"></nav>

    <!-- 统计信息 -->
    <div class="admin-summary" id="postsSummary"></div>
</div>

<script>
window.AdminSearchConfig = {
    searchUrl: '<?= route('admin.posts.search') ?>',
    csrfToken: '<?= csrf_token() ?>',
    trash: <?= $trash ? 'true' : 'false' ?>,
    routes: {
        edit: function(id) { return '<?= route('admin.posts.edit', ['id' => '__ID__']) ?>'.replace('__ID__', id); },
        delete: function(id) { return '<?= route('admin.posts.delete', ['id' => '__ID__']) ?>'.replace('__ID__', id); },
        restore: function(id) { return '<?= route('admin.posts.restore', ['id' => '__ID__']) ?>'.replace('__ID__', id); },
        forceDelete: function(id) { return '<?= route('admin.posts.forceDelete', ['id' => '__ID__']) ?>'.replace('__ID__', id); },
        view: function(slug) { return '/p/' + slug; }
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
        pageWrap: '#postsPage',
        tbodyId: 'postsTableBody',
        paginationId: 'postsPagination',
        summaryId: 'postsSummary',
        summaryUnit: '篇文章',
        searchInputId: 'postSearchInput',
        toggleBtnId: 'btnToggleFilters',
        advancedFiltersId: 'postsAdvancedFilters',
        filterCountBadgeId: 'filterCountBadge',
        resetBtnId: 'btnResetFilters',
        itemsKey: 'posts',
        colspan: 7,
        emptyText: cfg.trash ? '回收站为空' : '暂无文章',
        batchEmptyAlert: '请先选择文章',
        stateDefaults: {
            q: '',
            status: '',
            trash: cfg.trash ? 1 : 0,
            category_id: 0,
            author_id: 0,
            date_from: '',
            date_to: '',
            sort: 'created_at',
            order: 'desc',
            page: 1
        },
        countedFilters: ['category_id', 'author_id', 'date_from', 'date_to'],
        filterFields: [
            { id: 'filterCategory', stateKey: 'category_id', type: 'int' },
            { id: 'filterAuthor',   stateKey: 'author_id',   type: 'int' },
            { id: 'filterDateFrom', stateKey: 'date_from',   type: 'text' },
            { id: 'filterDateTo',   stateKey: 'date_to',     type: 'text' }
        ],
        onTabSelect: function (tab, state) {
            state.status = tab.getAttribute('data-status') || '';
            state.trash = parseInt(tab.getAttribute('data-trash')) || 0;
        },
        onReset: function (state) {
            state.trash = cfg.trash ? 1 : 0;
        },
        renderRow: function (item, state, config) {
            var id = item.id;
            var isTrash = state.trash === 1;
            var statusBadge = isTrash
                ? '<span class="badge badge-red">已删除</span>'
                : (item.status === 'published'
                    ? '<span class="badge badge-published">已发布</span>'
                    : '<span class="badge badge-draft">草稿</span>');

            var actions = '';
            if (isTrash) {
                actions = '<div class="btn-row">' +
                    '<form method="post" action="' + esc(cfg.routes.restore(id)) + '" class="form-inline">' +
                        '<input type="hidden" name="_token" value="' + esc(cfg.csrfToken) + '">' +
                        '<button type="submit" class="btn btn-sm btn-success">恢复</button>' +
                    '</form>' +
                    '<form method="post" action="' + esc(cfg.routes.forceDelete(id)) + '" data-confirm="确定永久删除该文章？此操作不可恢复！">' +
                        '<input type="hidden" name="_token" value="' + esc(cfg.csrfToken) + '">' +
                        '<button type="submit" class="btn btn-sm btn-danger">永久删除</button>' +
                    '</form>' +
                '</div>';
            } else {
                actions = '<div class="btn-row">' +
                    '<a href="' + esc(cfg.routes.view(item.slug)) + '" target="_blank" class="btn btn-sm btn-secondary">查看</a>' +
                    '<a href="' + esc(cfg.routes.edit(id)) + '" class="btn btn-sm btn-primary">编辑</a>' +
                    '<form method="post" action="' + esc(cfg.routes.delete(id)) + '" data-confirm="确定将文章移入回收站？">' +
                        '<input type="hidden" name="_token" value="' + esc(cfg.csrfToken) + '">' +
                        '<button type="submit" class="btn btn-sm btn-danger">删除</button>' +
                    '</form>' +
                '</div>';
            }

            return '<tr>' +
                '<td class="td-checkbox"><input type="checkbox" class="check-item" value="' + esc(id) + '"></td>' +
                '<td><a href="' + esc(cfg.routes.edit(id)) + '">' + esc(item.title) + '</a></td>' +
                '<td>' + esc(item.author_name || item.author_username || '-') + '</td>' +
                '<td>' + esc(item.category_name || '-') + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td>' + esc(item.published_at || item.created_at) + '</td>' +
                '<td>' + actions + '</td>' +
            '</tr>';
        }
    });
})();
</script>

<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');
