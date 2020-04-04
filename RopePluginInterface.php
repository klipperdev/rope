<?php

/*
 * This file is part of the Klipper Rope package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Rope;

use Composer\Plugin\PluginInterface;

/**
 * Composer plugin for Klipper.
 *
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
interface RopePluginInterface extends PluginInterface
{
    /**
     * Returns the map of the unique configurator name and the class name of the Symfony Flex Configurator.
     *
     * It's recommended to prefix the configurator name.
     * The class must extend the Symfony\Flex\Configurator\AbstractConfigurator class.
     *
     * @return array
     */
    public function getRopeConfigurators(): iterable;
}
