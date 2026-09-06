<?php

namespace App\Exceptions;

use RuntimeException;

/** The requested item code is already used by another item in the same shop. */
class TagConflictException extends RuntimeException {}
