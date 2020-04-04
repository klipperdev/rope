<?php

/*
 * This file is part of the Klipper Rope package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Rope\Repository;

use Composer\Package\PackageInterface;
use Klipper\Rope\RecipeMeta;

/**
 * Interface of the recipe repository.
 *
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
interface RecipeRepositoryInterface
{
    /**
     * Get the name of recipe repository.
     */
    public function getName(): string;

    /**
     * Check if the recipe is present for the package.
     *
     * @param PackageInterface $package The package
     */
    public function has(PackageInterface $package): bool;

    /**
     * Get the recipe for the package.
     *
     * @param PackageInterface $package The package
     * @param string           $job     The job type of Composer operation
     */
    public function get(PackageInterface $package, string $job): ?RecipeMeta;
}
