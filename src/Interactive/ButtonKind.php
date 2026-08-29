<?php

declare(strict_types=1);

namespace Pdf\Interactive;

/**
 * What a {@see \Pdf\Node\PushButton} does when clicked.
 *
 * `Push` carries only a JavaScript `/A` action (or nothing); `Submit` and
 * `Reset` emit the native `/SubmitForm` / `/ResetForm` actions, which work
 * without JavaScript in Acrobat, Foxit and most desktop viewers.
 */
enum ButtonKind
{
    case Push;
    case Submit;
    case Reset;
}
