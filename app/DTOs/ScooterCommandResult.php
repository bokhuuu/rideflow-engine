<?php

namespace App\DTOs;

/**
 * The result of any command sent to a scooter.
 *
 * Returned by every ScooterService method instead of a raw array.
 * Readonly - a result is a fact, it shouldn't change after it's built.
 */
readonly class ScooterCommandResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public array $data = [],
    ) {}

    /**
     * Safely read a value from $data by key.
     *
     * Use this instead of $result->data['key'] directly - won't crash if the key is missing.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}
