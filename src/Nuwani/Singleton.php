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

abstract class Singleton
{
	/**
	 * This property will contain the active instances of the singleton'ned
	 * classes, making sure there only is one of each active at any time.
	 * 
	 * @var array
	 */

	private static $m_aClassInstances;

	/**
	 * This function is the constructor of the class which inherits us. This
	 * is done to force the private-visibility of the class' constructor
	 * function.
	 */
	
	private function __construct ()
	{
	}

	/**
	 * This function will get a new instance of the class which inherits us,
	 * or return the active one if it already existed.
	 * 
	 * @param Object $pInstance Allows one to specify a specific instance for that class.
	 * @return Object
	 */
	
	public static final function getInstance ($pInstance = null)
	{
		$sCallee = get_called_class ();
		if (!isset (self :: $m_aClassInstances [$sCallee]))
		{
			if ($pInstance != null)
				self :: $m_aClassInstances [$sCallee] = $pInstance;
			else
				self :: $m_aClassInstances [$sCallee] = new $sCallee ();
		}
		
		return self :: $m_aClassInstances [$sCallee] ;
	}

	/**
	 * Cloning a singleton'ned class is not allowed, seeing it would create
	 * another instance, which evidently is not allowed.
	 * 
	 * @throws Exception When this method is invoked.
	 */
	
	public final function __clone ()
	{
		throw new Exception ('Cannot create a new instance of class "' . get_called_class () . '".');
	}
	
	/**
	 * This function normally allows deserialisation of classes which
	 * already existed (e.g. unserialize(serialize($class));), and thus
	 * creating another copy. We don't allow this either.
	 * 
	 * @throws Exception When this method is invoked.
	 */
	
	public final function __wakeup ()
	{
		throw new Exception ('Cannot unserialize the class "' . get_called_class () . '".');
	}
};

?>