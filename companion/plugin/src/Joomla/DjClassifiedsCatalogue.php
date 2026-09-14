<?php

declare(strict_types=1);

namespace VDM\Plugin\Console\JoomlaMcp\Joomla;

use VDM\Plugin\Console\JoomlaMcp\Domain\CoreEntityDefinition;

/**
 * DJ-Classifieds entity catalogue.
 *
 * Covers items, categories, profiles, regions, plans, and types.
 * Component: com_djclassifieds.
 */
final class DjClassifiedsCatalogue
{
    private const ITEMS_READ = [
        'id', 'cat_id', 'type_id', 'user_id', 'name', 'alias', 'description', 'intro_desc',
        'date_start', 'date_exp', 'date_mod', 'date_sort', 'display', 'special', 'notify',
        'published', 'ordering', 'price', 'price_negotiable', 'contact', 'pay_type',
        'address', 'region_id', 'exp_days', 'promotions', 'post_code', 'video', 'website',
        'ip_address', 'currency', 'metakey', 'metadesc', 'latitude', 'longitude', 'email',
        'token', 'access_view', 'extra_images', 'extra_images_to_pay', 'extra_chars',
        'extra_chars_to_pay', 'auction', 'bid_min', 'bid_max', 'bid_autoclose',
        'price_reserve', 'price_start', 'quantity', 'buynow', 'unit_id', 'offer',
        'blocked', 'metarobots', 'new_draft', 'last_view', 'new', 'date_renew',
        'auction_assist', 'date_unverif',
    ];

    private const ITEMS_WRITE = [
        'cat_id', 'type_id', 'name', 'alias', 'description', 'intro_desc',
        'date_start', 'date_exp', 'date_mod', 'display', 'special', 'notify',
        'published', 'ordering', 'price', 'price_negotiable', 'contact', 'pay_type',
        'address', 'region_id', 'exp_days', 'promotions', 'post_code', 'video', 'website',
        'currency', 'metakey', 'metadesc', 'latitude', 'longitude', 'email',
        'access_view', 'quantity', 'unit_id', 'offer', 'blocked', 'metarobots',
    ];

    private const CATEGORIES_READ = [
        'id', 'name', 'alias', 'parent_id', 'price', 'description', 'ordering',
        'published', 'autopublish', 'metakey', 'metadesc', 'access', 'points',
        'ads_disabled', 'theme', 'access_view', 'access_item_view', 'restriction_18',
        'rev_group_id', 'schema_type', 'metarobots', 'metatitle', 'ads_limit',
        'header_text', 'map_marker_icon', 'auction_disabled', 'buynow_disabled',
        'offer_disabled',
    ];

    private const CATEGORIES_WRITE = [
        'name', 'alias', 'parent_id', 'price', 'description', 'ordering',
        'published', 'autopublish', 'metakey', 'metadesc', 'access', 'points',
        'ads_disabled', 'theme', 'access_view', 'access_item_view', 'restriction_18',
        'rev_group_id', 'schema_type', 'metarobots', 'metatitle', 'ads_limit',
        'header_text', 'map_marker_icon', 'auction_disabled', 'buynow_disabled',
        'offer_disabled',
    ];

    private const PROFILES_READ = [
        'user_id', 'group_id', 'region_id', 'address', 'post_code',
        'latitude', 'longitude', 'verified', 'disabled_emails', 'description',
    ];

    private const PROFILES_WRITE = [
        'group_id', 'region_id', 'address', 'post_code',
        'latitude', 'longitude', 'verified', 'disabled_emails', 'description',
    ];

    private const REGIONS_READ = [
        'id', 'name', 'parent_id', 'country', 'city', 'published',
        'latitude', 'longitude', 'country_iso', 'header_text', 'alias',
        'ordering', 'metatitle', 'metakey', 'metadesc', 'metarobots',
        'ads_disabled',
    ];

    private const REGIONS_WRITE = [
        'name', 'parent_id', 'country', 'city', 'published',
        'latitude', 'longitude', 'country_iso', 'header_text', 'alias',
        'ordering', 'metatitle', 'metakey', 'metadesc', 'metarobots',
        'ads_disabled',
    ];

    private const PLANS_READ = [
        'id', 'name', 'description', 'price', 'points', 'published', 'ordering',
        'groups_assignment', 'groups_restriction', 'params', 'recurring',
        'hidden_labels', 'groups_assignment_exp', 'one_time', 'exp_type',
        'groups_deassignment', 'verify', 'unverify_exp',
    ];

    private const PLANS_WRITE = [
        'name', 'description', 'price', 'points', 'published', 'ordering',
        'groups_assignment', 'groups_restriction', 'params', 'recurring',
        'hidden_labels', 'groups_assignment_exp', 'one_time', 'exp_type',
        'groups_deassignment', 'verify', 'unverify_exp',
    ];

    private const TYPES_READ = [
        'id', 'name', 'price', 'points', 'ordering', 'published',
        'params', 'ug_access_disallow', 'cat_access_disallow',
    ];

    private const TYPES_WRITE = [
        'name', 'price', 'points', 'ordering', 'published',
        'params', 'ug_access_disallow', 'cat_access_disallow',
    ];

    /** @return list<CoreEntityDefinition> */
    public static function all(): array
    {
        return [
            new CoreEntityDefinition(
                'djclassifieds.items',
                'DJ-Classifieds items',
                'com_djclassifieds',
                'Items',
                'Item',
                self::ITEMS_READ,
                self::ITEMS_WRITE,
                legacyModelPrefix: 'DjClassifiedsModel',
            ),
            new CoreEntityDefinition(
                'djclassifieds.categories',
                'DJ-Classifieds categories',
                'com_djclassifieds',
                'Categories',
                'Category',
                self::CATEGORIES_READ,
                self::CATEGORIES_WRITE,
                legacyModelPrefix: 'DjClassifiedsModel',
            ),
            new CoreEntityDefinition(
                'djclassifieds.profiles',
                'DJ-Classifieds user profiles',
                'com_djclassifieds',
                'Profiles',
                'Profile',
                self::PROFILES_READ,
                self::PROFILES_WRITE,
                primaryKey: 'user_id',
                supportsState: false,
                legacyModelPrefix: 'DjClassifiedsModel',
            ),
            new CoreEntityDefinition(
                'djclassifieds.regions',
                'DJ-Classifieds regions',
                'com_djclassifieds',
                'Regions',
                'Region',
                self::REGIONS_READ,
                self::REGIONS_WRITE,
                legacyModelPrefix: 'DjClassifiedsModel',
            ),
            new CoreEntityDefinition(
                'djclassifieds.plans',
                'DJ-Classifieds plans',
                'com_djclassifieds',
                'Plans',
                'Plan',
                self::PLANS_READ,
                self::PLANS_WRITE,
                legacyModelPrefix: 'DjClassifiedsModel',
            ),
            new CoreEntityDefinition(
                'djclassifieds.types',
                'DJ-Classifieds item types',
                'com_djclassifieds',
                'Types',
                'Type',
                self::TYPES_READ,
                self::TYPES_WRITE,
                legacyModelPrefix: 'DjClassifiedsModel',
            ),
        ];
    }
}
