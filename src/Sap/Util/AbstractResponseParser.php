<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Util;

abstract class AbstractResponseParser
{
    public const INVALID_DATE = '0000-00-00';

    protected function getStringPart(string $content, string $start, string $end): string
    {
        $content = ' ' . $content;
        $ini     = strpos($content, $start);

        if ($ini === 0) {
            return '';
        }
        $ini += strlen($start);
        $len = strpos($content, $end, $ini) - $ini;

        return substr($content, $ini, $len);
    }

    protected function getDateTimeImmutableFromString(string $dateString): ?\DateTimeImmutable
    {
        $timezone = new \DateTimeZone(ini_get('date.timezone') ?: 'UTC');
        $date     = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $dateString . ' 00:00:00',
            $timezone
        );

        return ($date instanceof \DateTimeImmutable) ? $date : null;
    }

    protected function getSapCreationDateTime(?string $creationDate, ?string $creationTime): ?\DateTimeImmutable
    {
        if ($creationDate === null) {
            return null;
        }

        $timezone = new \DateTimeZone(ini_get('date.timezone') ?: 'UTC');
        $date     = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $creationDate . ' ' . $creationTime,
            $timezone
        );

        return ($date instanceof \DateTimeImmutable) ? $date : null;
    }

    /**
     * Returns null|or the given $type
     */
    protected function getData(array $data, string $key, ?string $type = 'string', ?bool $trimZero = false): mixed
    {
        if ($type === null) {
            $type = 'string';
        }

        if (empty($data[$key] ?? null)) {
            return null;
        }

        $value = $data[$key];

        /** @var $type string */
        if ($type === 'DateTime') {
            if ($value === self::INVALID_DATE) {
                return null;
            }

            return $this->getDateTimeImmutableFromString($value);
        }

        if ($trimZero) {
            $value = ltrim($value, '0');
        }

        settype($value, $type);

        return $value;
    }
}
