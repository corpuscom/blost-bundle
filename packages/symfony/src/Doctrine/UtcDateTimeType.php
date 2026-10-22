<?php

declare(strict_types=1);

namespace Corpuscom\Blost\SymfonyBlog\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeImmutableType;

final class UtcDateTimeType extends DateTimeImmutableType
{
    public const NAME = 'blost_utc_datetime';

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof \DateTimeImmutable) {
            $value = $value->setTimezone(new \DateTimeZone('UTC'));
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        if (is_string($value)) {
            $value = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        }

        return parent::convertToPHPValue($value, $platform);
    }
}
