<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 设置时区为北京时间
if (date_default_timezone_get() !== 'Asia/Shanghai') {
    date_default_timezone_set('Asia/Shanghai');
}

// 检查是否为管理员
$user = Typecho_Widget::widget('Widget_User');
if (!$user->pass('administrator', true)) {
    die(_t('权限不足'));
}

// 获取当前时间信息
$today = date('Ynj');
$yesterday = date('Ynj', strtotime('-1 day'));
$currentYear = date('Y');
$currentMonth = date('n');

// 获取筛选条件
$ymd = isset($_GET['ymd']) ? $_GET['ymd'] : '';
$year = isset($_GET['year']) ? $_GET['year'] : $currentYear;
$month = isset($_GET['month']) ? $_GET['month'] : $currentMonth;

// 数据文件路径
$dataFile = __DIR__ . '/data/login_records.json';

// 读取所有登录记录
$allRecords = array();
if (file_exists($dataFile)) {
    $jsonContent = file_get_contents($dataFile);
    if ($jsonContent) {
        $allRecords = json_decode($jsonContent, true);
        if (!is_array($allRecords)) {
            $allRecords = array();
        }
    }
}

// 筛选记录
$loginRecords = array();
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

foreach ($allRecords as $record) {
    $recordTime = $record['login_time'];
    $recordDate = date('Ynj', $recordTime);
    $recordYear = date('Y', $recordTime);
    $recordMonth = date('n', $recordTime);
    $recordDateStr = date('Y-m-d', $recordTime);
    
    $match = true;
    
    // 优先处理日期范围筛选
    if (!empty($startDate) || !empty($endDate)) {
        if (!empty($startDate) && $recordDateStr < $startDate) {
            $match = false;
        }
        if (!empty($endDate) && $recordDateStr > $endDate) {
            $match = false;
        }
    } else {
        // 其他筛选条件
        if (!empty($ymd)) {
            // 按天筛选
            if ($recordDate !== $ymd) {
                $match = false;
            }
        } elseif (isset($_GET['year'])) {
            // 只有当URL中明确包含year参数时，才进行按年筛选
            if ($recordYear != $year) {
                $match = false;
            } elseif (isset($_GET['month'])) {
                // 只有当URL中明确包含month参数时，才按年月筛选
                if ($recordMonth != $month) {
                    $match = false;
                }
            }
        }
    }
    
    if ($match) {
        $loginRecords[] = $record;
    }
}

// 获取总记录数
$total = count($loginRecords);

// 获取统计数据
$uniqueUsers = count(array_unique(array_column($loginRecords, 'uid')));
$avgLogins = $uniqueUsers > 0 ? round($total / $uniqueUsers, 2) : 0;

// 登录方式统计
$methodStats = array();
foreach ($loginRecords as $record) {
    $method = $record['login_method'];
    if (!isset($methodStats[$method])) {
        $methodStats[$method] = 0;
    }
    $methodStats[$method]++;
}
// 转换为对象格式，保持与原有代码兼容
$methodStats = array_map(function($count, $method) {
    $obj = new stdClass();
    $obj->login_method = $method;
    $obj->count = $count;
    return $obj;
}, $methodStats, array_keys($methodStats));

// 分页设置
$pageSize = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $pageSize;

// 分页处理
$pagedRecords = array_slice($loginRecords, $offset, $pageSize);

// 替换登录记录变量
$loginRecords = $pagedRecords;

// 格式化登录方式
function formatLoginMethod($method)
{
    // 处理OAuth登录方式
    if (strpos($method, 'oauth_') === 0) {
        $oauthType = strtolower(substr($method, 6));
        switch ($oauthType) {
            case 'qq':
                return _t('QQ登录');
            case 'weixin':
            case 'wechat':
                return _t('微信登录');
            case 'github':
                return _t('GitHub登录');
            case 'msn':
                return _t('微软登录');
            case 'google':
                return _t('Google登录');
            case 'weibo':
                return _t('新浪微博登录');
            case 'douban':
                return _t('豆瓣登录');
            case 'taobao':
                return _t('淘宝登录');
            case 'baidu':
                return _t('百度登录');
            default:
                return _t('OAuth登录') . ' (' . ucfirst($oauthType) . ')';
        }
    }
    
    // 处理其他登录方式
    switch ($method) {
        case 'normal':
            return _t('常规登录');
        case 'plugin':
        case 'oauth':
            return _t('OAuth登录');
        case 'auto':
            return _t('自动登录');
        default:
            return ucfirst($method);
    }
}

// 获取options实例
$options = Typecho_Widget::widget('Widget_Options');

// 页面标题
?>
<div class="main">
    <div class="body container">
        <div class="col-group typecho-page-main" role="main">
            <div class="typecho-list">
                <div class="typecho-page-header">
                    <h2><?php _e('登录记录'); ?></h2>
                    <p><?php _e('共 %d 条记录，涉及 %d 位用户，平均每人登录 %.2f 次', $total, $uniqueUsers, $avgLogins); ?></p>
                </div>
                
                <!-- 统计信息 -->
                <div class="typecho-option">
                    <div class="typecho-option-header">
                        <h4><?php _e('登录统计'); ?></h4>
                    </div>
                    <div class="typecho-option-content">
                        <div class="stat">
                            <?php foreach ($methodStats as $stat): ?>
                                <div class="stat-item">
                                    <span class="stat-label"><?php echo formatLoginMethod($stat->login_method); ?>:</span>
                                    <span class="stat-value"><?php echo $stat->count; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- 时间筛选导航 -->
                <div class="typecho-option">
                    <div class="typecho-option-header">
                        <h4><?php _e('时间筛选'); ?></h4>
                    </div>
                    <div class="typecho-option-content">
                        <div class="archives-nav">
                            <div style="margin-bottom: 1rem;">
                                <a href="<?php echo $options->adminUrl('extending.php?panel=TypechoLoginData/panel.php'); ?>" <?php if (empty($ymd) && empty($year) && empty($_GET['start_date']) && empty($_GET['end_date'])) echo 'class="current"'; ?>><?php _e('全部记录'); ?></a>
                                <a href="<?php echo $options->adminUrl('extending.php?panel=TypechoLoginData/panel.php&ymd=' . $today); ?>" <?php if ($ymd === $today) echo 'class="current"'; ?>><?php _e('今天'); ?></a>
                                <a href="<?php echo $options->adminUrl('extending.php?panel=TypechoLoginData/panel.php&ymd=' . $yesterday); ?>" <?php if ($ymd === $yesterday) echo 'class="current"'; ?>><?php _e('昨天'); ?></a>
                            </div>
                            
                            <!-- 日期范围选择 -->
                            <div style="margin-bottom: 1.5rem;">
                                <h5><?php _e('日期范围选择'); ?></h5>
                                <form method="get" action="<?php echo $options->adminUrl('extending.php?panel=TypechoLoginData/panel.php'); ?>" style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center;">
                                    <input type="hidden" name="panel" value="TypechoLoginData/panel.php">
                                    <div>
                                        <label for="start_date" style="margin-right: 0.5rem; font-weight: 600; color: var(--text-secondary);"><?php _e('开始日期'); ?></label>
                                        <input type="date" name="start_date" id="start_date" value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : ''; ?>" style="padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); font-size: 0.9rem; background: var(--bg-primary); color: var(--text-primary);">
                                    </div>
                                    <div>
                                        <label for="end_date" style="margin-right: 0.5rem; font-weight: 600; color: var(--text-secondary);"><?php _e('结束日期'); ?></label>
                                        <input type="date" name="end_date" id="end_date" value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : ''; ?>" style="padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); font-size: 0.9rem; background: var(--bg-primary); color: var(--text-primary);">
                                    </div>
                                    <div>
                                        <button type="submit" style="padding: 0.5rem 1.5rem; background: var(--primary-color); color: white; border: none; border-radius: var(--border-radius-sm); font-weight: 600; cursor: pointer; transition: var(--transition-fast);">
                                            <?php _e('筛选'); ?>
                                        </button>
                                        <button type="button" onclick="clearDateRange()" style="margin-left: 0.5rem; padding: 0.5rem 1.5rem; background: var(--bg-tertiary); color: var(--text-secondary); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); font-weight: 600; cursor: pointer; transition: var(--transition-fast);">
                                            <?php _e('清除'); ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <script>
                            function clearDateRange() {
                                document.getElementById('start_date').value = '';
                                document.getElementById('end_date').value = '';
                                window.location.href = '<?php echo $options->adminUrl('extending.php?panel=TypechoLoginData/panel.php'); ?>';
                            }
                            </script>
                            
                            <?php if (count($allRecords) > 0): ?>
                                <!-- 提取有记录的年份 -->
                                <?php 
                                $recordYears = array();
                                foreach ($allRecords as $record) {
                                    $recordYear = date('Y', $record['login_time']);
                                    if (!in_array($recordYear, $recordYears)) {
                                        $recordYears[] = $recordYear;
                                    }
                                }
                                // 按年份降序排序
                                rsort($recordYears);
                                ?>
                                
                                <h5><?php _e('按年筛选'); ?></h5>
                                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                                    <?php foreach ($recordYears as $y): ?>
                                        <a href="<?php echo $options->adminUrl('extending.php?panel=TypechoLoginData/panel.php&year=' . $y); ?>" <?php if (isset($_GET['year']) && $year == $y && empty($ymd)) echo 'class="current"'; ?>>
                                            <?php echo $y; ?> <?php _e('年'); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                
                                <h5><?php _e('按月筛选'); ?></h5>
                                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                                    <?php 
                                    // 提取当前选中年份中有记录的月份
                                    $recordMonths = array();
                                    foreach ($allRecords as $record) {
                                        $recordYear = date('Y', $record['login_time']);
                                        if ($recordYear == $year) {
                                            $recordMonth = date('n', $record['login_time']);
                                            if (!in_array($recordMonth, $recordMonths)) {
                                                $recordMonths[] = $recordMonth;
                                            }
                                        }
                                    }
                                    // 按月份升序排序
                                    sort($recordMonths);
                                    
                                    // 显示有记录的月份
                                    foreach ($recordMonths as $m): ?>
                                        <a href="<?php echo $options->adminUrl('extending.php?panel=TypechoLoginData/panel.php&year=' . $year . '&month=' . $m); ?>" <?php if (isset($_GET['month']) && $month == $m && empty($ymd)) echo 'class="current"'; ?>>
                                            <?php echo $m; ?> <?php _e('月'); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- 登录记录列表 -->
                <div class="typecho-table-wrap">
                    <table class="typecho-list-table">
                        <thead>
                            <tr>
                                <th><?php _e('UID'); ?></th>
                                <th><?php _e('用户名'); ?></th>
                                <th><?php _e('昵称'); ?></th>
                                <th><?php _e('登录时间'); ?></th>
                                <th><?php _e('登录方式'); ?></th>
                                <th><?php _e('登录IP'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($loginRecords)): ?>
                                <tr>
                                    <td colspan="6" class="typecho-table-nodata"><?php _e('暂无登录记录'); ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($loginRecords as $record): ?>
                                    <tr>
                                        <td><?php echo $record['uid']; ?></td>
                                        <td><?php echo $record['username']; ?></td>
                                        <td><?php echo $record['nickname']; ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', $record['login_time']); ?></td>
                                        <td><?php echo formatLoginMethod($record['login_method']); ?></td>
                                        <td><?php echo $record['ip']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- 分页 -->
                <?php if ($total > $pageSize): ?>
                    <div class="typecho-pager">
                        <?php 
                        // 构建分页URL
                        $paginationUrl = $options->adminUrl('extending.php?panel=TypechoLoginData/panel.php');
                        if (!empty($ymd)) {
                            $paginationUrl .= '&ymd=' . $ymd;
                        } elseif (!empty($year)) {
                            $paginationUrl .= '&year=' . $year;
                            if (!empty($month)) {
                                $paginationUrl .= '&month=' . $month;
                            }
                        }
                        $paginationUrl .= '&page={page}';
                        
                        Typecho_Widget::widget('Widget_PageNavigator_Contents')
                            ->render(array(
                                'total' => $total,
                                'pageSize' => $pageSize,
                                'page' => $page,
                                'url' => $paginationUrl
                            )); 
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style type="text/css">
    /* 全局样式优化 */
    :root {
        --primary-color: #6366f1;
        --primary-hover: #4f46e5;
        --primary-light: #eef2ff;
        --secondary-color: #8b5cf6;
        --success-color: #10b981;
        --warning-color: #f59e0b;
        --danger-color: #ef4444;
        --bg-primary: #ffffff;
        --bg-secondary: #f8fafc;
        --bg-tertiary: #f1f5f9;
        --border-color: #e2e8f0;
        --text-primary: #0f172a;
        --text-secondary: #475569;
        --text-muted: #94a3b8;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        --border-radius: 12px;
        --border-radius-sm: 8px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        --transition-fast: all 0.15s ease;
    }
    
    /* 重置和基础样式 */
    * {
        box-sizing: border-box;
    }
    
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        font-size: 14px;
        line-height: 1.5;
        color: var(--text-primary);
        background-color: var(--bg-secondary);
    }
    
    /* 主容器布局 */
    .main {
        padding: 2rem 0;
    }
    
    .body.container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    /* 卡片样式优化 */
    .typecho-option {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        overflow: hidden;
    }
    
    .typecho-option:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-2px);
    }
    
    /* 标题样式优化 */
    .typecho-option-header h4 {
        font-size: 1.375rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 1.5rem 0;
        padding-bottom: 0.75rem;
        border-bottom: 3px solid var(--primary-color);
        display: inline-block;
        letter-spacing: -0.025em;
    }
    
    /* 统计卡片优化 */
    .stat {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1.5rem;
        margin: 1rem 0;
    }
    
    .stat-item {
        background: linear-gradient(135deg, var(--bg-primary), var(--bg-secondary));
        padding: 1.75rem;
        border-radius: var(--border-radius);
        text-align: center;
        border: 1px solid var(--border-color);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }
    
    .stat-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    }
    
    .stat-item::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: radial-gradient(circle at 50% 0%, rgba(99, 102, 241, 0.1), transparent 70%);
        opacity: 0;
        transition: var(--transition);
    }
    
    .stat-item:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-xl);
        border-color: var(--primary-color);
    }
    
    .stat-item:hover::after {
        opacity: 1;
    }
    
    .stat-label {
        display: block;
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-secondary);
        margin-bottom: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.075em;
        position: relative;
        z-index: 1;
    }
    
    .stat-value {
        display: block;
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--primary-color);
        line-height: 1;
        position: relative;
        z-index: 1;
        font-family: 'Inter', -apple-system, sans-serif;
        letter-spacing: -0.05em;
    }
    
    /* 时间筛选导航优化 */
    .archives-nav {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        margin: 1.5rem 0;
    }
    
    .archives-nav > div:first-child {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
    }
    
    .archives-nav > div:first-child a {
        font-weight: 600;
        padding: 0.75rem 1.5rem;
        border-radius: var(--border-radius);
        transition: var(--transition-fast);
        background: var(--bg-tertiary);
        color: var(--text-secondary);
        border: 2px solid transparent;
    }
    
    .archives-nav > div:first-child a:hover,
    .archives-nav > div:first-child a.current {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }
    
    .archives-nav h5 {
        margin: 0 0 1rem 0;
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--text-primary);
        text-transform: none;
        letter-spacing: -0.025em;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .archives-nav h5::before {
        content: '';
        width: 4px;
        height: 20px;
        background: var(--primary-color);
        border-radius: 2px;
    }
    
    /* 年月筛选容器 */
    .archives-nav > div:nth-child(n+2) {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
    }
    
    .archives-nav a {
        display: inline-block;
        padding: 0.75rem 1.25rem;
        background: var(--bg-tertiary);
        border-radius: var(--border-radius-sm);
        text-decoration: none;
        color: var(--text-secondary);
        transition: var(--transition-fast);
        border: 1px solid var(--border-color);
        font-size: 0.9rem;
        font-weight: 500;
        line-height: 1;
        white-space: nowrap;
    }
    
    .archives-nav a:hover,
    .archives-nav a.current {
        background: var(--primary-light);
        color: var(--primary-color);
        border-color: var(--primary-color);
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
    }
    
    /* 表格样式优化 */
    .typecho-table-wrap {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
    }
    
    .typecho-table-wrap:hover {
        box-shadow: var(--shadow-md);
    }
    
    .typecho-list-table {
        border-collapse: collapse;
        width: 100%;
        font-size: 0.9rem;
        font-weight: 500;
    }
    
    .typecho-list-table th {
        background: linear-gradient(135deg, var(--primary-light), var(--bg-secondary));
        padding: 1.25rem;
        text-align: left;
        font-weight: 700;
        color: var(--text-primary);
        border-bottom: 2px solid var(--primary-color);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 0.8rem;
        white-space: nowrap;
    }
    
    .typecho-list-table td {
        padding: 1.25rem;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-secondary);
        transition: var(--transition-fast);
        vertical-align: middle;
    }
    
    .typecho-list-table tr:last-child td {
        border-bottom: none;
    }
    
    .typecho-list-table tr:hover td {
        background: var(--bg-secondary);
    }
    
    /* 登录方式标签样式 */
    .typecho-list-table td:nth-child(5) {
        font-weight: 600;
    }
    
    /* 登录IP样式 */
    .typecho-list-table td:nth-child(6) {
        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
        font-size: 0.85rem;
        color: var(--text-muted);
    }
    
    /* 无数据样式优化 */
    .typecho-table-nodata {
        text-align: center;
        color: var(--text-muted);
        font-style: italic;
        padding: 4rem !important;
        font-size: 1.125rem;
    }
    
    /* 分页样式优化 */
    .typecho-pager {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.5rem;
        margin-top: 2.5rem;
        padding: 1rem;
        background: var(--bg-secondary);
        border-radius: var(--border-radius);
    }
    
    .typecho-pager a,
    .typecho-pager .current {
        padding: 0.75rem 1.25rem;
        border: 2px solid var(--border-color);
        border-radius: var(--border-radius-sm);
        text-decoration: none;
        color: var(--text-secondary);
        transition: var(--transition-fast);
        font-size: 0.9rem;
        font-weight: 600;
        min-width: 40px;
        text-align: center;
        background: var(--bg-primary);
    }
    
    .typecho-pager a:hover {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }
    
    .typecho-pager .current {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
        font-weight: 700;
        box-shadow: var(--shadow-md);
    }
    
    /* 数据列表容器优化 */
    .col-group.typecho-page-main {
        display: grid;
        grid-template-columns: 1fr;
        gap: 2rem;
        align-items: start;
    }
    
    /* 列表区域优化 */
    .typecho-list {
        background: var(--bg-primary);
        border-radius: var(--border-radius);
        padding: 2rem;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
    }
    
    .typecho-list:hover {
        box-shadow: var(--shadow-md);
    }
    
    /* 响应式设计优化 */
    @media (max-width: 1024px) {
        .col-group.typecho-page-main {
            grid-template-columns: 1fr;
        }
        
        .col-mb-12.col-tb-4 {
            order: -1;
        }
    }
    
    @media (max-width: 768px) {
        .stat {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .stat-value {
            font-size: 2rem;
        }
        
        .typecho-list-table {
            font-size: 0.85rem;
        }
        
        .typecho-list-table th,
        .typecho-list-table td {
            padding: 1rem 0.75rem;
        }
        
        .typecho-page-header {
            padding: 2rem 1.5rem;
        }
        
        .typecho-page-header h2 {
            font-size: 2rem;
        }
        
        .typecho-list {
            padding: 1.5rem;
        }
        
        .typecho-option {
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .archives-nav > div:first-child {
            flex-direction: column;
        }
        
        .archives-nav > div:first-child a {
            text-align: center;
        }
    }
    
    /* 加载动画优化 */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .typecho-option,
    .typecho-table-wrap,
    .typecho-list {
        animation: fadeIn 0.6s ease-out;
    }
    
    /* 交错动画效果 */
    .typecho-option:nth-child(1) { animation-delay: 0.1s; }
    .typecho-option:nth-child(2) { animation-delay: 0.2s; }
    .typecho-table-wrap { animation-delay: 0.3s; }
    
    /* 滚动条优化 */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    
    ::-webkit-scrollbar-track {
        background: var(--bg-tertiary);
        border-radius: 4px;
    }
    
    ::-webkit-scrollbar-thumb {
        background: var(--border-color);
        border-radius: 4px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
        background: var(--text-muted);
    }
</style>