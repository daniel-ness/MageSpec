<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category   MageTest
 * @package    PhpSpec_MagentoExtension
 * @copyright  Copyright (c) 2008 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace MageTest\PhpSpec\MagentoExtension\Autoloader;

/**
 * Classes source autoload
 */
class MageLoader
{
    const SCOPE_FILE_PREFIX = '__';

    static protected $_instance;
    static protected $_scope = 'default';

    protected $_isIncludePathDefined = false;
    protected $_collectClasses = false;
    protected $_collectPath = null;
    protected $_arrLoadedClasses = array();
    protected $_srcPath = '';
    protected $_codePool = '';

    /**
     * Class constructor
     * @param string $srcPath
     * @param string $codePool
     */
    public function __construct($srcPath, $codePool = 'local')
    {
        $this->_srcPath = $srcPath;
        $this->_codePool = $codePool;
        $this->_isIncludePathDefined = defined('COMPILER_INCLUDE_PATH');
        if ($this->_isIncludePathDefined) {
            $this->_collectClasses  = true;
            $this->_collectPath = COMPILER_COLLECT_PATH;
        }
        set_include_path(get_include_path() . PATH_SEPARATOR . $this->_srcPath . $this->_codePool);
        self::registerScope(self::$_scope);
    }

    /**
     * Singleton pattern implementation
     *
     * @param string $srcPath
     * @param string $codePool
     * @return MageLoader
     */
    public static function instance($srcPath, $codePool)
    {
        if (!self::$_instance) {
            self::$_instance = new MageLoader($srcPath, $codePool);
        }
        return self::$_instance;
    }

    /**
     * Register SPL autoload function
     * @param string $srcPath
     * @param string $codePool
     */
    public static function register($srcPath, $codePool)
    {
        spl_autoload_register(array(self::instance($srcPath, $codePool), 'autoload'));
    }

    /**
     * Load class source code
     *
     * @param string $class
     * @return bool|mixed
     */
    public function autoload($class)
    {
        if ($this->_collectClasses) {
            $this->_arrLoadedClasses[self::$_scope][] = $class;
        }

        if (substr($class, -10) === 'Controller') {
            return $this->includeController($class);
        }

        $classFile = $this->getClassFile($class) . '.php';

        if (!stream_resolve_include_path($classFile)) {
            return false;
        }

        return include $classFile;
    }

    /**
     * Register autoload scope
     * This process allow include scope file which can contain classes
     * definition which are used for this scope
     *
     * @param string $code scope code
     */
    public static function registerScope($code)
    {
        self::$_scope = $code;
        if (defined('COMPILER_INCLUDE_PATH')) {
            $file = COMPILER_INCLUDE_PATH . DIRECTORY_SEPARATOR . self::SCOPE_FILE_PREFIX . $code . '.php';
            if (file_exists($file)) {
                include $file;
            }
        }
    }

    /**
     * Get current autoload scope
     *
     * @return string
     */
    public static function getScope()
    {
        return self::$_scope;
    }

    /**
     * Class destructor
     */
    public function __destruct()
    {
        if ($this->_collectClasses) {
            $this->_saveCollectedState();
        }
    }

    /**
     * Save information about used classes per scope with class popularity
     * Class_Name:popularity
     *
     * @return MageLoader
     */
    protected function _saveCollectedState()
    {
        $this->prepareCollectPath();

        if (!is_writeable($this->_collectPath)) {
            return $this;
        }

        foreach ($this->_arrLoadedClasses as $scope => $classes) {
            $this->writeStateFile($scope, $classes);
        }

        return $this;
    }

    /**
     * Includes a controller given a controller class name
     *
     * @param string $class controller class name
     * @return bool|mixed
     */
    private function includeController($class)
    {
        $local = $this->_srcPath . DIRECTORY_SEPARATOR . $this->_codePool . DIRECTORY_SEPARATOR;
        $controller = explode('_', $class);
        array_splice($controller, 2, 0, 'controllers');
        $pathToController = implode(DIRECTORY_SEPARATOR, $controller);
        $classFile = $local . $pathToController . '.php';
        if (!file_exists($classFile)) {
            return false;
        }
        return include_once $classFile;
    }

    /**
     * @param string $class
     * @return string
     */
    private function getClassFile($class)
    {
        if ($this->_isIncludePathDefined) {
            return  COMPILER_INCLUDE_PATH . DIRECTORY_SEPARATOR . $class;
        }
        return str_replace(' ', DIRECTORY_SEPARATOR, ucwords(str_replace('_', ' ', $class)));
    }

    private function prepareCollectPath()
    {
        if (!is_dir($this->_collectPath)) {
            mkdir($this->_collectPath);
            chmod($this->_collectPath, 0777);
        }
    }

    /**
     * @param $scope
     * @param $classes
     */
    protected function writeStateFile($scope, $classes)
    {
        $file = $this->_collectPath . DIRECTORY_SEPARATOR . $scope . '.csv';

        if (!file_exists($file)) {
            return;
        }

        $data = explode("\n", file_get_contents($file));
        
        foreach ($data as $index => $class) {
            $class = explode(':', $class);
            $searchIndex = array_search($class[0], $classes);
            if ($searchIndex !== false) {
                $class[1] += 1;
                unset($classes[$searchIndex]);
            }
            $data[$index] = $class[0] . ':' . $class[1];
        }

        foreach ($classes as $class) {
            $data[] = $class . ':1';
        }

        file_put_contents($file, implode("\n", $data));
    }
}
