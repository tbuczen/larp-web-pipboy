<?php

declare(strict_types=1);

namespace App\Enum;

enum ChatStatus: string
{
    /** Requested with an opening message; the receiver has not answered. */
    case PENDING = 'pending';
    /** Accepted by the receiver: both participants can write. */
    case ACTIVE = 'active';
    /** Declined by the receiver; the requester still sees it as pending. */
    case DECLINED = 'declined';
    /** Ended by either participant; history stays readable, nobody can write. */
    case CLOSED = 'closed';
}
