<?php
/**
 * Background
 *
 * Create the background proccess sliped out from application code. 
 *
 * @package    Background
 * @version    1.0
 * @author     Shintaro Ikezaki - @hackoh
 * @license    MIT License
 * @link       http://github.com/hackoh
 */

namespace Background;

class Background
{

	protected static $_lock = true;

	protected $_id;
	protected $_handler;
	protected $_arguments;
	protected $_created_at;
	protected $_completed_at;

	protected $_events = array(
		'before' => array(),
		'after' => array(),
		'success' => array(),
		'error' => array(),
		'exception' => array(),
	);

	public static function _init()
	{
		\Config::load('background', true);

		if (static::$_lock)
		{
			static::$_lock = @fopen(sys_get_temp_dir().'/fuel-background.lock', 'w');
		}
	}

	public static function forge($handler, $arguments = array())
	{
		return new static($handler, $arguments);
	}

	protected static function _to_callable($var)
	{
		if ($var instanceof \Closure)
		{
			$reflection = new \ReflectionFunction($var);
			$lines = file($reflection->getFileName());
			$lines = array_splice($lines, $reflection->getStartLine() - 1, ($reflection->getEndLine() - $reflection->getStartLine() + 1));
			$code = implode($lines);
			if (preg_match('/(function\s*?\(.*?\)\s.*?\{.*\})/s', $code, $matches))
			{
				$var = $matches[1];
			}
			else
			{
				throw new \InvalidArgumentException('Can\'t parse the closure specified.');
			}
		}
		elseif ( ! is_callable($var))
		{
			throw new \InvalidArgumentException('Can\'t parse the handler specified.');
		}
		return $var;
	}

	protected static function _queue()
	{
		try
		{
			$queue = \Cache::get('background.queue');
		}
		catch (\Exception $e)
		{
			$queue = array();
		}

		return (array) $queue;
	}

	protected static function _push($background)
	{
		$queue = static::_queue();
		$queue[$background->_id] = $background;
		\Cache::set('background.queue', $queue);
	}

	protected static function _lock($lock = true)
	{
		if (static::$_lock)
		{
			if ($lock)
			{
				flock(static::$_lock, LOCK_EX);
			}
			else
			{
				flock(static::$_lock, LOCK_UN);
			}
		}
	}

	public static function _end($id, $fork = false)
	{
		static::_lock();

		$queue = static::_queue();
		\Arr::delete($queue, $id);
		\Cache::set('background.queue', $queue);

		static::_lock(false);

		if ($queue)
		{
			$background = current($queue);

			if ($fork)
			{
				$background->run();
			}
			else
			{
				$background->_run();
			}
		}
	}

	public function __construct($handler, $arguments = array())
	{
		$id = \Str::random('unique');
		$this->_id = $id;
		$this->_handler = static::_to_callable($handler);
		$this->set_arguments($arguments);
	}

	public function queue()
	{
		static::_lock();

		$this->on('after', function($id) {
			Background::_end($id);
		}, array($this->_id));

		$queue = static::_queue();
		
		if ( ! count($queue))
		{
			$this->run();
		}

		static::_push($this);

		static::_lock(false);
	}

	public function run()
	{
		$this->_created_at = time();

		\Cache::set('background.'.$this->_id, $this);

		$php = \Config::get('background.php', 'php');
		$oil = \Config::get('background.oil_path_from_apppath', '../../oil');

		// Check OS
		if (PHP_OS !== 'WIN32' && PHP_OS !== 'WINNT')
		{
			// Unix-based OS
			$command = 'FUEL_ENV='.\Fuel::$env.' '.$php.' '.APPPATH.$oil.' r background '.$this->_id;
			exec($command.' > /dev/null 2>&1 &');
		}
		else
		{
			// Windows OS
			$command = 'set FUEL_ENV='.\Fuel::$env.'&'.$php.' '.APPPATH.$oil.' r background '.$this->_id;
			$fp = @popen('start /B cmd /c "'.$command.'"', 'r');
			@pclose($fp);
		}

		return $this;
	}

	public function on($event, $handler, $arguments = array())
	{

		if ( ! in_array($event, array_keys($this->_events)))
		{
			throw new \InvalidArgumentException('The undefined event "'.$event.'" specified.');
		}

		if ($handler instanceof \Background)
		{
			$this->_events[$event][] = $handler;
		}
		else
		{
			$this->_events[$event][] = static::forge($handler, $arguments);
		}

		return $this;
	}

	public function trigger($event, $arguments = array())
	{
		if ( ! in_array($event, array_keys($this->_events)))
		{
			throw new \InvalidArgumentException('The undefined event "'.$event.'" specified.');
		}

		foreach ($this->_events[$event] as $background)
		{
			if ($arguments)
			{
				$background->set_arguments($arguments);
			}
			$background->_run();
		}

		return $this;
	}

	public function set_arguments($arguments)
	{
		if (is_array($arguments))
		{
			$this->_arguments = $arguments;
		}
		else
		{
			$this->_arguments = array($arguments);
		}

		return $this;
	}

	public function id()
	{
		return $this->_id;
	}

	public function _run()
	{
		if (is_string($this->_handler) && strpos($this->_handler, 'function') === 0)
		{
			eval('$callable = '.$this->_handler.';');
		}
		else
		{
			$callable = $this->_handler;
		}

		$this->trigger('before');

		try
		{
			$result = call_user_func_array($callable, $this->_arguments);
		}
		catch (\Exception $e)
		{
			$result = $e;
		}

		\Cache::delete('background.'.$this->_id);

		$this->trigger('after');

		if ($result instanceof \Exception)
		{
			$this->trigger('exception', $result);
		}
		elseif ($result)
		{
			$this->trigger('success');
		}
		else
		{
			$this->trigger('error');
		}
	}
}

/* End of file background.php */
