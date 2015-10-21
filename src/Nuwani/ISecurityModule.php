<?php
// Copyright 2008-2015 Las Venturas Playground. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

interface ISecurityModule
{
    /**
     * This function will register a new security provider with this very
     * module, as soon as it gets available. Will be called automatically.
     * 
     * @param ISecurityProvider The security provider to register with this module.
     * @param integer $nLevel Security level this provider will register.
     */
    
    public function registerSecurityProvider (ISecurityProvider $pProvider, $nLevel);
    
    /**
     * Will be called when a security provider unloads, so we will be aware
     * that we won't be able to use it anymore. Could be for any reason.
     * 
     * @param ISecurityProvider $pProvider Security Provider that is being unloaded.
     */
    
    public function unregisterSecurityProvider (ISecurityProvider $pProvider);
}
