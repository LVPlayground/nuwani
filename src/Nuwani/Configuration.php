<?php
// Copyright 2008-2015 Las Venturas Playground. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Nuwani;

class Configuration extends Singleton {
    /**
     * The configuration will be stored in this array, after we pull it from
     * the global context, as it is defined in config.php ($aConfiguration).
     * @var array
     */
    private $m_aConfiguration;

    /**
     * This function will register the configuration array with this class,
     * making it available for all bot systems to use as they like.
     *
     * @param array $aConfiguration Configuration you wish to register.
     */
    public function register($aConfiguration) {
        $this->m_aConfiguration = $aConfiguration;
    }

    /**
     * This function will return an array with the configuration options
     * associated with the key as specified in the parameter.
     *
     * @param string $sKey Key of the configuration item you wish to retrieve.
     *
     * @return array
     */
    public function get($sKey) {
        if(isset($this->m_aConfiguration [$sKey])) {
            return $this->m_aConfiguration [$sKey];
        }

        return [];
    }
}
