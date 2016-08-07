<?php
// Copyright 2008-2015 Las Venturas Playground. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Nuwani;

class Bot implements \ArrayAccess {
    /**
     * The socket property contains a class which handles all communication
     * with the IRC server.
     * @var Socket
     */
    private $m_pSocket;

    /**
     * The nickname is quite vital to the system's runtime, seeing that's
     * how we approach bots when they are needed (rather than by numeric
     * indexes).
     * @var string
     */
    private $m_sNickname;

    /**
     * An array containing various information about this bot; the network
     * we're connecting to, channels we're in and whether we're in slave
     * mode.
     * @var array
     */
    private $m_aBotInfo;

    /**
     * A standard class containing all the variables that are being send to
     * us from the socket, easy for module handling.
     * @var \stdClass
     */
    public $In;

    /**
     * This boolean indicates whether we already called onRawSend() on the
     * modules in the current loop. If this is the case, further calls to
     * Bot::send() will not call onRawSend() on the modules until this loop
     * ends.
     * @var boolean
     */
    private $m_bRawSendCalled = false;

    /**
     * The construct function will initialise the current bot by setting up
     * the required objects for runtime. No connections are initialised yet.
     *
     * @param string $sName Nickname of the bot to initialise.
     */
    public function __construct($sName) {
        $this->m_pSocket = new Socket ($this);
        $this->m_sNickname = $sName;
        $this->In = new \stdClass;

        $this->m_aBotInfo = [
            'Channels' => [],
            'Network' => [],
            'Slave' => false,
            'PingTimer' => Timer::Create([$this, 'onPing'], 59000, true)
        ];

        BotManager::getInstance()->register($this, $this->m_sNickname);
    }

    /**
     * The destructor will simply unregister this bot with the bot manager,
     * in case it's still registered. No additional tasks will be done here.
     */
    public function __destruct() {
        BotManager::getInstance()->unregister($this);
    }

    /**
     * The fake destructor of the class gets called when we destroy it, how
     * suprising. In here we close the socket and remove it from the bot
     * manager.
     */
    public function destroy() {
        if(!ModuleManager::getInstance()->onShutdown($this)) {
            $this->m_pSocket->send('QUIT :' . $this->m_sNickname);
        }

        Timer::Stop($this->m_aBotInfo ['PingTimer']);
    }

    /**
     * This function can be changed to immediatly change various settings
     * related to this bot. Replaces the former list of properties.
     *
     * @param array $aBotInfo Contains various information about this bot.
     */
    public function setBotInfo($aBotInfo) {
        foreach($aBotInfo as $sKey => $mValue) {
            if($sKey == 'Channels' || $sKey == 'Network' || $sKey == 'PingTimer') {
                continue;
            } // skip these keys;

            $this->m_aBotInfo [$sKey] = $mValue; // silent merge
        }
    }

    /**
     * This function will process all messages received directly from the
     * socket, in here we will handle various callbacks and functions.
     *
     * @param string $sMessage The raw message being received.
     *
     * @return boolean
     */
    public function onReceive($sMessage) {
        $this->In->Chunks = explode(' ', $sMessage);
        $this->In->Raw = $sMessage;
        $this->In->User = substr($this->In->Chunks [0], 1);
        $this->In->PostColon = (strpos($sMessage, ':', 2) !== false) ? substr($sMessage, strpos($sMessage, ':', 2) + 1) : '';

        if(strpos($this->In->User, '!') !== false) {
            list ($this->In->Nickname, $this->In->Username, $this->In->Hostname) =
                preg_split('/!|@/', $this->In->User, 3);
        } else {
            $this->In->Username = $this->In->Hostname = '';
            $this->In->Nickname = $this->In->User;
        }

        // Exception for "PING", which is not the second piece;
        if($this->In->Chunks [0] == 'PING') {
            return $this->m_pSocket->send('PONG :' . $this->In->Chunks [1]);
        }

        ErrorExceptionHandler::$Context = $this;

        $pModules = ModuleManager::getInstance();
        $bIsSlave = $this->m_aBotInfo ['Slave'];

        switch(strtolower($this->In->Chunks [1])) {
            case '001': // Initial welcome message, including our nickname.
                $this->m_sNickname = $this->In->Chunks [2];

                $this->m_pSocket->send('MODE ' . $this->m_sNickname . ' +B');
                $pModules->onConnect($this);
                break;

            case '005': // Information about what the server supports;
                $pNetworkManager = NetworkManager::getInstance();
                $sNetworkName = $this->m_aBotInfo ['Network']['Name'];

                $pNetworkManager->parseSupported($sNetworkName, array_slice($this->In->Chunks, 3));

                if($pNetworkManager->getSupportRule($sNetworkName, 'NAMESX') !== false) {
                    $this->m_pSocket->send('PROTOCTL NAMESX');
                }

                break;

            case '332': // Topic command
                if(!$bIsSlave) {
                    $pModules->onChannelTopic($this, $this->In->Chunks [3], $this->In->PostColon);
                }
                break;

            case '353': // Names command
                if(!$bIsSlave) {
                    $pModules->onChannelNames($this, $this->In->Chunks [4], $this->In->PostColon);
                }
                break;

            case 'invite': // Inviting someone to a channel
                if(!$bIsSlave) {
                    $pModules->onInvite($this, $this->In->Nickname, $this->In->Chunks [2], substr($this->In->Chunks [3], 1));
                }

                break;

            case 'join': // Joining a certain channel
                $sChannel = str_replace(':', '', $this->In->Chunks [2]);
                if($this->In->Nickname == $this->m_sNickname) {
                    $this->m_aBotInfo ['Channels'] [strtolower($sChannel)] = true;
                }

                if(!$bIsSlave) {
                    $pModules->onChannelJoin($this, $sChannel, $this->In->Nickname);
                }
                break;

            case 'kick': // When someone gets kicked
                if($this->In->Chunks [3] == $this->m_sNickname) {
                    unset ($this->m_aBotInfo ['Channels'] [strtolower($this->In->Chunks [2])]);
                }

                if(!$bIsSlave) {
                    $pModules->onChannelKick($this, $this->In->Chunks [2], $this->In->Chunks [3], $this->In->Nickname, $this->In->PostColon);
                }
                break;

            case 'mode': // Change a mode on a channel
                if(!$bIsSlave) {
                    $pModules->onChannelMode($this, $this->In->Chunks [2], implode(' ', array_slice($this->In->Chunks, 3)));
                }
                break;

            case 'nick': // Nickchanges
                if($this->In->Nickname == $this->m_sNickname) {
                    $this->m_sNickname = $this->In->PostColon;
                }

                if(!$bIsSlave) {
                    $pModules->onChangeNick($this, $this->In->Nickname, $this->In->PostColon);
                }
                break;

            case 'notice': // Notice received from someone/something
                if($this->In->PostColon != 'Nuwani201' && !$bIsSlave) {
                    $pModules->onNotice($this, $this->In->Chunks [2], $this->In->Nickname, $this->In->PostColon);
                }

                break;

            case 'part': // Leaving a channel
                $sChannel = str_replace(':', '', $this->In->Chunks [2]);
                if($this->In->Nickname == $this->m_sNickname) {
                    unset ($this->m_aBotInfo ['Channels'] [strtolower($sChannel)]);
                }

                if(!$bIsSlave) {
                    $pModules->onChannelPart($this, $sChannel, $this->In->Nickname, $this->In->PostColon);
                }
                break;

            case 'privmsg': // A normal message of somekind
                if($bIsSlave) {
                    break;
                }
                /** slaves don't handle messages **/

                $sMessageSource = ltrim($this->In->Chunks [2], '+%@&:');
                if(substr($this->In->PostColon, 0, 1) != chr(1) && !$bIsSlave) {
                    if(substr($sMessageSource, 0, 1) == '#') {
                        $pModules->onChannelPrivmsg($this, $sMessageSource, $this->In->Nickname, $this->In->PostColon);
                    } else {
                        $pModules->onPrivmsg($this, $this->In->Nickname, $this->In->PostColon);
                    }
                } else {
                    $sType = strtoupper(substr(str_replace("\001", '', $this->In->Chunks [3]), 1));
                    $sMessage = trim(substr($this->In->PostColon, strlen($sType) + 2, -1));

                    $pModules->onCTCP($this, $sMessageSource, $this->In->Nickname, $sType, $sMessage);
                }
                break;

            case 'topic': // A topic has been changed
                if(!$bIsSlave) {
                    $pModules->onChangeTopic($this, $this->In->Chunks [2], $this->In->Nickname, $this->In->PostColon);
                }
                break;

            case 'quit': // Leaving IRC alltogether
                if(!$bIsSlave) {
                    $pModules->onQuit($this, $this->In->Nickname, $this->In->PostColon);
                }
                break;

            default:
                $pModules->onUnhandledCommand($this);
                break;
        }

        if(!$bIsSlave) { // Slaves are dumb... no really, they are.
            $pModules->onRaw($this, $this->In->Raw);
        }

        ErrorExceptionHandler::$Context = null;

        return true;
    }

    /**
     * The function which tells this bot to connect to the IRC network.
     * Basically we just call the socket's connect function and ...
     * nothing more!
     * @return boolean
     */
    public function connect($sUsername = '', $sRealname = '') {
        $sUsername = isset($this->m_aBotInfo ['Username']) ? $this->m_aBotInfo ['Username'] : NUWANI_NAME;
        $sRealname = isset($this->m_aBotInfo ['Realname']) ? $this->m_aBotInfo ['Realname'] : NUWANI_VERSION_STR . ' Bot Platform';

        return $this->m_pSocket->connect($this->m_sNickname, $sUsername, $sRealname);
    }

    /**
     * This function gets called by the timer-handler when we have to ping
     * ourselfes, in order to be sure the connection stays alive.
     */
    public function onPing() {
        $this->m_pSocket->send('NOTICE ' . $this->m_sNickname . ' :Nuwani201');
    }

    /**
     * This function gets invoked whenever the connection gets reset, so
     * this is the place where we have to call the onDisconnect callback in
     * modules.
     *
     * @param integer $nReason Socket error that the connection got closed with.
     */
    public function onDisconnect($nReason) {
        ModuleManager::getInstance()->onDisconnect($this, $nReason);
    }

    /**
     * The process function will do internal things like keeping the bot
     * alive, as well as telling the socket to update itself with
     * interesting things.
     */
    public function process() {
        $this->m_pSocket->process();
    }

    /**
     * This function will allow modules and other functions to send commands
     * to the IRC server, which will be distributed immediatly.
     *
     * @param string  $sCommand     Line to send to the IRC server.
     * @param boolean $bSkipModules Don't inform the modules about this send.
     */
    public function send($sCommand, $bSkipModules = false) {
        $this->m_pSocket->send($sCommand);

        if($bSkipModules === false &&
            $this->m_bRawSendCalled === false &&
            $this->m_aBotInfo ['Slave'] === false
        ) {
            // Prevent infinite recursive calls.
            $this->m_bRawSendCalled = true;
            ModuleManager::getInstance()->onRawSend($this, $sCommand);
            $this->m_bRawSendCalled = false;
        }
    }

    /**
     * This function initialises the network this bot will be using, e.g.
     * bindings and the server which we have to join.
     *
     * @param string $sNetwork Name of the network this bot should join.
     *
     * @throws \InvalidArgumentException When the specified network was not found.
     * @return boolean
     */
    public function setNetwork($sNetwork) {
        $aServerInfo = NetworkManager::getInstance()->getServer($sNetwork);
        if($aServerInfo !== false) {
            $this->m_pSocket->setServer($aServerInfo ['IP']);
            $this->m_pSocket->setPort($aServerInfo ['Port']);
            $this->m_aBotInfo ['Network'] = [
                'Address' => $aServerInfo ['IP'],
                'Port' => $aServerInfo ['Port'],
                'Name' => $sNetwork
            ];

            return true;
        }

        throw new \InvalidArgumentException ('The network "' . $sNetwork . '" has not been defined.');
    }

    /**
     * Indicates whether this bot is a slave or a master. This is toggleable
     * throughout the bots runtime, even though it's not adviced to do so.
     *
     * @param boolean $bSlave Is this bot a slave?
     */
    public function setSlave($bSlave) {
        $this->m_aBotInfo ['Slave'] = $bSlave;
    }

    /**
     * This function checks whether we currently are in a channel or not.
     * This works on both active- as passive (resp. master and slave) bots.
     *
     * @param string $sChannel Channel you wish to check.
     *
     * @return boolean
     */
    public function inChannel($sChannel) {
        if(isset($this->m_aBotInfo ['Channels'] [strtolower($sChannel)])) {
            return true;
        }

        return false;
    }

    /**
     * Get a certain setting of the bot. This is allowed in all occasions
     * to avoid lots of get-functions.
     *
     * @param string $sKey Key of the entry that you want to receive.
     *
     * @return mixed
     */
    public function offsetGet($sKey) {
        switch($sKey) {
            case 'In':
                return $this->In;
                break;
            case 'Network':
                return $this->m_aBotInfo ['Network']['Name'];
                break;
            case 'Nickname':
                return $this->m_sNickname;
                break;
            case 'Socket':
                return $this->m_pSocket;
                break;
        }

        if(isset($this->m_aBotInfo [$sKey])) {
            return $this->m_aBotInfo [$sKey];
        }

        return false;
    }

    /**
     * Of course we might be interested in checkign whether a certain key
     * exists (silly!), so that's what will be done here.
     *
     * @param string $sKey Key of the entry that you want to check.
     *
     * @return boolean
     */
    public function offsetExists($sKey) {
        switch($sKey) {
            case 'In':
            case 'Network':
            case 'Nickname':
            case 'Socket':
                return true;
        }

        return isset($this->m_aBotInfo [$sKey]);
    }

    /**
     * Setting the value of one of the properties is not supported,
     * therefore this function will always return false.
     *
     * @param string $sKey   Key of the entry that you want to set.
     * @param mixed  $mValue Value to assign to the key.
     *
     * @return null
     */
    public function offsetSet($sKey, $mValue) {
        return;
    }

    /**
     * This function will get called as soon as unset() gets called on the
     * Bot instance, which is not properly supported either.
     *
     * @param string $sKey Key of the entry that you want to unset.
     *
     * @return null
     */
    public function offsetUnset($sKey) {
        return;
    }
}
