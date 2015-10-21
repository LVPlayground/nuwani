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

class ModuleManager extends Singleton implements ArrayAccess, SeekableIterator, Countable
{
	/**
	 * This constant can be returned by callback functions in modules, which
	 * will indicate that the callback loop should be stopped immediatly.
	 * 
	 * @var integer
	 */
	
	const	FINISHED	= -1;

	/**
	 * An array of the modules which have been loaded into the Nuwani system,
	 * uses their names as the array index.
	 * 
	 * @var array
	 */
	
	private $m_aModules;
	
	/**
	 * The constructor will initialise the module-settings and auto-load all
	 * modules that have to be loaded, by checking out the module directory.
	 */
	
	protected function __construct ()
	{
		$this -> m_aModules = array ();
		$aFileList = glob ('Modules/*');
		
		foreach ($aFileList as $sFilename)
		{
			if (!is_dir ($sFilename))
				$sFilename = substr ($sFilename, 0, -4);
			
			$this -> loadModule (basename ($sFilename), false);
		}
		
		$this -> prioritize ();
	}
	
	/**
	 * This function will allow you to load a module into the manager, so
	 * all callbacks and things will be forwarded properly.
	 * 
	 * @param string $sName Name of the module you wish to load.
	 * @param boolean $bReorder Re-organize all modules based on priority.
	 * @return boolean
	 */
	
	public function loadModule ($sName, $bReorder = true)
	{
		if (isset ($this -> m_aModules [$sName]))
			$this -> unloadModule ($sName);
		
		$sPath = 'Modules/' . $sName;
		if (file_exists ($sPath) && is_dir ($sPath))
		{
			$sPath .= '/Module.php';
		}
		else
		{
			$sPath .= '.php';
		}
		
		if (file_exists ($sPath) && class_exists ($sName) && function_exists ('runkit_import'))
		{
			runkit_import ($sPath,  RUNKIT_IMPORT_OVERRIDE | RUNKIT_IMPORT_CLASSES);
		}
		else if (!class_exists ($sName) && file_exists ($sPath))
		{
			include_once $sPath;
		}
		else
		{
			return false;
		}
		
		if (!class_exists ($sName))
			return false ;
		
		try {
			$this -> m_aModules [$sName] = array
			(
				'Instance' => new $sName (),
				'Started' => time (),
				'Methods' => array ()
			);
		} catch (Exception $pException) {
			echo '[Modules] Exception occurred during instantiation of module "' . $sName . '": ';
			echo $pException -> getMessage () . PHP_EOL;
			
			return false ;
		}
		
		if (!($this -> m_aModules [$sName]['Instance'] instanceof ModuleBase))
		{
			throw new Exception ('The module "' . $sName . '" cannot be loaded: not a module.');
			unset ($this -> m_aModules [$sName]);
			return false ;
		}
		
		$pClassObj = new ReflectionClass ($sName);
		foreach ($pClassObj -> getMethods () as $pMethodObj)
			$this -> m_aModules [$sName]['Methods'][$pMethodObj -> getName ()] = $pMethodObj;
		
		unset ($pClassObj);
		
		if ($bReorder == true) // re-order the modules
			$this -> prioritize ();
		
		$this -> onModuleLoad ($this -> m_aModules [$sName]['Instance']);
		if (isset ($this -> m_aModules [$sName] ['Methods'] ['onModuleLoad']))
		{
			foreach ($this -> m_aModules as $sModuleName => $pModule)
			{
				if ($sModuleName == $sName)
					continue ;
					
				$this -> m_aModules [$sName] ['Instance'] -> onModuleLoad ($pModule ['Instance']);
			}
		}
		
		return true ;
	}
	
	/**
	 * The prioritize function will order all modules based on their priority.
	 * All calculations are done all-over again, so it can be somewhat costy
	 * on the performance. Use the second parameter of loadModule properly!
	 * 
	 * @return boolean
	 */
	
	private function prioritize ()
	{
		$aPriorityQueue = Configuration :: getInstance () -> get ('PriorityQueue');
		if (count ($aPriorityQueue) == 0)
			return false ;
		
		$aModuleList = array
		(
			'Prioritized' 	=> array (),
			'Normal'	=> array ()
		);
		
		/** Determain which modules are prioritized **/
		foreach ($this -> m_aModules as $sName => $pModule)
		{
			$nPriority = array_search ($sName, $aPriorityQueue, true);
			if ($nPriority !== false)
			{
				$aModuleList ['Prioritized'] [$nPriority] = $pModule ;
			}
			else
			{
				$aModuleList ['Normal'] [$sName] = $pModule ;
			}
		}
		
		/** Now sort both arrays, and merge them into one array **/
		ksort ($aModuleList ['Prioritized']);
		ksort ($aModuleList ['Normal']);
		
		$this -> m_aModules = array ();
		foreach ($aModuleList ['Prioritized'] as $pModule)
			$this -> m_aModules [get_class ($pModule ['Instance'])] = $pModule;
		
		foreach ($aModuleList ['Normal'] as $sName => $pModule)
			$this -> m_aModules [$sName] = $pModule;
		
		return true ;
	}
	
	/**
	 * This function destroys a class and throws it out of our internal 
	 * module-array, so it won't be used any longer.
	 * 
	 * @param string $sName Name of the module that should be unloaded.
	 * @return boolean
	 */
	
	public function unloadModule ($sName)
	{
		if (!isset ($this -> m_aModules [$sName]))
			return false ;
		
		// Callback for other modules (and this one)
		$this -> onModuleUnload ($this -> m_aModules [$sName]['Instance']);
		unset ($this -> m_aModules [$sName]);
		
		return true ;
	}
	
	/**
	 * This function easily reloads a module using the name of it. It calls
	 * the two internal functions load- and unloadModule.
	 * 
	 * @param string $sName Which module would you like to reload?
	 * @return boolean
	 */ 
	
	public function reloadModule ($sName)
	{
		return $this -> unloadModule ($sName) &&
		       $this -> loadModule ($sName);
	}

	/**
	 * A simply method returning the number of modules which currently have
	 * been loaded by this manager, counting our internal array.
	 * 
	 * @return integer
	 */
	
	public function count ()
	{
		return count ($this -> m_aModules);
	}
		
	/**
	 * This function calls a certain function in all of the modules, so
	 * it will automatically be forwarded. 
	 * 
	 * @param string $sFunction Name of the function being called.
	 * @param array $aParameters Parameters being passed along.
	 * @return boolean
	 */
	
	public function __call ($sFunction, $aParameters)
	{
		foreach ($this -> m_aModules as $sKey => $aModuleInfo)
		{
			if (isset ($aModuleInfo ['Methods'][$sFunction]))
			{
				$nReturnValue = 
				$aModuleInfo ['Methods'][$sFunction] -> invokeArgs 
				(
					$aModuleInfo ['Instance'], $aParameters
				);
				
				if ($nReturnValue === self :: FINISHED)
					return true ;
			}
		}
		
		return false ;
	}
	
	// -------------------------------------------------------------------//
	// Region: ArrayAccess                                                //
	// -------------------------------------------------------------------//
	
	/**
	 * The function that will be called when a certain module is requested
	 * from this handler. There is a check done for existance.
	 * 
	 * @param string $sKey Key of the entry that you want to receive.
	 * @return mixed
	 */
	
	public function offsetGet ($sKey)
	{
		if (isset ($this -> m_aModules [$sKey]))
			return $this -> m_aModules [$sKey]['Instance'];
		
		return false ;
	}
	
	/**
	 * Checks whether a key with this name exists, and if so, returns
	 * true, otherwise a somewhat more negative boolean gets returned.
	 * 
	 * @param string $sKey Key of the entry that you want to check.
	 * @return boolean
	 */
	
	public function offsetExists ($sKey)
	{
		return isset ($this -> m_aModules [$sKey]);
	}
	
	/**
	 * This function can be used to set associate a value with a certain key
	 * in our internal array, however, that's disabled seeing we're locking
	 * values.
	 * 
	 * @param string $sKey Key of the entry that you want to set.
	 * @param mixed $mValue Value to assign to the key.
	 * @return null
	 */
	
	public function offsetSet ($sKey, $mValue)
	{
		return ;
	}
	
	/**
	 * This function will get called as soon as unset() gets called on the 
	 * Modules instance, which is not properly supported either.
	 * 
	 * @param string $sKey Key of the entry that you want to unset.
	 * @return null
	 */
	
	public function offsetUnset ($sKey)
	{
		return ;
	}
	
	// -------------------------------------------------------------------//
	// Region: SeekableIterator                                           //
	// -------------------------------------------------------------------//
	
	/**
	 * This function returns the current active item in the module list,
	 * defined by a key and the associated priority.
	 * 
	 * @return ModuleBase
	 */
	
	public function current ()
	{
		return current ($this -> m_aModules);
	}
	
	/**
	 * Returns the key of the currently active item in the module info array,
	 * quite simple using the function with exactly the same name. This will
	 * return the name of the currently selected module.
	 * 
	 * @return string
	 */
	
	public function key ()
	{
		return key ($this -> m_aModules);
	}
	
	/**
	 * Advanced the module-manager's pointer to the next entry in the array,
	 * returning the value whatever that might be.
	 * 
	 * @return ModuleBase
	 */
	
	public function next ()
	{
		return next ($this -> m_aModules);
	}
	
	/**
	 * Rewinds the array to the absolute beginning, so iterating over it can
	 * start all over again.
	 * 
	 * @return ModuleBase
	 */
	
	public function rewind ()
	{
		return reset ($this -> m_aModules);
	}
	
	/**
	 * Determaines whether the current array index is a valid one, and not
	 * "beyond the array", which surely is possible with arrays.
	 * 
	 * @return boolean
	 */
	
	public function valid ()
	{
		return current ($this -> m_aModules) !== false;
	}
	
	/**
	 * The seek function "seeks" to a certain position within the module
	 * array, so we can skip the absolutely uninteresting parts.
	 * 
	 * @param mixed $mIndex Index that we wish to seek to.
	 * @throws OutOfBoundsException When the position cannot be seeked to.
	 */
	
	public function seek ($mIndex)
	{
		reset ($this -> m_aModules);
		$nPosition = 0;
		
		while ($nPosition < $mIndex && (current ($this -> m_aModules) !== false))
		{
			next ($this -> m_aModules);
			$nPosition ++;
		}
		
		if (current ($this -> m_aModules) === false)
			throw new OutOfBoundsException ('Cannot seek to position "' . $mIndex . '"');
	}

}

?>