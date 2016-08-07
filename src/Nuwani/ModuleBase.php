<?php
// Copyright 2008-2015 Las Venturas Playground. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Nuwani;

abstract class ModuleBase {
    /**
     * These constants can be used to cleanly format IRC messages without
     * the need to use them in-line. There are various COLOUR_* constants
     * too. In octal by the way.
     * @var string
     */
    const BOLD = "\002";
    const CLEAR = "\017";
    const COLOUR = "\003";
    const CTCP = "\001";
    const INVERSE = "\026";
    const TAB = "\011";
    const ITALIC = "\035";
    const UNDERLINE = "\037";

    /**
     * A set of constant values which define the colours that can be used
     * with IRC messages. Keep in mind that these do not include backgrounds.
     * @var string
     */
    const COLOUR_WHITE = "\00300";
    const COLOUR_BLACK = "\00301";
    const COLOUR_DARKBLUE = "\00302";
    const COLOUR_DARKGREEN = "\00303";
    const COLOUR_RED = "\00304";
    const COLOUR_BROWN = "\00305";
    const COLOUR_PURPLE = "\00306";
    const COLOUR_ORANGE = "\00307";
    const COLOUR_YELLOW = "\00308";
    const COLOUR_GREEN = "\00309";
    const COLOUR_TEAL = "\00310";
    const COLOUR_LIGHTBLUE = "\00311";
    const COLOUR_BLUE = "\00312";
    const COLOUR_PINK = "\00313";
    const COLOUR_DRAKGREY = "\00314";
    const COLOUR_GREY = "\00315";
}
