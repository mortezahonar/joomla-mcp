<?php

declare(strict_types=1);

namespace VDM\Plugin\Console\JoomlaMcp\Provider;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use VDM\Plugin\Console\JoomlaMcp\Contract\CoreEntityPayloadHookInterface;

/**
 * Mirrors the DJ-Classifieds admin item lifecycle for expiry fields.
 *
 * The component derives date_exp from exp_days through its FormController
 * postSaveHook (controllers/item.php) and loadFormData defaults (models/item.php).
 * The companion calls the administrator model directly, so this provider hook
 * derives the same fields before the model receives the payload.
 */
final readonly class DjClassifiedsItemExpiryHook implements CoreEntityPayloadHookInterface
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $data
     * @param array<string, mixed> $existing
     *
     * @return array<string, mixed>
     */
    public function prepareForSave(array $payload, array $data, array $existing = []): array
    {
        if ($existing !== [] && !array_key_exists('exp_days', $data)) {
            return $payload;
        }

        $existingExpDays = $existing['exp_days'] ?? $payload['exp_days'] ?? null;
        $existingDateExp = (string) ($existing['date_exp'] ?? $payload['date_exp'] ?? '');

        if ($existing === [] && !array_key_exists('exp_days', $data)) {
            $params = ComponentHelper::getParams('com_djclassifieds');
            $payload['exp_days'] = $params->get('exp_days', '7');
            $expDays = $payload['exp_days'];
        } else {
            $expDays = $data['exp_days'];
        }

        if ($expDays === '' || $expDays === null) {
            if ($existing !== [] && $existingExpDays !== '' && $existingExpDays !== null) {
                $payload['exp_days'] = $existingExpDays;
            }

            return $payload;
        }

        $oldExpDays = $existingExpDays;

        if ($existing !== [] && (string) $oldExpDays === '0' && $existingDateExp !== '2038-01-01 00:00:00') {
            $oldExpDays = '';
        }

        if ($existing !== [] && (string) $oldExpDays === (string) $expDays) {
            return $payload;
        }

        $dateExp = $this->djClassifiedsItemExpiry((int) $expDays);

        if ($dateExp !== null) {
            $payload['date_exp'] = $dateExp;
            $payload['exp_days'] = (int) $expDays;
        }

        return $payload;
    }

    private function djClassifiedsItemExpiry(int $expDays): ?string
    {
        if ($expDays === 0) {
            return '2038-01-01 00:00:00';
        }

        $dateExp = Factory::getDate()->modify('+' . $expDays . ' day')->toSQL();

        if ($dateExp === '1970-01-01 1:00:00' || $dateExp > '2038-01-01 00:00:00') {
            return '2038-01-01 00:00:00';
        }

        return $dateExp;
    }
}
