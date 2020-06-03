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

use Composer\Composer;
use Composer\Package\PackageInterface;
use Symfony\Flex\SymfonyBundle;

/**
 * Repository of recipes.
 *
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
abstract class AbstractRecipeRepository implements RecipeRepositoryInterface
{
    protected Composer $composer;

    private ?array $devPackages = null;

    /**
     * @param Composer $composer The composer
     */
    public function __construct(Composer $composer)
    {
        $this->composer = $composer;
    }

    /**
     * Get the recipe data.
     *
     * @param PackageInterface $package  The Composer package
     * @param string           $version  The version of recipe
     * @param array            $manifest The manifest of recipe
     * @param string           $job      The job type of Composer operation
     */
    protected function getRecipeData(PackageInterface $package, string $version, array $manifest, string $job): array
    {
        $name = $package->getName();

        $data = [
            'manifest' => $manifest,
            'origin' => sprintf('%s:%s@%s', $name, $version, $this->getRepoOrigin($package)),
        ];

        if ('symfony-bundle' === $package->getType()) {
            $bundle = new SymfonyBundle($this->composer, $package, $job);
            $envs = \in_array($name, $this->getDevPackage(), true) ? ['dev', 'test'] : ['all'];

            foreach ($bundle->getClassNames() as $class) {
                $data['manifest']['bundles'][$class] = $envs;
            }
        }

        return $data;
    }

    /**
     * Get the list dev packages.
     */
    protected function getDevPackage(): array
    {
        if (null === $this->devPackages) {
            $this->devPackages = array_map(
                static function ($package) { return $package['name']; },
                $this->composer->getLocker()->getLockData()['packages-dev']
            );
        }

        return $this->devPackages;
    }

    /**
     * Get the origin.
     *
     * @param PackageInterface $package The Composer package
     */
    abstract protected function getRepoOrigin(PackageInterface $package): string;
}
