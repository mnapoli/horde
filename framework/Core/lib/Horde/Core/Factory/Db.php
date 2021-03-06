<?php
/**
 * A Horde_Injector:: based factory for creating Horde_Db_Adapter objects.
 *
 * PHP version 5
 *
 * @category Horde
 * @package  Core
 * @author   Michael Slusarz <slusarz@horde.org>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @link     http://pear.horde.org/index.php?package=Core
 */

/**
 * A Horde_Injector:: based factory for creating Horde_Db_Adapter objects.
 *
 * Copyright 2010-2014 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Michael Slusarz <slusarz@horde.org>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @link     http://pear.horde.org/index.php?package=Core
 */
class Horde_Core_Factory_Db extends Horde_Core_Factory_Base
{
    /**
     * Instances.
     *
     * @var array
     */
    private $_instances = array();

    /**
     * Returns the DB instance.
     *
     * @param string $app            The application.
     * @param string|array $backend  The backend, see Horde::getDriverConfig().
     *                               If this is an array, this is used as the
     *                               configuration array.
     *
     * @return Horde_Db_Adapter  The singleton instance.
     * @throws Horde_Exception
     * @throws Horde_Db_Exception
     */
    public function create($app = 'horde', $backend = null)
    {
        global $registry;

        $pushed = ($app == 'horde')
            ? false
            : $registry->pushApp($app);

        $config = is_array($backend)
            ? $backend
            : $this->getConfig($backend);

        /* Prevent DSN from getting polluted (this only applies to non-custom
         * auth type connections. All other custom sql configurations MUST be
         * cleansed prior to passing to the factory (at least until Horde
         * 5). @todo Still needed? */
        if (!is_array($backend) && ($backend == 'auth')) {
            unset(
                $config['driverconfig'],
                $config['query_auth'],
                $config['query_add'],
                $config['query_getpw'],
                $config['query_update'],
                $config['query_resetpassword'],
                $config['query_remove'],
                $config['query_list'],
                $config['query_exists'],
                $config['encryption'],
                $config['show_encryption'],
                $config['username_field'],
                $config['password_field'],
                $config['table'],
                $config['login_block'],
                $config['login_block_count'],
                $config['login_block_time']
            );
        }
        unset($config['umask']);

        $e = null;

        ksort($config);
        $sig = hash(
            (PHP_MINOR_VERSION >= 4) ? 'fnv132' : 'sha1',
            serialize($config)
        );

        /* Determine if we are using the base SQL config. */
        if (isset($config['driverconfig']) &&
            ($config['driverconfig'] == 'horde')) {
            $this->_instances[$sig] = $this->create();
        } elseif (!isset($this->_instances[$sig])) {
            try {
                $this->_instances[$sig] = $this->createDb($config);
            } catch (Horde_Exception $e) {}
        }

        if ($pushed) {
            $registry->popApp();
        }

        if ($e) {
            throw $e;
        }

        return $this->_instances[$sig];
    }

    /**
     */
    public function getConfig($backend)
    {
        return Horde::getDriverConfig($backend, 'sql');
    }

    /**
     */
    public function createDb($config)
    {
        // Split read?
        if (!empty($config['splitread'])) {
            $read_config = $config['read'];
            unset($config['read'], $config['splitread']);
            return new Horde_Db_Adapter_SplitRead(
                $this->createDb(array_merge($config, $read_config)),
                $this->createDb($config)
            );
        }

        if (isset($config['adapter'])) {
            $class = $this->_getDriverName($config['adapter'], 'Horde_Db_Adapter');
            unset($config['adapter']);
        } elseif (empty($config['phptype'])) {
            throw new Horde_Exception('The database configuration is missing.');
        } else {
            switch ($config['phptype']) {
            case 'mysqli':
                $class = 'Horde_Db_Adapter_Mysqli';
                break;

            case 'mysql':
                $class = extension_loaded('pdo_mysql')
                    ? 'Horde_Db_Adapter_Pdo_Mysql'
                    : 'Horde_Db_Adapter_Mysql';
                break;

            case 'oci8':
                $class = 'Horde_Db_Adapter_Oci8';
                break;

            default:
                $class = 'Horde_Db_Adapter_Pdo_' . ucfirst($config['phptype']);
                break;
            }
        }

        if (!empty($config['hostspec'])) {
            $config['host'] = $config['hostspec'];
            unset($config['hostspec']);
        }

        $ob = new $class($config);

        if (!isset($config['cache'])) {
            $cacheFactory = $this->_injector->getInstance('Horde_Core_Factory_Cache');

            switch ($cacheFactory->getDriverName()) {
            case 'sql':
                $injector = $this->_injector->createChildInjector();
                $injector->setInstance('Horde_Db_Adapter', $ob);
                $cache = $cacheFactory->create($injector);
                break;

            default:
                $cache = $this->_injector->getInstance('Horde_Cache');
                break;
            }

            $ob->setCache($cache);
        }

        if (!isset($config['logger'])) {
            $ob->setLogger($this->_injector->getInstance('Horde_Log_Logger'));
        }

        return $ob;
    }
}
