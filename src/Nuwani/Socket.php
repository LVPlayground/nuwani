<?php
// Copyright 2008-2015 Las Venturas Playground. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Nuwani;

class Socket {
    /**
     * Contains the raw PHP socket which is used for communication over the
     * network towards the IRC server.
     * @var resource
     */
    private $m_rSocket;

    /**
     * An array with basic information about the socket; the server we'll be
     * connecting with, port number, IP and port we'll locally bind to,
     * etcetera.
     * @var array
     */
    private $m_aSocketInfo;

    /**
     * Incase we receive incomplete messages from the IRC server, we should
     * buffer them and re-use them in a later cycle when they're completed.
     * @var string
     */
    private $m_sRecvBuffer;

    /**
     * Contains the instance of the bot which owns us. This is used to send
     * the bot the callback for each message that's being received through
     * this socket.
     * @var Bot
     */
    private $m_pBot;

    /**
     * The constructor will initialise the default values, e.g. create the
     * socket that will be used for the connection. Actual connection will
     * be done later on.
     *
     * @param Bot $pBot The bot which eventually owns this socket.
     */
    public function __construct($pBot) {
        $this->m_rSocket = null;

        $this->m_aSocketInfo = [
            'Connected' => false,
            'ConnectTry' => 0,
            'NextTry' => 0,

            'RemoteIP' => '',
            'RemotePort' => 6667,

            'BindIP' => null,

            'Statistics' => [
                'Packets' => ['In' => 0, 'Out' => 0],
                'Bytes' => ['In' => 0, 'Out' => 0]
            ],

            'Context' => stream_context_create([]),
            'SSL' => true
        ];

        $this->m_aRecvBuffer = '';
        $this->m_pBot = $pBot;
    }

    /**
     * Defines the server which will be used for communication. This must be
     * an IP address, though we don't specifically check for it.
     *
     * @param string $sAddress IP Address of the server we're going to use.
     */
    public function setServer($sAddress) {
        $this->m_aSocketInfo ['RemoteIP'] = $sAddress;
    }

    /**
     * Port number of the IRC server. Usually this will be 6667, seeing 6697
     * usually gets used for secured connections which is supported as well.
     *
     * @param integer $nPort Port number we should be connecting to.
     */
    public function setPort($nPort) {
        $this->m_aSocketInfo ['RemotePort'] = $nPort;
    }

    /**
     * Sets the IP this socket should used to bind to locally, thus on our
     * own side of the connection. Some IRC servers limit the number of
     * connections that can be made from a single IP, this can be used to
     * have more.
     *
     * @param string $sIpAddress IP Address this socket will be bound to.
     */
    public function setBindTo($sIpAddress) {
        $this->m_aSocketInfo ['BindIP'] = $sIpAddress;
    }

    /**
     * For those of you paranoid of people listening on your connections,
     * Nuwani is one of the rare IRC bots that supports secured connections.
     * Feel free to enable it, however, think about updating the port too.
     *
     * @param boolean $bEnabled Should we be using a secured connection?
     */
    public function setSecuredConnection($bEnabled) {
        $this->m_aSocketInfo ['SSL'] = $bEnabled;
    }

    /**
     * The connect function will tell the socket to connect to the IRC
     * server and send the initialisation commands, being NICK and USER.
     * Returns a boolean telling you whether we connected successfully.
     * This method will automatically unregister the bot when the bot cannot
     * connect.
     *
     * @param string $sNickname Nickname to connect with.
     * @param string $sUsername Username to connect with.
     * @param string $sRealname Realname to use with this connection.
     *
     * @return boolean
     */
    public function connect($sNickname, $sUsername, $sRealname) {
        if($this->m_aSocketInfo ['NextTry'] > time()) {
            return false;
        }

        $sProtocol = 'tcp';

        $this->m_aSocketInfo ['NextTry'] = 15 * pow(2, $this->m_aSocketInfo ['ConnectTry']);
        $this->m_aSocketInfo ['ConnectTry']++;

        if($this->m_aSocketInfo ['BindIP'] != null) {
            stream_context_set_option($this->m_aSocketInfo ['Context'], 'socket', 'bindto', $this->m_aSocketInfo ['BindIP']);
        }

        if($this->m_aSocketInfo ['SSL'] !== false && extension_loaded('openssl')) {
            stream_context_set_option($this->m_aSocketInfo ['Context'], [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                    'local_cert' => __DIR__ . 'nuwani.pem',
                    'passphrase' => ''
                ]
            ]);

            $this->m_aSocketInfo ['RemotePort'] = $this->m_aSocketInfo ['SSL'];
            $sProtocol = 'ssl';
        }

        $this->m_rSocket = stream_socket_client($sProtocol . '://' .
            $this->m_aSocketInfo ['RemoteIP'] . ':' . $this->m_aSocketInfo ['RemotePort'],
            $nErrorNumber, $sErrorString, 2.0, STREAM_CLIENT_CONNECT, $this->m_aSocketInfo ['Context']);

        if($this->m_rSocket !== false) {
            fwrite($this->m_rSocket, 'USER ' . $sUsername . ' ' . $sUsername . ' - :' . $sRealname . PHP_EOL);
            fwrite($this->m_rSocket, 'NICK ' . $sNickname . PHP_EOL);

            stream_set_blocking($this->m_rSocket, 0);

            // When successful, reset everything.
            $this->m_aSocketInfo ['Connected'] = true;
            $this->m_aSocketInfo ['ConnectTry'] = 0;

            return true;
        }

        echo '[Socket] Could not connect to "' . $this->m_aSocketInfo ['RemoteIP'] . ':' . $this->m_aSocketInfo ['RemotePort'] . '" (attempt ' . $this->m_aSocketInfo ['ConnectTry'] . '/5): ' .
            $sErrorString . ' (' . $nErrorNumber . ').' . PHP_EOL;

        if($this->m_aSocketInfo ['ConnectTry'] >= 5) {
            echo '[Socket] Destroying bot ' . $this->m_pBot ['Nickname'] . ' after 5 failed connection attempts.' . PHP_EOL;

            BotManager::getInstance()->destroy($this->m_pBot);

            return false;
        }

        echo '[Socket] Retrying in ' . $this->m_aSocketInfo ['NextTry'] . ' seconds...' . PHP_EOL;

        new Timer ([$this->m_pBot, 'connect'], $this->m_aSocketInfo ['NextTry'] * 1000);

        return false;
    }

    /**
     * This method will close the socket.
     */
    public function close() {
        if($this->m_rSocket !== null) {
            fclose($this->m_rSocket);

            $this->m_aSocketInfo ['Connected'] = false;
            $this->m_rSocket = null;
            $this->m_aRecvBuffer = '';
        }
    }

    /**
     * For some purposes we might want this socket to go in blocking mode,
     * therefore this function was implemented.
     *
     * @param boolean $bBlocking Should this be a blocking socket?
     *
     * @return boolean
     */
    public function setBlocking($bBlocking) {
        return stream_set_blocking($this->m_rSocket, ($bBlocking ? 1 : 0));
    }

    /**
     * This function will send a certain command directly to the server, no
     * buffer or anything will be applied on top of that.
     *
     * @param string $sCommand The command to send to the server.
     *
     * @return boolean
     */
    public function send($sCommand) {
        if($this->m_rSocket !== false && $this->m_rSocket !== null) {
            $sCommand = trim($sCommand);

            $this->m_aSocketInfo ['Statistics'] ['Packets']['Out']++;
            $this->m_aSocketInfo ['Statistics'] ['Bytes']  ['Out'] += strlen($sCommand);

            if(fwrite($this->m_rSocket, $sCommand . PHP_EOL) === false) {
                return $this->handleError();
            }

            if(strtoupper(substr($sCommand, 0, 4)) == 'QUIT') {
                // We don't want the bot reconnecting now.
                $this->close();
                $this->m_pBot->onDisconnect(0);

                BotManager::getInstance()->destroy($this->m_pBot);

                echo '[Socket] Bot ' . $this->m_pBot ['Nickname'] . ' quit from ' . $this->m_pBot ['Network'] . ' by command. Destroying the bot.' . PHP_EOL;
            }

            return true;
        }

        return false;
    }

    /**
     * The process function will check the socket to see if any data can
     * be returned. If there is any, they will be returned using the Bot's
     * callback function (usually onReceive).
     */
    public function process() {
        if(!$this->m_aSocketInfo ['Connected']) {
            // Nothing to process if there's no connection.
            return;
        }

        if(!$this->m_rSocket || feof($this->m_rSocket)) // The bot died;
        {
            echo '[Socket] Bot ' . $this->m_pBot ['Nickname'] . ' got disconnected from server. Retrying in 3 seconds...' . PHP_EOL;

            $this->m_pBot->onDisconnect();

            $this->m_aSocketInfo ['Connected'] = false;

            // Try to reconnect extra fast, since connect() will
            // start with a 15 second timeout when connection fails.
            new Timer ([$this->m_pBot, 'connect'], 3000);

            return;
        }

        $sIncoming = fread($this->m_rSocket, 2048);
        if($sIncoming !== false) {
            $aIncoming = explode(PHP_EOL, ltrim($this->m_sRecvBuffer . $sIncoming));
            $this->m_sRecvBuffer = array_pop($aIncoming);

            foreach($aIncoming as $sLine) {
                $sLine = trim($sLine);
                if(strlen($sLine) <= 3) {
                    continue;
                } // Too short to be serious

                $this->m_aSocketInfo ['Statistics'] ['Packets']['In']++;
                $this->m_aSocketInfo ['Statistics'] ['Bytes']  ['In'] += strlen($sLine);

                $this->m_pBot->onReceive($sLine);
            }
        } else {
            echo '[Socket] Bot ' . $this->m_pBot ['Nickname'] . ' got disconnected from server. Retrying in 3 seconds...' . PHP_EOL;

            $this->m_pBot->onDisconnect();

            $this->m_aSocketInfo ['Connected'] = false;

            // Try to reconnect extra fast, since connect() will
            // start with a 15 second timeout when connection fails.
            new Timer ([$this->m_pBot, 'connect'], 3000);

            return;
        }
    }

    /**
     * This function gets called when an error occured on the socket, like a
     * broken pipe, a disconnect or whatever other kind of problem.
     *
     * @param integer $nErrorNumber Socket error that the connection got closed with.
     *
     * @return boolean
     */
    private function handleError($nErrorNumber = 0) {
        $this->close();

        $this->m_pBot->onDisconnect($nErrorNumber);

        return false;
    }

    /**
     * This function will return the internal statistic array, so it can
     * be used for whatever purpose it will be used.
     * @return array
     */
    public function getStatistics() {
        return $this->m_aSocketInfo ['Statistics'];
    }
}
