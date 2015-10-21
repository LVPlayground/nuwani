<?php
/**
 * Nuwani-v2 Bot Framework
 *
 * This file is part of the Nuwani v2 Bot Framework, a simple set of PHP classes
 * which allow you to set-up and run your own bot. It features advanced,
 * PHP 5.3 based syntax for optimal performance and security.
 *
 * @author Peter Beverloo <peter@lvp-media.com>
 */

namespace Nuwani;

class Configuration extends Singleton
{
	/**
	 * The configuration will be stored in this array, after we pull it from
	 * the global context, as it is defined in config.php ($aConfiguration).
	 * 
	 * @var array
	 */
	
	private $m_aConfiguration;
	
	/**
	 * This function will register the configuration array with this class,
	 * making it available for all bot systems to use as they like.
	 * 
	 * @param array $aConfiguration Configuration you wish to register.
	 */
	
	public function register ($aConfiguration)
	{
		$this -> m_aConfiguration = $aConfiguration ;
	}
	
	/**
	 * This function will return an array with the configuration options
	 * associated with the key as specified in the parameter.
	 * 
	 * @param string $sKey Key of the configuration item you wish to retrieve.
	 * @return array
	 */
	
	public function get ($sKey)
	{
		if (isset ($this -> m_aConfiguration [$sKey]))
			return $this -> m_aConfiguration [$sKey];

		return array ();
	}
};

?>