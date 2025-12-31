<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 设置时区为北京时间
if (date_default_timezone_get() !== 'Asia/Shanghai') {
    date_default_timezone_set('Asia/Shanghai');
}

/**
 * TypechoLoginData - Typecho用户登录记录插件
 * 
 * @package TypechoLoginData
 * @author tianlingzi
 * @version 1.0
 * @link https://www.tianlingzi.top/archives/237/
 */
class TypechoLoginData_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 数据文件路径
     */
    private static $dataFile;
    
    /**
     * 初始化数据文件路径
     */
    private static function initDataFile()
    {
        if (!isset(self::$dataFile)) {
            self::$dataFile = __DIR__ . '/data/login_records.json';
        }
    }
    
    /**
     * 激活插件方法
     * 
     * @return string 激活信息
     */
    public static function activate()
    {
        // 初始化数据文件
        self::initDataFile();
        
        // 注册登录钩子 - 常规登录
        Typecho_Plugin::factory('Widget_User')->loginSucceed = array('TypechoLoginData_Plugin', 'logLogin');
        
        // 注册登录钩子 - 插件登录（如社交登录）
        Typecho_Plugin::factory('Widget_User')->simpleLoginSucceed = array('TypechoLoginData_Plugin', 'logSimpleLogin');
        
        // 注册用户数据更新钩子，用于捕获第三方登录
        Typecho_Plugin::factory('Widget_User')->update = array('TypechoLoginData_Plugin', 'logUserUpdate');
        
        // 注册文章浏览钩子，用于检测新登录（通用方法）
        Typecho_Plugin::factory('Widget_Archive')->header = array('TypechoLoginData_Plugin', 'checkLogin');
        
        // 注册插件菜单
        Helper::addPanel(1, 'TypechoLoginData/panel.php', _t('登录记录'), _t('登录记录'), 'administrator');
        
        return _t('TypechoLoginData插件已激活');
    }
    
    /**
     * 禁用插件方法
     * 
     * @return string 禁用信息
     */
    public static function deactivate()
    {
        // 移除插件菜单
        Helper::removePanel(1, 'TypechoLoginData/panel.php');
        
        return _t('TypechoLoginData插件已禁用');
    }
    
    /**
     * 获取插件配置面板
     * 
     * @param Typecho_Widget_Helper_Form $form 配置表单
     */
    public static function config(Typecho_Widget_Helper_Form $form) {}
    
    /**
     * 个人用户配置面板
     * 
     * @param Typecho_Widget_Helper_Form $form 配置表单
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}
    
    /**
     * 获取用户IP地址
     * 
     * @return string IP地址
     */
    private static function getIp()
    {
        $ip = '';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }
    
    /**
     * 记录常规登录信息
     * 
     * @param mixed ...$args 参数列表
     * @return mixed 用户信息
     */
    public static function logLogin(...$args)
    {
        // 获取用户对象
        $user = isset($args[0]) ? $args[0] : null;
        return self::saveLoginRecord($user, 'normal');
    }
    
    /**
     * 记录插件登录信息
     * 
     * @param mixed ...$args 参数列表
     * @return mixed 用户信息
     */
    public static function logSimpleLogin(...$args)
    {
        // 获取用户信息，根据钩子调用方式不同，用户信息可能在不同位置
        $user = isset($args[1]) ? $args[1] : (isset($args[0]) ? $args[0] : null);
        return self::saveLoginRecord($user, 'plugin');
    }
    
    /**
     * 检查登录会话是否已记录
     * 
     * @param int $uid 用户ID
     * @return bool 是否已记录
     */
    private static function isLoginRecorded($uid)
    {
        $sessionKey = 'login_recorded_' . $uid;
        if (isset($_SESSION[$sessionKey])) {
            return true;
        }
        return false;
    }
    
    /**
     * 标记登录会话已记录
     * 
     * @param int $uid 用户ID
     */
    private static function markLoginRecorded($uid)
    {
        $sessionKey = 'login_recorded_' . $uid;
        $_SESSION[$sessionKey] = time();
    }
    
    /**
     * 从oauth_user表获取最新登录类型
     * 
     * @param int $uid 用户ID
     * @return string 登录类型
     */
    private static function getLatestOauthType($uid)
    {
        try {
            $db = Typecho_Db::get();
            $now = time();
            $fiveMinutesAgo = $now - 300; // 5分钟内的登录记录
            
            // 查询oauth_user表，获取用户5分钟内的登录记录
            $latestLogin = $db->fetchRow($db->select()
                ->from('table.oauth_user')
                ->where('uid = ?', $uid)
                ->where('datetime >= ?', date('Y-m-d H:i:s', $fiveMinutesAgo))
                ->order('datetime', Typecho_Db::SORT_DESC)
                ->limit(1));
            
            if ($latestLogin && isset($latestLogin['type'])) {
                return 'oauth_' . $latestLogin['type'];
            }
            
            // 如果5分钟内没有记录，获取最新的记录
            $latestLogin = $db->fetchRow($db->select()
                ->from('table.oauth_user')
                ->where('uid = ?', $uid)
                ->order('datetime', Typecho_Db::SORT_DESC)
                ->limit(1));
            
            if ($latestLogin && isset($latestLogin['type'])) {
                return 'oauth_' . $latestLogin['type'];
            }
        } catch (Exception $e) {
            // 忽略数据库查询错误
        }
        return 'oauth';
    }
    
    /**
     * 保存登录记录到JSON文件
     * 
     * @param mixed $user 用户信息
     * @param string $method 登录方式
     * @return mixed 用户信息
     */
    private static function saveLoginRecord($user, $method = 'normal')
    {
        try {
            self::initDataFile();
            
            $ip = self::getIp();
            $uid = null;
            $username = '';
            $nickname = '';
            
            // 确保用户信息可用
            if (is_array($user) && isset($user['uid'])) {
                // 从数组获取用户信息
                $uid = $user['uid'];
                $username = isset($user['name']) ? $user['name'] : '';
                $nickname = isset($user['screenName']) ? $user['screenName'] : '';
            } elseif (is_object($user)) {
                // 从对象获取用户信息
                $uid = isset($user->uid) ? $user->uid : null;
                $username = isset($user->name) ? $user->name : '';
                $nickname = isset($user->screenName) ? $user->screenName : '';
            } 
            
            // 如果仍无法获取用户信息，尝试从Widget获取
            if (empty($uid)) {
                try {
                    $widgetUser = Typecho_Widget::widget('Widget_User');
                    $uid = $widgetUser->uid;
                    $username = $widgetUser->name;
                    $nickname = $widgetUser->screenName;
                } catch (Exception $e) {
                    // 无法从Widget获取用户信息，跳过记录
                    return $user;
                }
            }
            
            // 只有当uid有效时才插入记录
            if (!empty($uid)) {
                // 检查是否已记录该登录会话
                if (self::isLoginRecorded($uid)) {
                    return $user;
                }
                
                // 检查是否为插件登录，获取具体插件名称
                $loginMethod = $method;
                
                // 检查session中的OAuth认证信息（TypechoOAuthLogin插件）
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }
                
                // 从多种来源获取登录类型，优先级递减
                if ($method === 'plugin' || $method === 'auto') {
                    // 1. 检查请求参数
                    if (isset($_GET['type'])) {
                        $loginMethod = 'oauth_' . $_GET['type'];
                    } elseif (isset($_GET['plugin'])) {
                        $loginMethod = $_GET['plugin'];
                    } elseif (isset($_POST['plugin'])) {
                        $loginMethod = $_POST['plugin'];
                    } 
                    // 2. 检查session（如果还有的话）
                    elseif (isset($_SESSION['__typecho_auth']) && is_array($_SESSION['__typecho_auth']) && isset($_SESSION['__typecho_auth']['type'])) {
                        $loginMethod = 'oauth_' . $_SESSION['__typecho_auth']['type'];
                    } 
                    // 3. 查询oauth_user表，获取最新登录类型（最可靠的方法）
                    else {
                        $loginMethod = self::getLatestOauthType($uid);
                    }
                }
                
                // 确保用户名和昵称有默认值
                $username = $username ?: '';
                $nickname = $nickname ?: '';
                
                // 创建登录记录
                $loginRecord = array(
                    'uid' => $uid,
                    'username' => $username,
                    'nickname' => $nickname,
                    'login_time' => time(),
                    'login_method' => $loginMethod,
                    'ip' => $ip
                );
                
                // 读取现有记录
                $records = array();
                if (file_exists(self::$dataFile)) {
                    $jsonContent = file_get_contents(self::$dataFile);
                    if ($jsonContent) {
                        $records = json_decode($jsonContent, true);
                        if (!is_array($records)) {
                            $records = array();
                        }
                    }
                }
                
                // 将新记录添加到数组开头
                array_unshift($records, $loginRecord);
                
                // 写入JSON文件
                file_put_contents(self::$dataFile, json_encode($records, JSON_UNESCAPED_UNICODE));
                
                // 标记该登录会话已记录
                self::markLoginRecorded($uid);
            }
        } catch (Exception $e) {
            // 文件操作失败，记录错误但不影响登录过程
            error_log('TypechoLoginData Plugin Error: ' . $e->getMessage());
        }
        
        return $user;
    }
    
    /**
     * 记录用户更新事件（可能由第三方登录触发）
     * 
     * @param array $rows 更新的数据
     * @param Typecho_Widget_Helper_Form $form 表单对象
     * @return array 更新的数据
     */
    public static function logUserUpdate($rows, $form)
    {
        // 尝试获取用户信息
        try {
            $user = Typecho_Widget::widget('Widget_User');
            if ($user->hasLogin()) {
                self::saveLoginRecord($user, 'plugin');
            }
        } catch (Exception $e) {
            // 忽略错误
        }
        return $rows;
    }
    
    /**
     * 检查用户登录状态（通用方法）
     * 
     * @param string $header HTML头部
     * @return string HTML头部
     */
    public static function checkLogin($header)
    {
        // 启动会话
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // 尝试获取用户信息
        try {
            $user = Typecho_Widget::widget('Widget_User');
            if ($user->hasLogin()) {
                $uid = $user->uid;
                if (!$user->pass('guest', true) && !self::isLoginRecorded($uid)) {
                    // 用户已登录且未记录过登录
                    self::saveLoginRecord($user, 'auto');
                }
            }
        } catch (Exception $e) {
            // 忽略错误
        }
        return $header;
    }
}