<?php
// Copyright 2008-2015 Las Venturas Playground. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

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
		throw new \Exception ('Cannot create a new instance of class "' . get_called_class () . '".');
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
		throw new \Exception ('Cannot unserialize the class "' . get_called_class () . '".');
	}
};
