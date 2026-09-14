<?php

declare(strict_types=1);

namespace VDM\Plugin\Console\JoomlaMcp\Joomla;

use Throwable;
use VDM\Plugin\Console\JoomlaMcp\Contract\ModelProviderInterface;
use VDM\Plugin\Console\JoomlaMcp\Domain\ActionException;

final readonly class JoomlaModelProvider implements ModelProviderInterface
{
    public function __construct(private object $application)
    {
    }

    public function administrator(string $component, string $modelName, ?string $legacyModelPrefix = null): object
    {
        if (!method_exists($this->application, 'bootComponent')) {
            throw new ActionException('JOOMLA_RUNTIME_UNAVAILABLE', 'The Joomla component runtime is unavailable.');
        }

        $this->defineComponentPaths($component);

        try {
            $componentInstance = $this->application->bootComponent($component);
        } catch (Throwable) {
            throw new ActionException('COMPONENT_UNAVAILABLE', sprintf('Component "%s" is unavailable.', $component));
        }

        if (!is_object($componentInstance) || !method_exists($componentInstance, 'getMVCFactory')) {
            throw new ActionException('COMPONENT_UNAVAILABLE', sprintf('Component "%s" is unavailable.', $component));
        }

        $prefix = $legacyModelPrefix ?? 'Administrator';

        if ($legacyModelPrefix !== null) {
            $this->registerLegacyComponentPaths($legacyModelPrefix, $component);
        }

        try {
            $model = $componentInstance->getMVCFactory()->createModel(
                $modelName,
                $prefix,
                ['ignore_request' => true],
            );
        } catch (Throwable) {
            throw new ActionException('MODEL_UNAVAILABLE', sprintf('Joomla model "%s.%s" is unavailable.', $component, $modelName));
        }

        if (!is_object($model)) {
            throw new ActionException('MODEL_UNAVAILABLE', sprintf('Joomla model "%s.%s" is unavailable.', $component, $modelName));
        }

        return $model;
    }

    private function registerLegacyComponentPaths(string $legacyModelPrefix, string $component): void
    {
        if (!defined('JPATH_COMPONENT_ADMINISTRATOR')) {
            return;
        }

        \Joomla\CMS\MVC\Model\BaseDatabaseModel::addIncludePath(
            JPATH_COMPONENT_ADMINISTRATOR . '/models',
            $legacyModelPrefix,
        );

        foreach ([JPATH_COMPONENT_ADMINISTRATOR . '/loader.php', JPATH_COMPONENT_ADMINISTRATOR . '/model.php'] as $legacyBootstrap) {
            if (is_file($legacyBootstrap)) {
                require_once $legacyBootstrap;
            }
        }
    }

    private function defineComponentPaths(string $component): void
    {
        if (!preg_match('/^com_[a-z0-9_]+$/', $component)) {
            throw new ActionException('COMPONENT_UNAVAILABLE', 'The requested Joomla component name is invalid.');
        }

        if (defined('JPATH_ADMINISTRATOR') && !defined('JPATH_COMPONENT_ADMINISTRATOR')) {
            define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/' . $component);
        }

        if (defined('JPATH_SITE') && !defined('JPATH_COMPONENT_SITE')) {
            define('JPATH_COMPONENT_SITE', JPATH_SITE . '/components/' . $component);
        }

        if (defined('JPATH_COMPONENT_ADMINISTRATOR') && !defined('JPATH_COMPONENT')) {
            define('JPATH_COMPONENT', JPATH_COMPONENT_ADMINISTRATOR);
        }
    }
}
