<?php
/** @var array $roles */
$roles = $roles ?? \Core\Auth\Capability::roles();
ob_start();
?>
<div class="page-header">
    <h2>用户管理</h2>
    <div class="page-header-actions">
        <a href="<?= route('admin.users.create') ?>" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            添加用户
        </a>
    </div>
</div>

<div class="table-wrap" id="usersPage">
    <!-- 批量操作表单 -->
    <form method="post" class="batch-form" style="display:none" action="<?= route('admin.users.batch') ?>">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="batch_ids" value="">
        <input type="hidden" name="batch_action" value="">
    </form>
    <div class="batch-bar">
        <span>已选中 <span class="batch-count">0</span> 项</span>
        <div class="batch-actions">
            <button type="button" class="btn btn-sm btn-danger" data-batch-action="delete" data-confirm="确定删除选中的用户？">批量删除</button>
        </div>
    </div>

    <!-- 筛选区域 -->
    <div class="admin-filter-bar">
        <!-- 第一行：搜索框 + 筛选切换按钮 -->
        <div class="admin-filter-row-top">
            <div class="search-box">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="userSearchInput" placeholder="搜索用户名、昵称、邮箱..." class="form-control" autocomplete="off">
            </div>
            <button type="button" id="btnToggleFilters" class="btn btn-sm btn-secondary admin-filter-toggle" aria-expanded="false">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                筛选
                <span class="admin-filter-count" id="filterCountBadge" style="display:none">0</span>
            </button>
        </div>

        <!-- 高级筛选（可折叠） -->
        <div class="admin-filters" id="usersAdvancedFilters" style="display:none">
            <div class="admin-filter-item">
                <label class="admin-filter-label">角色</label>
                <select id="filterRole" class="form-control form-control-sm">
                    <option value="">全部角色</option>
                    <?php foreach ($roles as $rk => $rv): ?>
                        <option value="<?= e($rk) ?>"><?= e($rv) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-filter-item">
                <label class="admin-filter-label">状态</label>
                <select id="filterStatus" class="form-control form-control-sm">
                    <option value="">全部状态</option>
                    <option value="active">正常</option>
                    <option value="inactive">未激活</option>
                    <option value="banned">已封禁</option>
                </select>
            </div>
            <button type="button" id="btnResetFilters" class="btn btn-sm btn-secondary admin-filter-reset">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                重置
            </button>
        </div>
    </div>

    <!-- 用户表格 -->
    <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th class="th-checkbox"><input type="checkbox" class="check-all"></th>
                <th class="sortable" data-sort="display_name">用户<span class="sort-arrow"></span></th>
                <th class="sortable" data-sort="email">邮箱<span class="sort-arrow"></span></th>
                <th class="sortable" data-sort="role">角色<span class="sort-arrow"></span></th>
                <th class="sortable" data-sort="status">状态<span class="sort-arrow"></span></th>
                <th class="sortable" data-sort="created_at">注册时间<span class="sort-arrow"></span></th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody id="usersTableBody">
                <tr><td colspan="7" class="empty-cell">加载中...</td></tr>
            </tbody>
        </table>
    </div>

    <!-- 分页 -->
    <nav class="pagination" id="usersPagination"></nav>

    <!-- 统计信息 -->
    <div class="admin-summary" id="usersSummary"></div>
</div>

<script>
window.AdminSearchConfig = {
    searchUrl: '<?= route('admin.users.search') ?>',
    csrfToken: '<?= csrf_token() ?>',
    currentUserId: <?= (int) current_user()->getAttribute('id') ?>,
    roles: <?= json_encode($roles) ?>,
    roleBadgeClass: {
        super_admin: 'badge-blue',
        senior_admin: 'badge-blue',
        editor_admin: 'badge-gray',
        editor_writer: 'badge-gray',
        visitor: 'badge-gray'
    },
    routes: {
        edit: function(id) { return '<?= route('admin.users.edit', ['id' => '__ID__']) ?>'.replace('__ID__', id); },
        delete: function(id) { return '<?= route('admin.users.delete', ['id' => '__ID__']) ?>'.replace('__ID__', id); }
    }
};
</script>
<script src="<?= asset('admin/admin-search.js') ?>?v=<?= time() ?>"></script>
<script>
(function () {
    var cfg = window.AdminSearchConfig;
    var esc = window.AdminSearch.esc;
    // 头像 URL（按角色映射，与 User::avatarUrl 保持一致）
    var roleAvatarKey = {
        super_admin: 'super_admin',
        senior_admin: 'senior_admin',
        editor_admin: 'editor_admin',
        editor_writer: 'editor_writer',
        visitor: 'visitor'
    };

    window.AdminSearch.init({
        searchUrl: cfg.searchUrl,
        pageWrap: '#usersPage',
        tbodyId: 'usersTableBody',
        paginationId: 'usersPagination',
        summaryId: 'usersSummary',
        summaryUnit: '位用户',
        searchInputId: 'userSearchInput',
        toggleBtnId: 'btnToggleFilters',
        advancedFiltersId: 'usersAdvancedFilters',
        filterCountBadgeId: 'filterCountBadge',
        resetBtnId: 'btnResetFilters',
        itemsKey: 'items',
        colspan: 7,
        emptyText: '暂无用户',
        batchEmptyAlert: '请先选择用户',
        stateDefaults: { q: '', role: '', status: '', sort: 'created_at', order: 'desc', page: 1 },
        countedFilters: ['role', 'status'],
        filterFields: [
            { id: 'filterRole', stateKey: 'role', type: 'text' },
            { id: 'filterStatus', stateKey: 'status', type: 'text' }
        ],
        renderRow: function (item, state, config) {
            var id = item.id;
            var displayName = item.display_name || item.username;
            var avatarKey = roleAvatarKey[item.role] || 'visitor';
            var avatarUrl = '<?= asset('avatars/__AVATAR__.jpg') ?>'.replace('__AVATAR__', avatarKey);
            var roleLabel = cfg.roles[item.role] || item.role;
            var roleBadge = cfg.roleBadgeClass[item.role] || 'badge-gray';
            var statusBadge = item.status === 'active' ? 'badge-green' : 'badge-red';
            var statusLabel = item.status === 'active' ? '正常' : (item.status === 'banned' ? '已封禁' : item.status);

            var actions = '<div class="btn-row">' +
                '<a href="' + esc(cfg.routes.edit(id)) + '" class="btn btn-sm btn-secondary">编辑</a>';
            if (id !== cfg.currentUserId) {
                actions += '<form method="post" action="' + esc(cfg.routes.delete(id)) + '" data-confirm="确定删除该用户？">' +
                    '<input type="hidden" name="_token" value="' + esc(cfg.csrfToken) + '">' +
                    '<button type="submit" class="btn btn-sm btn-danger">删除</button>' +
                '</form>';
            }
            actions += '</div>';

            return '<tr>' +
                '<td class="td-checkbox"><input type="checkbox" class="check-item" value="' + esc(id) + '"' + (id === cfg.currentUserId ? ' disabled' : '') + '></td>' +
                '<td>' +
                    '<div class="user-cell">' +
                        '<img src="' + esc(avatarUrl) + '" alt="" loading="lazy">' +
                        '<div class="user-info">' +
                            '<a href="' + esc(cfg.routes.edit(id)) + '" class="user-name">' + esc(displayName) + '</a>' +
                            '<span class="user-username">@' + esc(item.username) + '</span>' +
                        '</div>' +
                    '</div>' +
                '</td>' +
                '<td>' + esc(item.email) + '</td>' +
                '<td><span class="badge ' + roleBadge + '">' + esc(roleLabel) + '</span></td>' +
                '<td><span class="badge ' + statusBadge + '">' + esc(statusLabel) + '</span></td>' +
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
