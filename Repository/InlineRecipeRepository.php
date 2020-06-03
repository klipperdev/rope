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

use Composer\Json\JsonFile;
use Composer\Package\PackageInterface;
use Klipper\Rope\RecipeMeta;
use Klipper\Rope\Utils;
use Symfony\Flex\Recipe;

/**
 * Repository of recipes.
 *
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class InlineRecipeRepository extends AbstractRecipeRepository
{
    public function getName(): string
    {
        return 'klipper/rope-recipe-inline';
    }

    public function has(PackageInterface $package): bool
    {
        $json = new JsonFile($this->getRopeFilePath($package));

        return $json->exists();
    }

    public function get(PackageInterface $package, string $job): ?RecipeMeta
    {
        $recipe = null;

        if ($this->has($package)) {
            $json = new JsonFile($this->getRopeFilePath($package));
            $version = ltrim($package->getPrettyVersion(), 'v');
            $data = $this->getRecipeData($package, $version, $json->read(), $job);
            $recipe = new RecipeMeta(new Recipe($package, $package->getName(), $job, $data), Utils::getReference($package));
        }

        return $recipe;
    }

    protected function getRepoOrigin(PackageInterface $package): string
    {
        return Utils::getRecipeRepo($package).':'.Utils::getRecipeBranch($package);
    }

    /**
     * Get the path of the Rope manifest file.
     *
     * @param PackageInterface $package The package
     */
    protected function getRopeFilePath(PackageInterface $package): string
    {
        return $this->composer->getInstallationManager()->getInstallPath($package).'/rope.json';
    }
}
