<?php

namespace App\Enums;

/**
 * Stable application error codes returned by the public API.
 */
enum BookingError: string
{
    case SlotNotAvailable = 'SLOT_NOT_AVAILABLE';
    case PartySizeNotSupported = 'PARTY_SIZE_NOT_SUPPORTED';
    case BookingTooSoon = 'BOOKING_TOO_SOON';
    case DateOutOfRange = 'DATE_OUT_OF_RANGE';
    case DuplicateReservation = 'DUPLICATE_RESERVATION';
    case PhoneDailyLimitReached = 'PHONE_DAILY_LIMIT_REACHED';
    case TableNotAvailable = 'TABLE_NOT_AVAILABLE';
    case ReservationCancelled = 'RESERVATION_CANCELLED';
    case ReservationTokenMissing = 'RESERVATION_TOKEN_MISSING';
    case ReservationNotFound = 'RESERVATION_NOT_FOUND';
    case MonthOutOfRange = 'MONTH_OUT_OF_RANGE';

    public function message(): string
    {
        return match ($this) {
            self::MonthOutOfRange => 'Il mese selezionato non è consultabile.',
            self::ReservationTokenMissing => 'Per accedere alla prenotazione è necessario il codice segreto (header X-Reservation-Token).',
            self::ReservationNotFound => 'Prenotazione non trovata.',
            self::SlotNotAvailable => "L'orario selezionato non è più disponibile.",
            self::PartySizeNotSupported => 'Non ci sono tavoli prenotabili per il numero di persone indicato.',
            self::BookingTooSoon => "L'orario selezionato è troppo vicino: scegli un orario successivo.",
            self::DateOutOfRange => 'La data selezionata non è prenotabile.',
            self::DuplicateReservation => 'Esiste già una prenotazione identica per questo numero di telefono.',
            self::PhoneDailyLimitReached => 'È stato raggiunto il numero massimo di prenotazioni per questo numero di telefono nella giornata.',
            self::TableNotAvailable => 'Il tavolo selezionato non è compatibile o non è libero in questo orario.',
            self::ReservationCancelled => 'La prenotazione è annullata e non può essere modificata.',
        };
    }

    public function httpStatus(): int
    {
        return match ($this) {
            self::ReservationTokenMissing => 401,
            self::ReservationNotFound => 404,
            self::SlotNotAvailable,
            self::DuplicateReservation,
            self::PhoneDailyLimitReached,
            self::TableNotAvailable,
            self::ReservationCancelled => 409,
            self::PartySizeNotSupported,
            self::BookingTooSoon,
            self::DateOutOfRange,
            self::MonthOutOfRange => 422,
        };
    }
}
