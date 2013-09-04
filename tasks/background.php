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

namespace Fuel\Tasks;

class Background
{
	public static function run($id)
	{
		try
		{
			$background = \Cache::get('background.'.$id);
		}
		catch(\Exception $e)
		{
			\Log::warning('The background proccess "'.$id.'" is not found.');
		}

		$background->_run();
	}
}

/* End of file background.php */
