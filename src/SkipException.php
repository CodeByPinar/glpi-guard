<?php

declare(strict_types=1);

namespace GlpiGuard;

/** Thrown by a check that cannot run here (missing table, Windows permissions, no DB …). Not an error. */
final class SkipException extends \RuntimeException
{
}
