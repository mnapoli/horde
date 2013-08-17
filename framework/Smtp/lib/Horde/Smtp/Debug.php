<?php
/**
 * Copyright 2013 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2013 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Smtp
 */

/**
 * Handles debugging output for a Horde_Smtp object.
 *
 * NOTE: This class is NOT intended to be accessed outside of the Smtp package.
 * There is NO guarantees that the API of this class will not change across
 * versions.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013 Horde LLC
 * @internal
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Smtp
 */
class Horde_Smtp_Debug
{
    /* Time, in seconds, to be labeled a slow command. */
    const SLOW_CMD = 5;

    /**
     * The debug stream.
     *
     * @var resource
     */
    protected $_stream;

    /**
     * Timestamp of last command.
     *
     * @var integer
     */
    protected $_time = null;

    /**
     * Constructor.
     *
     * @param mixed $debug  The debug target.
     */
    public function __construct($debug)
    {
        $this->_stream = is_resource($debug)
            ? $debug
            : @fopen($debug, 'a');
        register_shutdown_function(array($this, 'shutdown'));
    }

    /**
     * Shutdown function.
     */
    public function shutdown()
    {
        if (is_resource($this->_stream)) {
            fflush($this->_stream);
            fclose($this->_stream);
            $this->_stream = null;
        }
    }

    /**
     * Write client output to debug log.
     *
     * @param string $msg   Debug message.
     * @param boolean $eol  Output EOL?
     */
    public function client($msg, $eol = true)
    {
        $this->_write($msg . ($eol ? "\n" : ''), 'C: ');
    }

    /**
     * Write informational message to debug log.
     *
     * @param string $msg  Debug message.
     */
    public function info($msg)
    {
        $this->_write($msg . "\n", '>> ');
    }

    /**
     * Write server output to debug log.
     *
     * @param string $msg  Debug message.
     */
    public function raw($msg)
    {
        $this->_write($msg);
    }

    /**
     * Write server output to debug log.
     *
     * @param string $msg  Debug message.
     */
    public function server($msg)
    {
        $this->_write($msg . "\n", 'S: ');
    }

    /**
     * Write debug information to the output stream.
     *
     * @param string $msg  Debug data.
     * @param string $pre  Prefix.
     */
    protected function _write($msg, $pre = null)
    {
        if (!$this->_stream) {
            return;
        }

        if (!is_null($pre)) {
            $new_time = microtime(true);

            if (is_null($this->_time)) {
                fwrite(
                    $this->_stream,
                    str_repeat('-', 30) . "\n" . '>> ' . date('r') . "\n"
                );
            } elseif (($diff = ($new_time - $this->_time)) > self::SLOW_CMD) {
                fwrite(
                    $this->_stream,
                    '>> Slow Command: ' . round($diff, 3) . " seconds\n"
                );
            }

            $this->_time = $new_time;
        }

        fwrite($this->_stream, $pre . $msg);
    }

}