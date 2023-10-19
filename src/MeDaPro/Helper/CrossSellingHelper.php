<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Helper;

class CrossSellingHelper
{
    private const CROSSSELLING_GROUP_KEY        = 'Typname';
    private const CROSSSELLING_GROUP_MEMBER_KEY = 'Artikel-Nr';

    public static function getCrossSellingGroups(array $productData): array
    {
        $group  = null;
        $groups = [];

        // Parsing this implies a structure where the group is started first, with the members immediately afterwards
        foreach ($productData as $key => $value) {
            if (str_starts_with($key, self::CROSSSELLING_GROUP_KEY)) {
                $group = $value;
            } elseif ($group && str_starts_with($key, self::CROSSSELLING_GROUP_MEMBER_KEY)) {
                $groups[$group][$value] = $value;
            } else {
                $group = null;
            }
        }

        foreach ($groups as $group => $productNumbers) {
            $groups[$group] = array_filter($productNumbers);
        }

        return array_filter($groups);
    }
}
