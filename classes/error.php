<?php
class Error extends \Fuel\Core\Error
{
	public static function shutdown_handler()
	{
		$last_error = error_get_last();

		if ($last_error and in_array($last_error['type'], static::$fatal_levels))
		{
			global $argv;
			$id = end($argv);
			\Cache::delete('background.'.$id);
			\Log::warning('The background '.$id.' is dead with fatal error.');
			\Background::_end($id, true);
		}

		parent::shutdown_handler();
	}
}