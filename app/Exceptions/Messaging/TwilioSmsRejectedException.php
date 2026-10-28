<?php

namespace App\Exceptions\Messaging;

use RuntimeException;

/** The message was rejected before acceptance; a corrected attempt may be sent. */
class TwilioSmsRejectedException extends RuntimeException {}
