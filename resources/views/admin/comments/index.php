<?php
ob_start();
?>
<div class="page-header">
    <h2>评论管理</h2>
</div>

<div class="table-wrap" id="commentsPage">
    <!-- 批量操作表单 -->
    <form method="post" class="batch-form" style="display:none" action="<?= route('admin.comments.batch') ?>">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="batch_ids" value="">
        <input type="hidden" name="batch_action" value="">
    </form>
    <div class="batch-bar">
        <span>已选中 <span class="batch-count">0</span> 项</span>
        <div class="batch-actions">
            <button type="button" class="btn btn-sm btn-success" data-batch-action="approve" data-confirm="确定批量批准？">批量批准</button>
            <button type="button" class="btn btn-sm btn-warning" data-batch-action="spam" data-confirm="确定批量标垃圾？">标垃圾</button>
            <button type="button" class="btn btn-sm btn-danger" data-batch-action="delete" data-confirm="确定批量删除？">批量删除</button>
        </div>
    </div>

    <!-- 筛选区域 -->
    <div class="admin-filter-bar">
        <!-- 第一行：搜索框 -->
        <div class="admin-filter-row-top">
            <div class="search-box">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="commentSearchInput" placeholder="搜索评论内容、作者、邮箱..." class="form-control" autocomplete="off">
            </div>
        </div>

        <!-- 第二行：状态标签（始终显示，横向滚动） -->
        <div class="filter-tabs admin-filter-tabs">
            <a href="javascript:;" class="filter-tab is-default active" data-status="">全部</a>
            <a href="javascript:;" class="filter-tab" data-status="pending">待审</a>
            <a href="javascript:;" class="filter-tab" data-status="approved">已批准</a>
            <a href="javascript:;" class="filter-tab" data-status="spam">垃圾</a>
        </div>
    </div>

    <!-- 评论表格 -->
    <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th class="th-checkbox"><input type="checkbox" class="check-all"></th>
                <th>评论</th>
                <th>作者</th>
                <th>文章</th>
                <th class="sortable" data-sort="status">状态<span class="sort-arrow"></span></th>
                <th class="sortable" data-sort="created_at">时间<span class="sort-arrow"></span></th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody id="commentsTableBody">
                <tr><td colspan="7" class="empty-cell">加载中...</td></tr>
            </tbody>
        </table>
    </div>

    <!-- 分页 -->
    <nav class="pagination" id="commentsPagination"></nav>

    <!-- 统计信息 -->
    <div class="admin-summary" id="commentsSummary"></div>
</div>

<script>
window.AdminSearchConfig = {
    searchUrl: '<?= route('admin.comments.search') ?>',
    csrfToken: '<?= csrf_token() ?>',
    routes: {
        approve: function(id) { return '<?= route('admin.comments.approve', ['id' => '__ID__']) ?>'.replace('__ID__', id); },
        spam: function(id) { return '<?= route('admin.comments.spam', ['id' => '__ID__']) ?>'.replace('__ID__', id); },
        delete: function(id) { return '<?= route('admin.comments.delete', ['id' => '__ID__']) ?>'.replace('__ID__', id); }
    }
};
</script>
<script src="<?= asset('admin/admin-search.js') ?>?v=<?= time() ?>"></script>
<script>
(function () {
    var cfg = window.AdminSearchConfig;
    var esc = window.AdminSearch.esc;

    var statusLabels = { pending: '待审', approved: '已批准', spam: '垃圾' };

    window.AdminSearch.init({
        searchUrl: cfg.searchUrl,
        pageWrap: '#commentsPage',
        tbodyId: 'commentsTableBody',
        paginationId: 'commentsPagination',
        summaryId: 'commentsSummary',
        summaryUnit: '条评论',
        searchInputId: 'commentSearchInput',
        itemsKey: 'items',
        colspan: 7,
        emptyText: '暂无评论',
        batchEmptyAlert: '请先选择评论',
        stateDefaults: { q: '', status: '', sort: 'created_at', order: 'desc', page: 1 },
        countedFilters: [],
        filterFields: [],
        onTabSelect: function (tab, state) {
            state.status = tab.getAttribute('data-status') || '';
        },
        renderRow: function (item, state, config) {
            var id = item.id;
            var content = item.content || '';
            var authorName = item.author_name || '匿名';
            var authorIp = item.author_ip || '';
            var statusLabel = statusLabels[item.status] || item.status;

            var postCell = '';
            if (item.post_title && item.post_slug) {
                postCell = '<a href="/p/' + esc(item.post_slug) + '" target="_blank">' + esc(item.post_title) + '</a>';
            }

            var actions = '<div class="btn-row">';
            if (item.status !== 'approved') {
                actions += '<form method="post" action="' + esc(cfg.routes.approve(id)) + '" class="form-inline">' +
                    '<input type="hidden" name="_token" value="' + esc(cfg.csrfToken) + '">' +
                    '<button type="submit" class="btn btn-sm btn-success">' +
                        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>' +
                        '批准' +
                    '</button>' +
                '</form>';
            }
            if (item.status !== 'spam') {
                actions += '<form method="post" action="' + esc(cfg.routes.spam(id)) + '" class="form-inline">' +
                    '<input type="hidden" name="_token" value="' + esc(cfg.csrfToken) + '">' +
                    '<button type="submit" class="btn btn-sm btn-warning">标垃圾</button>' +
                '</form>';
            }
            actions += '<form method="post" action="' + esc(cfg.routes.delete(id)) + '" data-confirm="确定删除该评论？" class="form-inline">' +
                '<input type="hidden" name="_token" value="' + esc(cfg.csrfToken) + '">' +
                '<button type="submit" class="btn btn-sm btn-danger">删除</button>' +
            '</form>';
            actions += '</div>';

            return '<tr>' +
                '<td class="td-checkbox"><input type="checkbox" class="check-item" value="' + esc(id) + '"></td>' +
                '<td>' + esc(content.length > 60 ? content.substring(0, 60) : content) + '</td>' +
                '<td>' + esc(authorName) + (authorIp ? '<br><span class="text-muted" style="font-size:12px">' + esc(authorIp) + '</span>' : '') + '</td>' +
                '<td>' + postCell + '</td>' +
                '<td><span class="badge badge-' + esc(item.status) + '">' + esc(statusLabel) + '</span></td>' +
                '<td>' + esc(item.created_at) + '</td>' +
                '<td>' + actions + '</td>' +
            '</tr>';
        }
    });
})();
</script>

<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');
