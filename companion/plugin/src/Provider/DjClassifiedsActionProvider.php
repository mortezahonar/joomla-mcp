<?php

declare(strict_types=1);

namespace VDM\Plugin\Console\JoomlaMcp\Provider;

use VDM\Plugin\Console\JoomlaMcp\Action\CoreEntityAction;
use VDM\Plugin\Console\JoomlaMcp\Action\DjClassifiedsInspectAction;
use VDM\Plugin\Console\JoomlaMcp\Contract\ActionInterface;
use VDM\Plugin\Console\JoomlaMcp\Contract\ModelProviderInterface;
use VDM\Plugin\Console\JoomlaMcp\Joomla\DjClassifiedsCatalogue;

/** Owns the DJ-Classifieds action set and its provider-specific write hook. */
final readonly class DjClassifiedsActionProvider
{
    public function __construct(
        private object $application,
        private ModelProviderInterface $models,
    ) {
    }

    public function inspectionAction(): ActionInterface
    {
        return new DjClassifiedsInspectAction($this->application);
    }

    /** @return list<ActionInterface> */
    public function entityActions(): array
    {
        $actions = [];

        foreach (DjClassifiedsCatalogue::all() as $entity) {
            $expiryHook = $entity->id === 'djclassifieds.items'
                ? new DjClassifiedsItemExpiryHook()
                : null;

            foreach (['list', 'get', 'create', 'update', 'delete'] as $operation) {
                $payloadHook = in_array($operation, ['create', 'update'], true) ? $expiryHook : null;
                $actions[] = new CoreEntityAction($entity, $operation, $this->models, $payloadHook);
            }

            if ($entity->supportsState) {
                $actions[] = new CoreEntityAction($entity, 'state', $this->models);
            }
        }

        return $actions;
    }
}
