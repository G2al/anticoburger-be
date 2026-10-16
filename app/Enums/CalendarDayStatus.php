<?php

namespace App\Enums;

/**
 * Public status of a calendar day for a given party size.
 */
enum CalendarDayStatus: string
{
    /** At least one start time is free. */
    case Available = 'available';

    /** The restaurant is open but every start time is already taken. */
    case FullyBooked = 'fully_booked';

    /** No start time is scheduled, or every scheduled start time is blocked. */
    case Closed = 'closed';

    /** Outside the booking window (past, beyond the advance limit, or no start time left after the lead time). */
    case NotBookable = 'not_bookable';
}
