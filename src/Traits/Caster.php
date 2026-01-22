<?php
    /*/
	 * Project Name:    Wingman — Database — Caster Trait
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 20 2026
	 * Last Modified:   Jan 20 2026
    /*/

    # Use the Database\Traits namespace.
    namespace Wingman\Database\Traits;

    # Import the following classes to the current scope.
    use BackedEnum;
    use DateTimeImmutable;
    use DateTimeInterface;
    use InvalidArgumentException;
    use UnitEnum;

    /**
     * Provides methods for casting database values to and from PHP types.
     * @package Wingman\Database\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait Caster {
        /**
         * Casts a binary UUID from the database to a string UUID.
         * @param string $binary The binary UUID from the database.
         * @return string The casted string UUID.
         */
        public function castFromBinaryUuid (string $binary) : string {
            $hex = unpack("H*", $binary)[1];
            return vsprintf("%s%s-%s-%s-%s-%s%s%s", str_split($hex, 4));
        }

        /**
         * Casts a value from the database to a boolean.
         * @param mixed $value The value from the database.
         * @return bool The casted boolean value.
         */
        public function castFromBoolean (mixed $value) : bool {
            return (bool) $value;
        }

        /**
         * Casts a date string from the database to a DateTimeImmutable object.
         * @param string $value The date string from the database.
         * @return DateTimeImmutable|null The casted DateTimeImmutable object, or null on failure.
         */
        public function castFromDate (string $value) : ?DateTimeImmutable {
            $date = DateTimeImmutable::createFromFormat("Y-m-d", $value);
            return $date ?: null;
        }
        
        /**
         * Casts a datetime string from the database to a DateTimeImmutable object.
         * @param string $value The datetime string from the database.
         * @return DateTimeImmutable|null The casted DateTimeImmutable object, or null on failure.
         */
        public function castFromDateTime (string $value) : ?DateTimeImmutable {
            $format = str_contains($value, '.') ? "Y-m-d H:i:s.u" : "Y-m-d H:i:s";
            $date = DateTimeImmutable::createFromFormat($format, $value);
            return $date ?: null;
        }
        
        /**
         * Casts a decimal value from the database to a string.
         * @param mixed $value The decimal value from the database.
         * @return string The casted string value.
         */
        public function castFromDecimal (mixed $value) : string {
            # Always return as string to preserve precision for bcmath.
            return (string) $value;
        }

        /**
         * Casts a value from the database to an enum instance.
         * @param string|int $value The value from the database.
         * @param string $enumClass The enum class name.
         * @return UnitEnum|null The casted enum instance, or null if no match is found.
         * @throws InvalidArgumentException If the provided class is not a valid enum.
         */
        public function castFromEnum (string|int $value, string $enumClass) : ?UnitEnum {
            if (!enum_exists($enumClass)) {
                throw new InvalidArgumentException("$enumClass is not a valid Enum.");
            }
            
            return $enumClass::tryFrom($value) ?: null;
        }
        
        /**
         * Casts a JSON string from the database to an associative array.
         * @param string $value The JSON string from the database.
         * @return array The casted associative array.
         * @throws JsonException If decoding fails.
         */
        public function castFromJson (string $value): array {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        }
        
        /**
         * Casts a Unix timestamp in milliseconds from the database to a DateTimeImmutable object.
         * @param int|string $value The Unix timestamp in milliseconds from the database.
         * @return DateTimeImmutable|null The casted DateTimeImmutable object, or null on failure.
         */
        public function castFromUnixMs (int|string $value) : ?DateTimeImmutable {
            $seconds = floor((int) $value / 1000);
            $milliseconds = (int) $value % 1000;
            $date = DateTimeImmutable::createFromFormat("U.v", "$seconds.$milliseconds");
            return $date ?: null;
        }
        
        /**
         * Casts a time string from the database to a DateTimeImmutable object.
         * @param string $value The time string from the database.
         * @return DateTimeImmutable|null The casted DateTimeImmutable object, or null on failure.
         */
        public function castFromTime (string $value) : ?DateTimeImmutable {
            # Time doesn't have a date, so PHP will default to 'today'.
            $time = DateTimeImmutable::createFromFormat("H:i:s", $value);
            return $time ?: null;
        }

        /**
         * Casts a string UUID to a binary UUID for database storage.
         * @param string $uuid The string UUID to cast.
         * @return string The casted binary UUID.
         */
        public function castToBinaryUuid (string $uuid) : string {
            return pack("H*", str_replace('-', '', $uuid));
        }

        /**
         * Casts a boolean value to a string for database storage.
         * @param bool $value The boolean value to cast.
         * @return string The casted string value.
         */
        public function castToBoolean (bool $value) : string {
            return $value ? '1' : '0';
        }
        
        /**
         * Casts a DateTimeInterface object to a date string for database storage.
         * @param DateTimeInterface $value The DateTimeInterface object to cast.
         * @return string The casted date string.
         */
        public function castToDate (DateTimeInterface $value) : string {
            return $value->format("Y-m-d");
        }

        /**
         * Casts a DateTimeInterface object to a datetime string for database storage.
         * @param DateTimeInterface $value The DateTimeInterface object to cast.
         * @return string The casted datetime string.
         */
        public function castToDateTime (DateTimeInterface $value) : string {
            return $value->format("Y-m-d H:i:s");
        }

        /**
         * Casts a decimal value to a string for database storage.
         * @param string|float $value The decimal value to cast.
         * @param int $precision The number of decimal places.
         * @return string The casted string value.
         */
        public function castToDecimal (string|float $value, int $precision = 2) : string {
            return number_format((float) $value, $precision, '.', '');
        }
        
        /**
         * Casts an enum value to its backing value or name for database storage.
         * @param UnitEnum $value The enum value to cast.
         * @return string|int The casted backing value or name.
         */
        public function castToEnum (UnitEnum $value) : string|int {
            return $value instanceof BackedEnum ? $value->value : $value->name;
        }

        /**
         * Casts an array or object to a JSON string for database storage.
         * @param array|object $value The array or object to cast.
         * @return string The casted JSON string.
         * @throws JsonException If encoding fails.
         */
        public function castToJson (array|object $value) : string {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        /**
         * Casts a DateTimeInterface object to a time string for database storage.
         * @param DateTimeInterface $value The DateTimeInterface object to cast.
         * @return string The casted time string.
         */
        public function castToTime (DateTimeInterface $value) : string {
            return $value->format("H:i:s");
        }

        /**
         * Casts a DateTimeInterface object to a Unix timestamp in milliseconds.
         * @param DateTimeInterface $value The DateTimeInterface object to cast.
         * @return int The cast Unix timestamp in milliseconds.
         */
        public function castToUnixMs (DateTimeInterface $value) : int {
            $seconds = (int) $value->format('U');
            $ms = (int) $value->format('v');
            return ($seconds * 1000) + $ms;
        }
    }
?>