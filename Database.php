<?php

/*
 * This file is part of the Geek-Zoo Projects.
 *
 * @copyright (c) 2010 Geek-Zoo Projects More info http://www.geek-zoo.com
 * @license http://opensource.org/licenses/gpl-2.0.php The GNU General Public License
 * @author xuanyan <xuanyan@geek-zoo.com>
 *
 */

class Database
{
    public static $sql = array();
    private static $connections = array();
    public static $instance = null;
    public static $debug = false;
    private $driver = null;

    const NUM = 0;
    const ASSOC = 1;
    const BOTH = 2;
    
    const VERSION = '20120323';

    function __get($name)
    {
        return $this->driver->$name;
    }

    function __set($name, $value)
    {
        $this->driver->$name = $value;
    }

    function __call($fun, $params = array())
    {
        return call_user_func_array(array($this->driver, $fun), $params);
    }

    function __construct()
    {
        $params = func_get_args();

        if (count($params) == 1) {
            $params = $params[0];
        }

        list(, $sp) = self::getParamHash($params);

        $this->driver = self::getDriver($params, $sp);
    }

    private static function getDriver($params, $sp)
    {
        if (is_array($params)) {
            $driver = array_shift($params);
        } elseif (strpos($params, '://')) {  // dsn
            if (!$dsn = parse_url($params)) {
                throw new DatabaseException("cant detect the dsn: {$params}");
            }
            if (!isset($dsn['scheme'])) {
                throw new DatabaseException("cant detect the driver: {$params}");
            }
            $driver = $dsn['scheme'];
            $params = array();
            
            $params[0] = isset($dsn['host']) ? $dsn['host'] : '';
            $params[1] = isset($dsn['user']) ? $dsn['user'] : '';
            $params[2] = isset($dsn['pass']) ? $dsn['pass'] : '';
            $params[3] = isset($dsn['path']) ? ltrim($dsn['path'], '/') : '';

            if ($driver == 'mysql') {
                isset($dsn['port']) && $params[0] .= ":{$dsn['port']}";
            } elseif ($driver == 'mysqli') {
                isset($dsn['port']) && $params[4] = $dsn['port'];
            } else {
                throw new DatabaseException("not support dsn driver: {$driver}");
            }

        } elseif (preg_match('/type \((\w+)|object\((\w+)\)/', $sp, $driver)) {
            $driver = strtolower(array_pop($driver));
            if ($driver == 'sqlitedatabase') {
                $driver = 'sqlite';
            }
        } else {
            throw new DatabaseException("cant auto detect the database driver");
        }

        require_once dirname(__FILE__).'/Driver/'.$driver.'.php';
        $class = $driver.'Wrapper';

        return new $class($params);
    }

    private static function getParamHash($params)
    {
        // mabe the param is object, so use var_dump
        ob_start();
        var_dump($params);
        $sp = ob_get_clean();
        $key = sha1($sp);
        // $key = md5(serialize($params));

        return array($key, $sp);
    }

    public static function connect()
    {
        $params = func_get_args();

        if (count($params) == 1) {
            $params = $params[0];
        }

        list($key, $sp) = self::getParamHash($params);

        if (!isset(self::$connections[$key])) {
            self::$connections[$key] = self::getDriver($params, $sp);
        }

        return self::$connections[$key];
    }
}

abstract class DatabaseAbstract
{
    protected $initParams = array();
    protected $link = null;

    protected $config = array(
        'tablePreFix' => null,
        'replaceTableName' => true,
        'initialization' => array()
    );

    // get config
    public function __get($key)
    {
        return $this->getConfig($key);
    }
    // set config
    public function __set($key, $value)
    {
        return $this->setConfig($key, $value);
    }

    public function setConfig($key, $value)
    {
        $this->config[$key] = $value;

        return true;
    }

    public function getConfig($key)
    {
        return isset($this->config[$key]) ? $this->config[$key] : false;
    }

    public function getTable($table_name)
    {
        // for preg_replace_callback
        if (is_array($table_name) && isset($table_name[1])) {
            $table_name = $table_name[1];
        }

        $tablePreFix = $this->getConfig('tablePreFix');

        if (!$tablePreFix) {
            return $table_name;
        }

        if (is_string($tablePreFix)) {
            return $tablePreFix.$table_name;
        }

        foreach ($tablePreFix as $key => $val) {
            if ($val == '*') {
                return $key.$table_name;
            }
            if (in_array($table_name, $val)) {
                return $key.$table_name;
            }
        }

        return $table_name;
    }

    function __construct($initParams)
    {
        $this->initParams = $initParams;
        if (!is_array($this->initParams)) {
            $this->link = $this->initParams;
        }
    }

    public function getCol()
    {
        $param = func_get_args();
        $query = call_user_func_array(array($this, 'query'), $param);

        $rs = array();
        while ($rt = $this->fetch($query, Database::NUM)) {
            $rs[] = $rt[0];
        }

        return $rs;
    }

    public function getOne()
    {
        $param = func_get_args();
        $query = call_user_func_array(array($this, 'query'), $param);
        $rs = $this->fetch($query, Database::NUM);

        return $rs[0];
    }

    public function getAll()
    {
        $param = func_get_args();
        $query = call_user_func_array(array($this,'query'), $param);

        $rs = array();
        while ($rt = $this->fetch($query, Database::ASSOC)) {
            $rs[] = $rt;
        }

        return $rs;
    }

    public function getRow()
    {
        $param = func_get_args();
        $query = call_user_func_array(array($this, 'query'), $param);
        $rs = $this->fetch($query, Database::ASSOC);

        return $rs === false ? array() : $rs;
    }

    public function getDriver()
    {
        return $this->initialization();
    }
}

interface DatabaseWrapper
{
    public function getRow();
    public function getCol();
    public function getOne();
    public function getAll();
    public function exec();
    public function lastInsertId();
    public function getDriver();
    public function query();
    public function fetch($query);
}

class DatabaseException extends Exception
{
    
}

?>