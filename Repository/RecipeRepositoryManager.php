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
 * Manager of recipe repositories.
 *
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class RecipeRepositoryManager
{
    /**
     * @var RecipeRepositoryInterface[]
     */
    private $repositories = [];

    /**
     * Constructor.
     *
     * @param RecipeRepositoryInterface[] $repositories The recipe repositories
     */
    public function __construct(array $repositories = [])
    {
        foreach ($repositories as $repository) {
            $this->add($repository);
        }
    }

    /**
     * Check if the repository is defined.
     *
     * @param string $name The repository name
     */
    public function has(string $name): bool
    {
        return isset($this->repositories[$name]);
    }

    /**
     * Add the recipe repository.
     *
     * @param RecipeRepositoryInterface $repository The recipe repository
     */
    public function add(RecipeRepositoryInterface $repository): void
    {
        $name = $repository->getName();

        if (!isset($this->repositories[$name])) {
            $this->repositories[$name] = $repository;
        }
    }

    /**
     * Get the recipe for the package.
     *
     * @param PackageInterface $package The package
     * @param string           $job     The job type of Composer operation
     */
    public function getRecipe(PackageInterface $package, string $job): ?RecipeMeta
    {
        $recipe = null;

        foreach ($this->repositories as $repository) {
            if ($repository->has($package)) {
                $recipe = $repository->get($package, $job);

                break;
            }
        }

        return $recipe;
    }
}
