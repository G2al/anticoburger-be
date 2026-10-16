<?php

namespace App\Filament\Support;

use App\Exceptions\BookingException;
use Closure;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Runs a service call from Filament and turns booking rule violations into
 * admin feedback instead of errors.
 */
final class BookingFeedback
{
    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $operation
     * @param  string|null  $formStatePath  when set, validation errors are attached to the form fields
     * @return TResult
     *
     * @throws Halt
     * @throws ValidationException
     */
    public static function run(Closure $operation, ?string $formStatePath = null): mixed
    {
        try {
            return $operation();
        } catch (BookingException $exception) {
            self::notifyFailure($exception->getMessage());

            throw new Halt;
        } catch (ValidationException $exception) {
            if ($formStatePath === null) {
                self::notifyFailure(Arr::first(Arr::flatten($exception->errors())) ?? $exception->getMessage());

                throw new Halt;
            }

            throw ValidationException::withMessages(
                collect($exception->errors())
                    ->mapWithKeys(fn (array $messages, string $key) => ["{$formStatePath}.{$key}" => $messages])
                    ->all()
            );
        }
    }

    public static function notifyFailure(string $message): void
    {
        Notification::make()
            ->title('Operazione non consentita')
            ->body($message)
            ->danger()
            ->persistent()
            ->send();
    }
}
