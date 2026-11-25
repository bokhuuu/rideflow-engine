<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when communication with the scooter infrastructure fails -
 * Python server unreachable, bad HTTP response, connection timeout.
 *
 * Not for device-level failures like "already locked" - those come back
 * as ScooterCommandResult with success=false, not as exceptions.
 */
class ScooterException extends RuntimeException {}
