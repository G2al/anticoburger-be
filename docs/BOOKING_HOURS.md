# Public weekly booking hours

`GET /api/v1/booking-hours` (same read throttle as booking settings) returns:

```json
{"data":{"timezone":"Europe/Rome","days":[{"day_of_week":1,"ranges":[{"first_booking_time":"18:30","last_booking_time":"23:25"}]}]}}
```

The response always contains all seven ISO weekdays. Empty `ranges` means no weekly booking service that day. Ranges are the union of configured booking start windows of active tables with allowed party sizes. Separate services retain their gaps. Values are local `HH:mm` times in `timezone`.

This is a weekly overview, not opening/closing times, generated time slots or current availability. Reservations, lead time, date-specific blocks and holidays do not change this overview; clients must use the calendar/availability endpoints when booking. Responses use `Cache-Control: no-store` so admin schedule changes are immediately reflected on the next request.

No migrations or reseeding are required. Deploy this endpoint before deploying a frontend that consumes it.
