<?php

declare(strict_types=1);

namespace VDM\Plugin\Console\JoomlaMcp\Contract;

interface CoreEntityPayloadHookInterface
{
    /**
     * Prepare a validated entity payload immediately before it is handed to the Joomla model.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $data
     * @param array<string, mixed> $existing
     *
     * @return array<string, mixed>
     */
    public function prepareForSave(array $payload, array $data, array $existing = []): array;
}
